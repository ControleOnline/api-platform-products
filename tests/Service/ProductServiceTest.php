<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupProduct;
use ControleOnline\Service\PeopleService;
use ControleOnline\Service\PrintService;
use ControleOnline\Service\ProductService;
use ControleOnline\Repository\ProductGroupProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AllowMockObjectsWithoutExpectations]
class ProductServiceTest extends TestCase
{
    #[DataProvider('allowedGroupPriceCalculationProvider')]
    public function testValidateImportRowAcceptsSupportedGroupPriceCalculations(string $value): void
    {
        $service = $this->createProductService();
        $method = new ReflectionMethod(ProductService::class, 'validateImportRow');
        $method->setAccessible(true);

        $method->invoke($service, [
            'category_name' => 'Categoria',
            'product_name' => 'Produto',
            'group_name' => 'Grupo',
            'group_price_calculation' => $value,
        ]);

        self::assertTrue(true);
    }

    public function testValidateImportRowRejectsInvalidGroupPriceCalculation(): void
    {
        $service = $this->createProductService();
        $method = new ReflectionMethod(ProductService::class, 'validateImportRow');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);

        $method->invoke($service, [
            'category_name' => 'Categoria',
            'product_name' => 'Produto',
            'group_name' => 'Grupo',
            'group_price_calculation' => 'invalid',
        ]); 
    }

    public function testValidateImportRowRejectsRecipeProductType(): void
    {
        $service = $this->createProductService();
        $method = new ReflectionMethod(ProductService::class, 'validateImportRow');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);

        $method->invoke($service, [
            'category_name' => 'Categoria',
            'product_name' => 'Preparo',
            'product_type' => 'recipe',
        ]);
    }

    public static function allowedGroupPriceCalculationProvider(): array
    {
        return [
            ['sum'],
            ['average'],
            ['biggest'],
            ['free'],
        ];
    }

    public function testLinkGroupItemNullsProductForSharedModifiers(): void
    {
        $service = $this->createProductService();
        $method = new ReflectionMethod(ProductService::class, 'linkGroupItem');
        $method->setAccessible(true);

        $parentProduct = $this->createMock(Product::class);
        $group = $this->createMock(ProductGroup::class);
        $item = $this->createMock(Product::class);
        $repository = $this->createMock(ProductGroupProductRepository::class);
        $persisted = null;

        $repository->expects(self::once())
            ->method('findSharedGroupItem')
            ->with($group, $item, 'component', 2.0)
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(ProductGroupProduct::class)
            ->willReturn($repository);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function ($entity) use (&$persisted): bool {
                $persisted = $entity;
                return $entity instanceof ProductGroupProduct;
            }));

        $this->setProductServiceEntityManager($service, $entityManager);

        $method->invoke($service, $parentProduct, $group, $item, [
            'item_product_type' => 'component',
            'item_quantity' => '2',
            'item_price' => '4.50',
        ]);

        self::assertInstanceOf(ProductGroupProduct::class, $persisted);
        self::assertSame($group, $persisted->getProductGroup());
        self::assertSame($item, $persisted->getProductChild());
        self::assertSame('component', $persisted->getProductType());
        self::assertNull($persisted->getProduct());
        self::assertSame(2.0, $persisted->getQuantity());
        self::assertSame(4.5, $persisted->getPrice());
    }

    public function testLinkGroupItemKeepsParentAnchorForFeedstock(): void
    {
        $service = $this->createProductService();
        $method = new ReflectionMethod(ProductService::class, 'linkGroupItem');
        $method->setAccessible(true);

        $parentProduct = $this->createMock(Product::class);
        $group = $this->createMock(ProductGroup::class);
        $item = $this->createMock(Product::class);
        $repository = $this->createMock(ProductGroupProductRepository::class);
        $persisted = null;

        $repository->expects(self::once())
            ->method('findSharedGroupItem')
            ->with($group, $item, 'feedstock', 1.0)
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(ProductGroupProduct::class)
            ->willReturn($repository);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function ($entity) use (&$persisted): bool {
                $persisted = $entity;
                return $entity instanceof ProductGroupProduct;
            }));

        $this->setProductServiceEntityManager($service, $entityManager);

        $method->invoke($service, $parentProduct, $group, $item, [
            'item_product_type' => 'feedstock',
            'item_quantity' => '1',
            'item_price' => '0',
        ]);

        self::assertInstanceOf(ProductGroupProduct::class, $persisted);
        self::assertSame($group, $persisted->getProductGroup());
        self::assertSame($item, $persisted->getProductChild());
        self::assertSame('feedstock', $persisted->getProductType());
        self::assertSame($parentProduct, $persisted->getProduct());
        self::assertSame(1.0, $persisted->getQuantity());
        self::assertSame(0.0, $persisted->getPrice());
    }

    private function createProductService(): ProductService
    {
        return new ProductService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(PrintService::class),
            $this->createMock(PeopleService::class),
        );
    }

    private function setProductServiceEntityManager(ProductService $service, EntityManagerInterface $entityManager): void
    {
        $property = new \ReflectionProperty(ProductService::class, 'manager');
        $property->setAccessible(true);
        $property->setValue($service, $entityManager);
    }
}
