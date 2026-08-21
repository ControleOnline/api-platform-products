<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Category;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductCategory;
use ControleOnline\Entity\ProductFile;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupParent;
use ControleOnline\Entity\ProductGroupProduct;
use ControleOnline\Entity\ProductShowcase;
use ControleOnline\Repository\ProductCategoryRepository;
use ControleOnline\Repository\ProductFileRepository;
use ControleOnline\Repository\ProductGroupRepository;
use ControleOnline\Service\ProductCatalogProjectionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[AllowMockObjectsWithoutExpectations]
class ProductCatalogProjectionServiceTest extends TestCase
{
    public function testProjectionPreservesPlacementsAndRecursiveCustomizationOrder(): void
    {
        $company = $this->people(1);
        $otherCompany = $this->people(2);
        $parent = $this->product(1, 'Pizza', $company);
        $firstChild = $this->product(2, 'Abacaxi', $company);
        $secondChild = $this->product(3, 'Bacon', $company);
        $nestedChild = $this->product(4, 'Molho', $company);
        $foreignChild = $this->product(99, 'Produto externo', $otherCompany);

        $mainGroup = $this->group(20, 'Adicionais', 2, $company, $parent);
        $mainGroup->addProduct($this->groupProduct(202, $secondChild, null));
        $mainGroup->addProduct($this->groupProduct(201, $firstChild, 0));
        $mainGroup->addProduct($this->groupProduct(299, $foreignChild, 1));

        $nestedGroup = $this->group(30, 'Molhos', 1, $company, $firstChild);
        $nestedGroup->addProduct($this->groupProduct(301, $nestedChild, 0));

        $category = (new Category())
            ->setName('Pizzas')
            ->setContext('products')
            ->setCompany($company)
            ->setSortOrder(4);
        $this->setId($category, 10);
        $relation = (new ProductCategory())
            ->setProduct($parent)
            ->setCategory($category)
            ->setSortOrder(3);
        $this->setId($relation, 100);

        $groupRepository = $this->createMock(ProductGroupRepository::class);
        $groupRepository->method('findActiveForParentProducts')
            ->willReturnCallback(static function (array $products) use ($mainGroup, $nestedGroup): array {
                $ids = array_map(static fn(Product $product): int => (int) $product->getId(), $products);
                if (in_array(1, $ids, true)) {
                    return [$mainGroup];
                }
                if (in_array(2, $ids, true)) {
                    return [$nestedGroup];
                }

                return [];
            });
        $categoryRepository = $this->createMock(ProductCategoryRepository::class);
        $categoryRepository->expects(self::once())
            ->method('findCatalogRelations')
            ->with(
                self::callback(static fn(array $products): bool => count($products) === 4),
                $company
            )
            ->willReturn([$relation]);
        $showcase = (new ProductShowcase())
            ->setCompany($company)
            ->setName('Shop')
            ->setIntegrationKey('shop');
        $categoryRepository->expects(self::once())
            ->method('findPublishedCategoryIdsForShowcase')
            ->with($showcase)
            ->willReturn([10, 11]);
        $fileRepository = $this->createMock(ProductFileRepository::class);
        $fileRepository->method('findBy')->willReturn([]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturnCallback(
            static fn(string $class): object => match ($class) {
                ProductGroup::class => $groupRepository,
                ProductCategory::class => $categoryRepository,
                ProductFile::class => $fileRepository,
            }
        );

        $projection = (new ProductCatalogProjectionService($manager))
            ->build([$parent], $company, $showcase);
        $product = $projection['products'][1];

        self::assertSame([10, 11], $projection['categoryIds']);
        self::assertSame([10], $product['categoryIds']);
        self::assertSame(3, $product['productCategories'][0]['sortOrder']);
        self::assertSame(['id' => 10, '@id' => '/categories/10'], $product['productCategories'][0]['category']);
        self::assertTrue($product['hasCustomizationGroups']);
        self::assertSame(20, $product['productGroups'][0]['id']);
        self::assertSame([2, 3], array_column(
            array_column($product['productGroups'][0]['products'], 'product'),
            'id'
        ));
        self::assertSame(
            30,
            $product['productGroups'][0]['products'][0]['product']['productGroups'][0]['id']
        );
        self::assertSame(
            4,
            $product['productGroups'][0]['products'][0]['product']['productGroups'][0]['products'][0]['product']['id']
        );
    }

    private function people(int $id): People
    {
        $people = $this->createMock(People::class);
        $people->method('getId')->willReturn($id);

        return $people;
    }

    private function product(int $id, string $name, People $company): Product
    {
        return (new Product())
            ->setId($id)
            ->setProduct($name)
            ->setCompany($company)
            ->setType('product')
            ->setDescription('')
            ->setActive(true);
    }

    private function group(
        int $id,
        string $name,
        int $order,
        People $company,
        Product $parent
    ): ProductGroup {
        $group = (new ProductGroup())
            ->setProductGroup($name)
            ->setGroupOrder($order)
            ->setCompany($company)
            ->setActive(true);
        $this->setId($group, $id);
        $group->addParentProduct(
            (new ProductGroupParent())
                ->setParentProduct($parent)
                ->setActive(true)
        );

        return $group;
    }

    private function groupProduct(int $id, Product $child, ?int $sortOrder): ProductGroupProduct
    {
        $item = (new ProductGroupProduct())
            ->setProductChild($child)
            ->setProductType('component')
            ->setQuantity(1)
            ->setPrice(0)
            ->setSortOrder($sortOrder)
            ->setActive(true);
        $this->setId($item, $id);

        return $item;
    }

    private function setId(object $entity, int $id): void
    {
        $property = new ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
