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
        $companies = implode(',', array_map(
            fn($c) => $c->getId(),
            $this->peopleService->getMyCompanies()
        ));

        try {
            $conn = $this->getEntityManager()->getConnection();
            $conn->executeStatement('CALL update_product_inventory(?)', [$companies]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getProductsInventory(?People $company): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select([
            'p.id as product_id',
            'p.product as product_name',
            'p.description',
            'pu.unity',
            'pu.unitType',
            'pi.available',
            'pi.minimum',
            'pi.maximum',
            'c.name as company_name',
            'i.inventory as inventory_name'
        ])
            ->join('p.productUnit', 'pu')
            ->join('p.company', 'c')
            ->join('ControleOnline\Entity\ProductInventory', 'pi', 'WITH', 'pi.product = p.id')
            ->join('pi.inventory', 'i')
            ->orderBy('p.product', 'ASC');

        if ($company !== null) {
            $qb->andWhere('p.company = :company')
                ->setParameter('company', $company);
        }

        return $qb->getQuery()->getArrayResult();
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

        error_log($qb->getQuery()->getSQL());
        return $qb->getQuery()->getResult();
    }
}
