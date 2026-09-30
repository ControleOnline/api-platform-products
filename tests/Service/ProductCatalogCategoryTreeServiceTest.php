<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Category;
use ControleOnline\Entity\People;
use ControleOnline\Repository\CategoryRepository;
use ControleOnline\Service\CategoryPayloadService;
use ControleOnline\Service\CategoryTreeService;
use ControleOnline\Service\ProductCatalogCategoryTreeService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ProductCatalogCategoryTreeServiceTest extends TestCase
{
    public function testPublishedProjectionIncludesAncestorsButNotSameTenantUnpublishedCategory(): void
    {
        $company = $this->company(3);
        $root = $this->category(12, 'Cardápio', $company);
        $published = $this->category(15, 'Pizzas', $company, $root);
        $sameTenantUnpublished = $this->category(99, 'Oculta', $company, $root);
        $repository = $this->repository(
            3,
            [$sameTenantUnpublished, $published, $root]
        );

        $payload = $this->service($repository)->build($company, [15]);

        self::assertSame([12, 15], array_column($payload, 'id'));
        self::assertNotContains(99, array_column($payload, 'id'));
        self::assertSame('/categories/12', $payload[0]['@id']);
        self::assertSame(12, $payload[1]['parent']['id']);
    }

    public function testResolvedEmptyShowcaseKeepsCategoryTreeAuthoritativelyEmpty(): void
    {
        $company = $this->company(3);
        $repository = $this->repository(3, [
            $this->category(99, 'Produto legado', $company),
        ]);

        self::assertSame([], $this->service($repository)->build($company, []));
    }

    public function testLegacyProjectionCanExposeAllActiveCategoryIdsProvidedByProducts(): void
    {
        $company = $this->company(3);
        $first = $this->category(12, 'Serviços', $company);
        $second = $this->category(99, 'Customizados', $company);
        $repository = $this->repository(3, [$second, $first]);

        $payload = $this->service($repository)->build($company, [12, 99]);

        self::assertSame([99, 12], array_column($payload, 'id'));
    }

    private function service(CategoryRepository $repository): ProductCatalogCategoryTreeService
    {
        return new ProductCatalogCategoryTreeService(
            $repository,
            new CategoryTreeService(),
            new CategoryPayloadService()
        );
    }

    /**
     * @param Category[] $categories
     */
    private function repository(int $companyId, array $categories): CategoryRepository
    {
        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('findTreeCandidates')
            ->with($companyId, 'products')
            ->willReturn($categories);

        return $repository;
    }

    private function company(int $id): People
    {
        $company = $this->createMock(People::class);
        $company->method('getId')->willReturn($id);

        return $company;
    }

    private function category(
        int $id,
        string $name,
        People $company,
        ?Category $parent = null
    ): Category {
        $category = (new Category())
            ->setName($name)
            ->setContext('products')
            ->setCompany($company)
            ->setParent($parent);
        (new ReflectionProperty($category, 'id'))->setValue($category, $id);

        return $category;
    }
}
