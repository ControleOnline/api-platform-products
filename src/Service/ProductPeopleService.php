<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductPeople;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;

class ProductPeopleService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
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
        $this->assertMutationAllowed($productPeople);
    }

    public function preUpdate(ProductPeople $productPeople): void
    {
        $this->assertMutationAllowed($productPeople);
    }

    public function preRemove(ProductPeople $productPeople): void
    {
        $this->assertMutationAllowed($productPeople);
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

        $this->productService->assertCanManageProduct($product);

        if (!$this->isPeopleVisible($people)) {
            throw new AccessDeniedHttpException('Voce nao pode vincular esta pessoa ao produto informado.');
        }
    }

    private function isPeopleVisible(People $people): bool
    {
        if ($people->getId() === null) {
            return false;
        }

        $queryBuilder = $this->manager->getRepository(People::class)->createQueryBuilder('productPeopleVisiblePeople');
        $queryBuilder->andWhere('productPeopleVisiblePeople.id = :productPeopleVisiblePeopleId');
        $queryBuilder->setParameter('productPeopleVisiblePeopleId', (int) $people->getId());

        $this->peopleService->checkLink($queryBuilder, People::class, null, 'productPeopleVisiblePeople');

        return $queryBuilder
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() instanceof People;
    }
}
