<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\People;
use ControleOnline\Entity\ProductShowcase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProductShowcase|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductShowcase|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductShowcase[]    findAll()
 * @method ProductShowcase[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductShowcaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductShowcase::class);
    }

    public function findDefaultActive(People $company, string $integrationKey): ?ProductShowcase
    {
        return $this->createQueryBuilder('showcase')
            ->andWhere('showcase.company = :company')
            ->andWhere('showcase.integrationKey = :integrationKey')
            ->andWhere('showcase.active = true')
            ->setParameter('company', $company)
            ->setParameter('integrationKey', ProductShowcase::normalizeIntegrationKey($integrationKey))
            ->orderBy('showcase.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
