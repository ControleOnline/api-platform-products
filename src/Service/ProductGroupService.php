<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
as Security;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductGroupService
{
    private $request;
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private PeopleService $PeopleService,
        RequestStack $requestStack

    ) {
        $this->request  = $requestStack->getCurrentRequest();
    }

    public function discoveryProductGroup(Product $parentProduct, string $groupName): ProductGroup
    {
        $productGroup = $this->entityManager->getRepository(ProductGroup::class)->findOneBy([
            'productGroup' => $groupName,
            'parentProduct' => $parentProduct
        ]);

        if (!$productGroup) {
            $productGroup = new ProductGroup();
            $productGroup->setParentProduct($parentProduct);
            $productGroup->setProductGroup($groupName);
            $productGroup->setPriceCalculation('sum');
            $productGroup->setRequired(false);
            $productGroup->setMinimum(0);
            $productGroup->setMaximum(0);
            $productGroup->setActive(true);
            $productGroup->setGroupOrder(0);
            $this->entityManager->persist($productGroup);
            $this->entityManager->flush();
        }

        return $productGroup;
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
