<?php

namespace App\Repository;

use App\Entity\ProductFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProductFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductFile|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductFile[]    findAll()
 * @method ProductFile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductFile::class);
    }
}
