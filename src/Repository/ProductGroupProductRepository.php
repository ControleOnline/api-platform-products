<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\ProductGroupProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductGroupProduct>
 *
 * @method ProductGroupProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductGroupProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductGroupProduct[]    findAll()
 * @method ProductGroupProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductGroupProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductGroupProduct::class);
    }
}
