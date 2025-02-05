<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\QueryBuilder;

class ProductGroupService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleService $PeopleService
    ) {}

}
