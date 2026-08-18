<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\ProductShowcase;
use ControleOnline\Entity\ProductShowcaseItem;
use ControleOnline\Repository\ProductShowcaseItemRepository;
use ControleOnline\Service\ProductCatalogQueryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[AllowMockObjectsWithoutExpectations]
class ProductCatalogQueryServiceTest extends TestCase
{
    public function testShowcaseQueryRequiresPublishedOwnedProductsAndDeterministicOrder(): void
    {
        $company = $this->createMock(People::class);
        $showcase = (new ProductShowcase())
            ->setCompany($company)
            ->setName('POS')
            ->setIntegrationKey('pos');
        $manager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = (new QueryBuilder($manager))
            ->select('item')
            ->from(ProductShowcaseItem::class, 'item');
        $repository = $this->createMock(ProductShowcaseItemRepository::class);
        $repository->expects(self::once())
            ->method('createQueryBuilder')
            ->with('item')
            ->willReturn($queryBuilder);
        $manager->expects(self::once())
            ->method('getRepository')
            ->with(ProductShowcaseItem::class)
            ->willReturn($repository);
        $service = new ProductCatalogQueryService($manager);

        $buildQuery = new ReflectionMethod($service, 'createShowcaseItemsQueryBuilder');
        $queryBuilder = $buildQuery->invoke($service, $showcase, []);
        $applyOrdering = new ReflectionMethod($service, 'applyShowcaseOrdering');
        $applyOrdering->invoke($service, $queryBuilder);
        $dql = $queryBuilder->getDQL();

        self::assertStringContainsString('item.active = true', $dql);
        self::assertStringContainsString('item.published = true', $dql);
        self::assertStringContainsString('product.active = true', $dql);
        self::assertStringContainsString('product.company = :showcaseCompany', $dql);
        self::assertStringContainsString(
            'ORDER BY showcaseSortOrderNull ASC, item.sortOrder ASC, product.product ASC, product.id ASC',
            $dql
        );
    }
}
