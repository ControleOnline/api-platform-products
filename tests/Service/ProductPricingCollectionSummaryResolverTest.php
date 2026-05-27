<?php

namespace ControleOnline\Tests\Service;

use ApiPlatform\Metadata\GetCollection;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupProduct;
use ControleOnline\Repository\ProductGroupProductRepository;
use ControleOnline\Repository\ProductGroupRepository;
use ControleOnline\Repository\ProductRepository;
use ControleOnline\Service\ProductPricingCollectionSummaryResolver;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ProductPricingCollectionSummaryResolverTest extends TestCase
{
    public function testBuildsPricingSummaryForMainProduct(): void
    {
        $mainProduct = $this->mockProduct(1343, 'Combo Alpha Gyros', 'custom');
        $requiredGroup = $this->mockGroup(10, 'Escolha a carne', true, 1);
        $optionalGroup = $this->mockGroup(11, 'Adicionais', false, 2);

        $directFeedstock = $this->mockGroupProduct(1, $mainProduct, null, 'feedstock', $this->mockProduct(2001, 'Pao', 'feedstock'), 150, 4.0);

        $cheapOptionProduct = $this->mockProduct(3001, 'Carne Fraldinha', 'component');
        $expensiveOptionProduct = $this->mockProduct(3002, 'Linguica Toscana', 'component');
        $optionalOptionProduct = $this->mockProduct(3003, 'Bacon', 'component');

        $cheapOption = $this->mockGroupProduct(2, $mainProduct, $requiredGroup, 'component', $cheapOptionProduct, 1, 9.99);
        $expensiveOption = $this->mockGroupProduct(3, $mainProduct, $requiredGroup, 'component', $expensiveOptionProduct, 1, 7.99);
        $optionalOption = $this->mockGroupProduct(4, $mainProduct, $optionalGroup, 'component', $optionalOptionProduct, 1, 3.0);

        $cheapFeedstock = $this->mockGroupProduct(5, $cheapOptionProduct, $requiredGroup, 'feedstock', $this->mockProduct(4001, 'Carne', 'feedstock'), 1, 6.5);
        $expensiveFeedstock = $this->mockGroupProduct(6, $expensiveOptionProduct, $requiredGroup, 'feedstock', $this->mockProduct(4002, 'Linguica', 'feedstock'), 1, 8.25);
        $optionalFeedstock = $this->mockGroupProduct(7, $optionalOptionProduct, $optionalGroup, 'feedstock', $this->mockProduct(4003, 'Bacon', 'feedstock'), 1, 2.1);

        $requiredGroup->method('getProducts')->willReturn(new ArrayCollection([$cheapOption, $expensiveOption]));
        $optionalGroup->method('getProducts')->willReturn(new ArrayCollection([$optionalOption]));

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('find')->with(1343)->willReturn($mainProduct);

        $groupRepository = $this->createMock(ProductGroupRepository::class);
        $groupRepository->method('findGroupsForProduct')->willReturnCallback(
            static function (Product $product, ?int $productGroupId, bool $requiredOnly) use ($requiredGroup, $optionalGroup): array {
                if ($productGroupId !== null) {
                    $groups = array_filter(
                        [$requiredGroup, $optionalGroup],
                        static fn(ProductGroup $group): bool => $group->getId() === $productGroupId
                    );

                    return array_values($groups);
                }

                if ($requiredOnly) {
                    return [$requiredGroup];
                }

                return [$requiredGroup, $optionalGroup];
            }
        );

        $groupProductRepository = $this->createMock(ProductGroupProductRepository::class);
        $groupProductRepository->method('findBy')->willReturnCallback(
            static function (array $criteria) use (
                $mainProduct,
                $requiredGroup,
                $optionalGroup,
                $directFeedstock,
                $cheapOption,
                $expensiveOption,
                $optionalOption,
                $cheapOptionProduct,
                $expensiveOptionProduct,
                $optionalOptionProduct,
                $cheapFeedstock,
                $expensiveFeedstock,
                $optionalFeedstock
            ): array {
                if (($criteria['product'] ?? null) === $mainProduct && 'feedstock' === ($criteria['productType'] ?? null) && array_key_exists('productGroup', $criteria) && null === $criteria['productGroup']) {
                    return [$directFeedstock];
                }

                if (($criteria['product'] ?? null) === $mainProduct && true === ($criteria['active'] ?? null)) {
                    return [$cheapOption, $expensiveOption, $optionalOption];
                }

                if (($criteria['product'] ?? null) === $cheapOptionProduct && ($criteria['productGroup'] ?? null) === $requiredGroup) {
                    return [$cheapFeedstock];
                }

                if (($criteria['product'] ?? null) === $expensiveOptionProduct && ($criteria['productGroup'] ?? null) === $requiredGroup) {
                    return [$expensiveFeedstock];
                }

                if (($criteria['product'] ?? null) === $optionalOptionProduct && ($criteria['productGroup'] ?? null) === $optionalGroup) {
                    return [$optionalFeedstock];
                }

                return [];
            }
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [Product::class, $productRepository],
            [ProductGroup::class, $groupRepository],
            [ProductGroupProduct::class, $groupProductRepository],
        ]);

        $resolver = new ProductPricingCollectionSummaryResolver($entityManager);
        $summary = $resolver->resolve(
            new GetCollection(),
            ProductGroupProduct::class,
            ['name' => 'pricing'],
            $this->createMock(QueryBuilder::class),
            [],
            ['filters' => ['product' => '/products/1343']]
        );

        self::assertSame(4.0, $summary['directCost']);
        self::assertSame(10.5, $summary['totalCost']);
        self::assertSame(6.5, $summary['groups'][0]['cost']);
        self::assertSame('Carne Fraldinha', $summary['groups'][0]['cheapestOption']['productChild']['product']);
        self::assertSame(['2', '3', '4'], array_map('strval', array_keys($summary['groupItemCosts'])));
        self::assertTrue($summary['groupItemCosts']['2']['hasFeedstocks']);
        self::assertSame(2.1, $summary['groupItemCosts']['4']['cost']);
    }

    private function mockProduct(int $id, string $name, string $type): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getProduct')->willReturn($name);
        $product->method('getType')->willReturn($type);
        $product->method('isActive')->willReturn(true);

        return $product;
    }

    private function mockGroup(int $id, string $name, bool $required, int $order): ProductGroup
    {
        $group = $this->createMock(ProductGroup::class);
        $group->method('getId')->willReturn($id);
        $group->method('getProductGroup')->willReturn($name);
        $group->method('getRequired')->willReturn($required);
        $group->method('getGroupOrder')->willReturn($order);

        return $group;
    }

    private function mockGroupProduct(
        int $id,
        Product $product,
        ?ProductGroup $group,
        string $type,
        Product $child,
        float $quantity,
        float $price
    ): ProductGroupProduct {
        $item = $this->createMock(ProductGroupProduct::class);
        $item->method('getId')->willReturn($id);
        $item->method('getProduct')->willReturn($product);
        $item->method('getProductGroup')->willReturn($group);
        $item->method('getProductType')->willReturn($type);
        $item->method('getProductChild')->willReturn($child);
        $item->method('getQuantity')->willReturn($quantity);
        $item->method('getPrice')->willReturn($price);
        $item->method('isActive')->willReturn(true);

        return $item;
    }
}
