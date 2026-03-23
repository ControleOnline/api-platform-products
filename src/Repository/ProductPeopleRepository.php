<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\ProductPeople;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ControleOnline\Entity\People;
use ControleOnline\Service\PeopleService;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * @method ProductPeople|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductPeople|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductPeople[]    findAll()
 * @method ProductPeople[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductPeopleRepository extends ServiceEntityRepository
{

    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, ProductPeople::class);
    }
}
