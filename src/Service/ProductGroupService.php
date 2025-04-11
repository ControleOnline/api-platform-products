<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
 as Security;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductGroupService
{
    private $request;
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PeopleService $PeopleService,
        RequestStack $requestStack

    ) {
        $this->request  = $requestStack->getCurrentRequest();
    }



    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        if ($product = $this->request->query->get('product', null)) {
            $queryBuilder->join(sprintf('%s.products', $rootAlias), 'productGroupProduct');
            $queryBuilder->join('productGroupProduct.product', 'product');
            $queryBuilder->andWhere('product.id = :product');
            $queryBuilder->setParameter('product', $product);
        }
    }
}
