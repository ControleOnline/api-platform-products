<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ControleOnline\Entity\People;
use ControleOnline\Service\PeopleService;
use Doctrine\ORM\QueryBuilder;

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

    private function getInventoryData(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->select([
                'op.outInventory AS inventory_id',
                'p.id AS product_id',
                "SUM(CASE WHEN o.orderType = 'purchasing' AND o.status IN (:purchasing_status) THEN op.quantity ELSE 0 END) - 
                 SUM(CASE WHEN o.orderType = 'sale' AND o.status IN (:sales_status) THEN op.quantity ELSE 0 END) AS available",
                "SUM(CASE WHEN o.orderType = 'purchasing' AND o.status IN (:ordered_status) THEN op.quantity ELSE 0 END) AS ordered",
                "SUM(CASE WHEN o.orderType = 'purchasing' AND o.status IN (:transit_status) THEN op.quantity ELSE 0 END) AS transit",
                '0 AS minimum',
                '0 AS maximum',
                "SUM(CASE WHEN o.orderType = 'sale' AND o.status IN (:sales_status) THEN op.quantity ELSE 0 END) AS sales"
            ])
            ->join('ControleOnline\Entity\OrderProduct', 'op', 'WITH', 'op.product = p.id')
            ->join('op.order', 'o')
            ->andWhere("o.orderType IN ('purchasing', 'sale')")
            ->andWhere('o.status IN (:all_status)')
            ->andWhere("p.type IN ('product', 'feedstock')")
            ->andWhere(
                "(o.orderType = 'sale' AND o.provider IN (:provider_id)) OR 
                 (o.orderType = 'purchasing' AND o.client IN (:client_id))"
            )
            ->groupBy('op.outInventory, p.id');
    }

    public function updateInventory(): void
    {
        $em = $this->getEntityManager();

        $purchasingStatus = [7];
        $orderedStatus    = [5];
        $transitStatus    = [6];
        $salesStatus      = [6];
        $allStatus = array_unique(array_merge($purchasingStatus, $orderedStatus, $transitStatus, $salesStatus));

        try {
            $em->getConnection()->beginTransaction();

            $inventoryQb = $this->getInventoryData()
                ->setParameter('purchasing_status', $purchasingStatus)
                ->setParameter('sales_status', $salesStatus)
                ->setParameter('ordered_status', $orderedStatus)
                ->setParameter('transit_status', $transitStatus)
                ->setParameter('all_status', $allStatus)
                ->setParameter('provider_id', $this->peopleService->getMyCompanies())
                ->setParameter('client_id', $this->peopleService->getMyCompanies());

            $subquery = $inventoryQb->getQuery()->getSQL();

            $sql = '
            UPDATE product_inventory pi
            JOIN (
                ' . $subquery . '
            ) inv ON pi.inventory_id = inv.inventory_id AND pi.product_id = inv.product_id
            SET pi.available = inv.available,
                pi.ordered   = inv.ordered,
                pi.transit   = inv.transit,
                pi.minimum   = inv.minimum,
                pi.maximum   = inv.maximum,
                pi.sales     = inv.sales
        ';

            $em->getConnection()->executeStatement($sql, [
                'purchasing_status' => $purchasingStatus,
                'sales_status'      => $salesStatus,
                'ordered_status'    => $orderedStatus,
                'transit_status'    => $transitStatus,
                'all_status'        => $allStatus,
                'provider_id'       => $this->peopleService->getMyCompanies(),
                'client_id'         => $this->peopleService->getMyCompanies()
            ]);

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollBack();
            throw new \Exception($e->getMessage());
        }
    }


    public function getPurchasingSuggestion(?People $company): array
    {
        $this->updateInventory();
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
