<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductPeople;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductPeopleService
{
    private const ALLOWED_ROLES = ['supplier', 'manufacturer', 'distributor'];

    public function __construct(
        private EntityManagerInterface $manager,
        private PeopleService $peopleService,
        private ProductService $productService
    ) {}

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        $aliases = $queryBuilder->getAllAliases();

        if (!in_array('productPeopleProduct', $aliases, true)) {
            $queryBuilder->innerJoin(sprintf('%s.product', $rootAlias), 'productPeopleProduct');
        }

        if (!in_array('productPeopleLinkedPeople', $aliases, true)) {
            $queryBuilder->innerJoin(sprintf('%s.people', $rootAlias), 'productPeopleLinkedPeople');
        }

        $this->productService->securityFilter($queryBuilder, $resourceClass, $applyTo, 'productPeopleProduct');
        $this->peopleService->checkLink($queryBuilder, $resourceClass, $applyTo, 'productPeopleLinkedPeople');
        $queryBuilder->distinct();
    }

    public function prePersist(ProductPeople $productPeople): void
    {
        $this->normalizeAndValidate($productPeople);
        $this->assertMutationAllowed($productPeople);
        $this->assertNoDuplicateLink($productPeople);
    }

    public function preUpdate(ProductPeople $productPeople): void
    {
        $this->normalizeAndValidate($productPeople);
        $this->assertMutationAllowed($productPeople);
        $this->assertNoDuplicateLink($productPeople);
    }

    public function preRemove(ProductPeople $productPeople): void
    {
        $this->assertMutationAllowed($productPeople);
    }

    private function normalizeAndValidate(ProductPeople $productPeople): void
    {
        $role = strtolower(trim((string) $productPeople->getRole()));
        if ($role === '') {
            $role = 'supplier';
        }
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw new BadRequestHttpException(
                sprintf('Role inválida para product_people. Use: %s.', implode(', ', self::ALLOWED_ROLES))
            );
        }
        $productPeople->setRole($role);

        $supplierSku = $productPeople->getSupplierSku();
        if (is_string($supplierSku) && trim($supplierSku) === '') {
            $productPeople->setSupplierSku(null);
        }
    }

    private function assertMutationAllowed(ProductPeople $productPeople): void
    {
        $product = $productPeople->getProduct();
        $people = $productPeople->getPeople();

        if (!$product instanceof Product) {
            throw new BadRequestHttpException('Produto obrigatorio para o vinculo de fornecedor.');
        }

        if (!$people instanceof People) {
            throw new BadRequestHttpException('Pessoa obrigatoria para o vinculo de fornecedor.');
        }

        // Garante company hidratada (proxy / denormalize por IRI).
        $productId = $product->getId();
        if ($productId) {
            $managed = $this->manager->getRepository(Product::class)->find($productId);
            if ($managed instanceof Product) {
                $product = $managed;
                $productPeople->setProduct($product);
            }
        }

        $this->productService->assertCanManageProduct($product);

        if (!$this->isVisiblePeople($people)) {
            throw new AccessDeniedHttpException('Você não pode vincular este fornecedor ao produto.');
        }
    }

    private function assertNoDuplicateLink(ProductPeople $productPeople): void
    {
        $product = $productPeople->getProduct();
        $people = $productPeople->getPeople();
        if (!$product instanceof Product || !$people instanceof People) {
            return;
        }

        $qb = $this->manager->createQueryBuilder()
            ->select('pp')
            ->from(ProductPeople::class, 'pp')
            ->andWhere('pp.product = :product')
            ->andWhere('pp.people = :people')
            ->andWhere('pp.role = :role')
            ->setParameter('product', $product)
            ->setParameter('people', $people)
            ->setParameter('role', $productPeople->getRole())
            ->setMaxResults(1);

        $sku = $productPeople->getSupplierSku();
        if ($sku === null || $sku === '') {
            $qb->andWhere('pp.supplierSku IS NULL OR pp.supplierSku = :emptySku')
                ->setParameter('emptySku', '');
        } else {
            $qb->andWhere('pp.supplierSku = :sku')
                ->setParameter('sku', $sku);
        }

        if ($productPeople->getId()) {
            $qb->andWhere('pp.id != :id')->setParameter('id', $productPeople->getId());
        }

        $existing = $qb->getQuery()->getOneOrNullResult();
        if ($existing instanceof ProductPeople) {
            throw new ConflictHttpException('Já existe vínculo product_people para este produto, pessoa e role.');
        }
    }

    private function isVisiblePeople(People $people): bool
    {
        try {
            $queryBuilder = $this->manager->createQueryBuilder();
            $queryBuilder->select('productPeopleVisiblePeople');
            $queryBuilder->from(People::class, 'productPeopleVisiblePeople');
            $queryBuilder->andWhere('productPeopleVisiblePeople.id = :productPeopleVisiblePeopleId');
            $queryBuilder->setParameter('productPeopleVisiblePeopleId', (int) $people->getId());

            $this->peopleService->checkLink($queryBuilder, People::class, null, 'productPeopleVisiblePeople');

            return $queryBuilder
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult() instanceof People;
        } catch (\Throwable) {
            // Falha de visibilidade não deve virar 500; nega o vínculo.
            return false;
        }
    }
}
