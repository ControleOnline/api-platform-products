<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ControleOnline\Entity\People;
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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }


    public function getPurchasingSuggestion(?People $company): array
    {
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
                '(CASE WHEN SUM(pi.available + pi.ordered + pi.transit - pi.minimum - pi.sales) * -1 < 0 THEN 0 ELSE SUM(pi.available + pi.ordered + pi.transit - pi.minimum - pi.sales) * -1 END) AS needed'
            ])
            ->join('p.people', 'pe')
            ->join('ControleOnline\Entity\ProductInventory', 'pi', 'WITH', 'pi.product = p.id')
            ->andWhere('p.type NOT IN (:excludedTypes)')
            ->andWhere('pe IN (:companies)')
            ->groupBy('p.id, p.product, p.description, p.sku, pe.id, pe.name, pe.alias')
            ->having('needed > 0')
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
