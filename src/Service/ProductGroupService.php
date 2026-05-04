<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupParent;
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

        $this->linkParentProduct($productGroup, $parentProduct);

        return $productGroup;
    }

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        if ($product = $this->normalizeReferenceId($this->request->query->get('product', null))) {
            $queryBuilder->leftJoin(sprintf('%s.parentProducts', $rootAlias), 'productGroupParentFilter');
            $queryBuilder->leftJoin(sprintf('%s.products', $rootAlias), 'productGroupProductFilter');
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                sprintf('IDENTITY(%s.parentProduct) = :productGroupParentProduct', $rootAlias),
                'IDENTITY(productGroupParentFilter.parentProduct) = :productGroupParentProduct',
                'IDENTITY(productGroupProductFilter.product) = :productGroupParentProduct'
            ));
            $queryBuilder->andWhere(sprintf('%s.active = true', $rootAlias));
            $queryBuilder->setParameter('productGroupParentProduct', $product);
            $queryBuilder->distinct();
        }
    }

    private function linkParentProduct(ProductGroup $productGroup, Product $parentProduct): void
    {
        $link = $this->entityManager->getRepository(ProductGroupParent::class)->findOneBy([
            'productGroup' => $productGroup,
            'parentProduct' => $parentProduct,
        ]);

        if (!$link instanceof ProductGroupParent) {
            $link = new ProductGroupParent();
            $link->setProductGroup($productGroup);
            $link->setParentProduct($parentProduct);
            $this->entityManager->persist($link);
        }

        $link->setActive(true);
        $this->entityManager->flush();
    }

    private function normalizeReferenceId(mixed $reference): ?int
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if ($reference instanceof Product) {
            return (int) $reference->getId();
        }

        $id = preg_replace('/\D+/', '', (string) $reference);
        return $id !== '' ? (int) $id : null;
    }
}
