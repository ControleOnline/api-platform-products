<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ControleOnline\Entity\People;
use ControleOnline\Service\PeopleService;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(
        private PeopleService $peopleService,
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, Product::class);
    }

    public function updateProductInventory(): void
    {

        $sql = "INSERT INTO `product_inventory` (
                `inventory_id`, 
                `product_id`, 
                `available`, 
                `ordered`, 
                `transit`, 
                `minimum`, 
                `maximum`, 
                `sales`
            )
            SELECT 
                op.`inventory_id`,
                op.`product_id`,
                (SUM(CASE WHEN o.`order_type` = 'purchasing' AND o.`status_id` IN (:purchasing_status) THEN op.`quantity` ELSE 0 END) - 
                 SUM(CASE WHEN o.`order_type` = 'sale' AND o.`status_id` IN (:sales_status) THEN op.`quantity` ELSE 0 END)) AS `available`,
                SUM(CASE WHEN o.`order_type` = 'purchasing' AND o.`status_id` IN (:ordered_status) THEN op.`quantity` ELSE 0 END) AS `ordered`,
                SUM(CASE WHEN o.`order_type` = 'purchasing' AND o.`status_id` IN (:transit_status) THEN op.`quantity` ELSE 0 END) AS `transit`,
                0 AS `minimum`,
                0 AS `maximum`,
                SUM(CASE WHEN o.`order_type` = 'sale' AND o.`status_id` IN (:sales_status) THEN op.`quantity` ELSE 0 END) AS `sales`
            FROM `orders` o
            JOIN `order_product` op ON o.`id` = op.`order_id`
            JOIN `product` p ON op.`product_id` = p.`id`
            WHERE o.`order_type` IN ('purchasing', 'sale')
              AND o.`status_id` IN (:all_status)
              AND p.`type` IN ('product', 'feedstock')
              AND (
                  (o.`order_type` = 'sale' AND o.`provider_id` IN (:provider_id))
                  OR
                  (o.`order_type` = 'purchasing' AND o.`client_id` IN (:client_id))
              )
            GROUP BY op.`inventory_id`, op.`product_id`
            ON DUPLICATE KEY UPDATE
                `available` = VALUES(`available`),
                `ordered` = VALUES(`ordered`),
                `transit` = VALUES(`transit`),
                `sales` = VALUES(`sales`)
        ";

        $purchasingStatus = [7];
        $orderedStatus = [5];
        $transitStatus = [6];
        $salesStatus = [6];
        $allStatus = array_unique(array_merge($purchasingStatus, $orderedStatus, $transitStatus, $salesStatus));

        try {
            $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

            $companies = implode(',', array_map(fn($c) => $c->getId(), $this->peopleService->getMyCompanies()));

            $stmt->bindValue('purchasing_status', implode(',', $purchasingStatus), \PDO::PARAM_STR);
            $stmt->bindValue('sales_status', implode(',', $salesStatus), \PDO::PARAM_STR);
            $stmt->bindValue('ordered_status', implode(',', $orderedStatus), \PDO::PARAM_STR);
            $stmt->bindValue('transit_status', implode(',', $transitStatus), \PDO::PARAM_STR);
            $stmt->bindValue('all_status', implode(',', $allStatus), \PDO::PARAM_STR);
            $stmt->bindValue('provider_id', $companies, \PDO::PARAM_STR);
            $stmt->bindValue('client_id',  $companies, \PDO::PARAM_STR);

            $stmt->executeQuery();
        } catch (\Exception $e) {
            throw new \Exception("Erro ao atualizar o estoque: " . $e->getMessage());
        }
    }

    public function getPurchasingSuggestion(?People $company): array
    {
        $this->updateProductInventory();
        $qb = $this->createQueryBuilder('p')
            ->select([
                'pe.id AS company_id',
                'pe.name AS company_name',
                'pe.alias AS company_alias',
                'p.id AS product_id',
                'p.sku AS sku',
                'p.product AS product_name',
                'p.description AS description',
                'p.type AS type',
                'SUM(pi.available + pi.ordered + pi.transit - pi.sales) AS stock',
                'SUM(pi.minimum) AS minimum',
                'CASE WHEN SUM(pi.available + pi.ordered + pi.transit - pi.minimum - pi.sales) * -1 < 0 THEN 0 ELSE SUM(pi.available + pi.ordered + pi.transit - pi.minimum - pi.sales) * -1 END AS needed',
                'pu.productUnit AS unity'
            ])
            ->join('p.company', 'pe')
            ->join('ControleOnline\Entity\ProductInventory', 'pi', 'WITH', 'pi.product = p.id')
            ->join('p.productUnit', 'pu')
            ->andWhere('p.type NOT IN (:excludedTypes)')
            ->andWhere('pe IN (:companies)')
            ->groupBy('p.id, p.product, p.description, p.sku, pe.id, pe.name, pe.alias, pu.productUnit')
            ->having('needed > 0')
            ->addOrderBy('p.product', 'ASC')
            ->setParameter('excludedTypes', ['custom', 'component'])
            ->setParameter(
                'companies',
                $this->peopleService->getMyCompanies()
            );

        if ($company)
            $qb->andWhere('pe = :company')->setParameter('company', $company);

        return $qb->getQuery()->getResult();
    }
}
