<?php

namespace ControleOnline\Tests\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ControleOnline\Entity\Category;
use ControleOnline\Entity\File;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductCategory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Serializer\Attribute\Groups;

class ProductTest extends TestCase
{
    public function testTrackingCategoryUsesTheRootCategoryConfiguredOrder(): void
    {
        $root = $this->category(20, 'Molhos')->setSortOrder(4);
        $child = $this->category(21, 'Molhos em pote', $root);
        $product = (new Product())->setId(100);
        $relation = (new ProductCategory())
            ->setProduct($product)
            ->setCategory($child);

        $product->getProductCategory()->add($relation);

        self::assertSame(['id' => 20, 'rank' => 4], $product->getTrackingCategory());
    }

    public function testTrackingCategoryAcceptsZeroAsTheFirstConfiguredOrder(): void
    {
        $root = $this->category(20, 'Combos')->setSortOrder(0);
        $product = (new Product())->setId(100);
        $product->getProductCategory()->add(
            (new ProductCategory())->setProduct($product)->setCategory($root)
        );

        self::assertSame(['id' => 20, 'rank' => 0], $product->getTrackingCategory());
    }

    public function testTrackingCategoryIsNullWhenTheProductHasNoCategory(): void
    {
        self::assertNull((new Product())->getTrackingCategory());
    }

    public function testTrackingCategoryIsIncludedInConferencePayload(): void
    {
        $groups = (new \ReflectionMethod(Product::class, 'getTrackingCategory'))
            ->getAttributes(Groups::class)[0]
            ->newInstance()
            ->getGroups();

        self::assertContains('order_conference:read', $groups);
    }

    public function testProductCollectionUsesPartialFetchToExcludeFileBlobs(): void
    {
        $resource = (new \ReflectionClass(Product::class))
            ->getAttributes(ApiResource::class)[0]
            ->newInstance();

        $collectionOperation = null;
        foreach ($resource->getOperations() as $operation) {
            if ($operation instanceof GetCollection) {
                $collectionOperation = $operation;
                break;
            }
        }

        self::assertInstanceOf(GetCollection::class, $collectionOperation);
        self::assertTrue($collectionOperation->getFetchPartial());

        $contentGroups = (new ReflectionProperty(File::class, 'content'))
            ->getAttributes(Groups::class)[0]
            ->newInstance()
            ->getGroups();

        self::assertNotContains('product:read', $contentGroups);
    }

    private function category(int $id, string $name, ?Category $parent = null): Category
    {
        $category = (new Category())->setName($name)->setContext('products');
        $idProperty = new ReflectionProperty(Category::class, 'id');
        $idProperty->setValue($category, $id);

        if ($parent instanceof Category) {
            $category->setParent($parent);
        }

        return $category;
    }
}
