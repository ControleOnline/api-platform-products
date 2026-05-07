<?php

namespace ControleOnline\Service;

use ApiPlatform\Metadata\Operation;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupProduct;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductPricingCollectionSummaryResolver implements CollectionSummaryResolverInterface
{
    public function __construct(
        private EntityManagerInterface $manager,
        private ?RequestStack $requestStack = null
    ) {}

    public function resolve(
        Operation $operation,
        string $resourceClass,
        array $summaryField,
        QueryBuilder $filteredIdsQueryBuilder,
        array $uriVariables = [],
        array $context = []
    ): mixed {
        if (ProductGroupProduct::class !== $resourceClass) {
            return null;
        }

        $productId = $this->normalizeId($this->resolveFilterValue('product', $context));
        if (!$productId) {
            return null;
        }

        $productGroupId = $this->normalizeId($this->resolveFilterValue('productGroup', $context));

        $mainProduct = $this->manager->getRepository(Product::class)->find($productId);
        if (!$mainProduct instanceof Product) {
            return null;
        }

        $directFeedstocks = $this->findFeedstocksForProduct($mainProduct, null);
        $groupItems = $this->findGroupItemsForProduct($mainProduct, $productGroupId);
        $groupItemCosts = $this->buildGroupItemCosts($groupItems);

        $requiredGroups = $this->findRequiredGroupsForProduct($mainProduct, $productGroupId);
        $groupsSummary = $this->buildRequiredGroupsSummary($requiredGroups, $groupItems, $groupItemCosts);

        return [
            'scope' => [
                'productId' => (string) $productId,
                'productGroupId' => $productGroupId ? (string) $productGroupId : null,
            ],
            'directCost' => $this->sumFeedstockPrices($directFeedstocks),
            'directFeedstocks' => array_map([$this, 'normalizeFeedstock'], $directFeedstocks),
            'groupItemCosts' => $groupItemCosts,
            'groups' => $groupsSummary,
            'totalCost' => $this->sumFeedstockPrices($directFeedstocks) + array_reduce(
                $groupsSummary,
                static fn(float $sum, array $group): float => $sum + (float) ($group['cost'] ?? 0),
                0.0
            ),
        ];
    }

    /**
     * @return ProductGroupProduct[]
     */
    private function findFeedstocksForProduct(Product $product, ?ProductGroup $group): array
    {
        $criteria = [
            'product' => $product,
            'productType' => 'feedstock',
            'active' => true,
        ];

        if ($group instanceof ProductGroup) {
            $criteria['productGroup'] = $group;
        } else {
            $criteria['productGroup'] = null;
        }

        return $this->manager->getRepository(ProductGroupProduct::class)->findBy(
            $criteria,
            ['id' => 'ASC']
        );
    }

    /**
     * @return ProductGroupProduct[]
     */
    private function findGroupItemsForProduct(Product $product, ?int $productGroupId = null): array
    {
        $items = $this->manager->getRepository(ProductGroupProduct::class)->findBy(
            [
                'product' => $product,
                'active' => true,
            ],
            ['id' => 'ASC']
        );

        $items = array_values(array_filter(
            $items,
            static fn(ProductGroupProduct $item): bool => 'feedstock' !== $item->getProductType()
        ));

        if ($productGroupId) {
            $items = array_values(array_filter(
                $items,
                static fn(ProductGroupProduct $item): bool => $productGroupId === $item->getProductGroup()?->getId()
            ));
        }

        usort($items, static function (ProductGroupProduct $left, ProductGroupProduct $right): int {
            $leftGroup = $left->getProductGroup();
            $rightGroup = $right->getProductGroup();

            $leftOrder = $leftGroup?->getGroupOrder() ?? 0;
            $rightOrder = $rightGroup?->getGroupOrder() ?? 0;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            $leftLabel = $leftGroup?->getProductGroup() ?? '';
            $rightLabel = $rightGroup?->getProductGroup() ?? '';
            if ($leftLabel !== $rightLabel) {
                return $leftLabel <=> $rightLabel;
            }

            return ($left->getId() ?? 0) <=> ($right->getId() ?? 0);
        });

        return $items;
    }

    /**
     * @return ProductGroup[]
     */
    private function findRequiredGroupsForProduct(Product $product, ?int $productGroupId = null): array
    {
        $criteria = [
            'parentProduct' => $product,
            'required' => true,
            'active' => true,
        ];

        if ($productGroupId) {
            $criteria['id'] = $productGroupId;
        }

        return $this->manager->getRepository(ProductGroup::class)->findBy(
            $criteria,
            ['groupOrder' => 'ASC', 'productGroup' => 'ASC']
        );
    }

    private function buildGroupItemCosts(array $groupItems): array
    {
        $costs = [];
        $feedstocksByGroupAndProduct = [];

        foreach ($groupItems as $groupItem) {
            $group = $groupItem->getProductGroup();
            $child = $groupItem->getProductChild();
            $itemId = $this->normalizeId($groupItem);

            if (!$group instanceof ProductGroup || !$child instanceof Product || !$itemId) {
                continue;
            }

            $groupId = $group->getId();
            $childId = $child->getId();
            $cacheKey = sprintf('%s:%s', $groupId, $childId);

            if (!array_key_exists($cacheKey, $feedstocksByGroupAndProduct)) {
                $feedstocksByGroupAndProduct[$cacheKey] = $this->findFeedstocksForProduct($child, $group);
            }

            $feedstocks = $feedstocksByGroupAndProduct[$cacheKey];
            $costs[(string) $itemId] = [
                'itemId' => (string) $itemId,
                'cost' => $this->sumFeedstockPrices($feedstocks),
                'hasFeedstocks' => [] !== $feedstocks,
            ];
        }

        return $costs;
    }

    private function buildRequiredGroupsSummary(array $requiredGroups, array $groupItems, array $groupItemCosts): array
    {
        $itemsByGroupId = [];

        foreach ($groupItems as $groupItem) {
            $groupId = $this->normalizeId($groupItem->getProductGroup());
            if (!$groupId) {
                continue;
            }

            $itemsByGroupId[$groupId][] = $groupItem;
        }

        $summary = [];

        foreach ($requiredGroups as $group) {
            $groupId = (string) $group->getId();
            $items = $itemsByGroupId[$groupId] ?? [];
            $selected = null;
            $selectedFeedstocks = [];

            foreach ($items as $item) {
                $itemId = $this->normalizeId($item);
                if (!$itemId) {
                    continue;
                }

                $costEntry = $groupItemCosts[(string) $itemId] ?? null;
                $itemCost = (float) ($costEntry['cost'] ?? 0);

                if (null !== $selected && $itemCost >= (float) $selected['cost']) {
                    continue;
                }

                $child = $item->getProductChild();
                $selected = [
                    'cost' => $itemCost,
                    'item' => $item,
                ];
                $selectedFeedstocks = $child instanceof Product
                    ? $this->findFeedstocksForProduct($child, $group)
                    : [];
            }

            $summary[] = [
                'group' => $this->normalizeGroup($group),
                'cost' => (float) ($selected['cost'] ?? 0),
                'cheapestOption' => isset($selected['item'])
                    ? $this->normalizeGroupItem($selected['item'])
                    : null,
                'cheapestOptionFeedstocks' => array_map([$this, 'normalizeFeedstock'], $selectedFeedstocks),
            ];
        }

        return $summary;
    }

    private function normalizeFeedstock(ProductGroupProduct $feedstock): array
    {
        return [
            'id' => (string) $feedstock->getId(),
            'price' => $feedstock->getPrice(),
            'quantity' => $feedstock->getQuantity(),
            'productType' => $feedstock->getProductType(),
            'productChild' => $this->normalizeProduct($feedstock->getProductChild()),
        ];
    }

    private function normalizeGroup(ProductGroup $group): array
    {
        return [
            'id' => (string) $group->getId(),
            '@id' => '/product_groups/' . $group->getId(),
            'productGroup' => $group->getProductGroup(),
            'required' => $group->getRequired(),
            'groupOrder' => $group->getGroupOrder(),
        ];
    }

    private function normalizeGroupItem(ProductGroupProduct $item): array
    {
        $group = $item->getProductGroup();

        return [
            'id' => (string) $item->getId(),
            '@id' => '/product_group_products/' . $item->getId(),
            'price' => $item->getPrice(),
            'quantity' => $item->getQuantity(),
            'productType' => $item->getProductType(),
            'productChild' => $this->normalizeProduct($item->getProductChild()),
            'productGroup' => $group instanceof ProductGroup ? $this->normalizeGroup($group) : null,
        ];
    }

    private function normalizeProduct(?Product $product): ?array
    {
        if (!$product instanceof Product) {
            return null;
        }

        return [
            'id' => (string) $product->getId(),
            '@id' => '/products/' . $product->getId(),
            'product' => $product->getProduct(),
            'type' => $product->getType(),
        ];
    }

    /**
     * @param ProductGroupProduct[] $feedstocks
     */
    private function sumFeedstockPrices(array $feedstocks): float
    {
        return array_reduce(
            $feedstocks,
            static fn(float $sum, ProductGroupProduct $feedstock): float => $sum + (float) $feedstock->getPrice(),
            0.0
        );
    }

    private function normalizeId(mixed $value): ?int
    {
        if ($value instanceof Product || $value instanceof ProductGroup || $value instanceof ProductGroupProduct) {
            return $value->getId();
        }

        $raw = is_scalar($value) ? (string) $value : '';
        $normalized = preg_replace('/\D+/', '', $raw);

        return '' !== (string) $normalized ? (int) $normalized : null;
    }

    private function resolveFilterValue(string $name, array $context): mixed
    {
        $filters = $context['filters'] ?? [];
        if (array_key_exists($name, $filters)) {
            return $filters[$name];
        }

        return $this->requestStack?->getCurrentRequest()?->query->all()[$name] ?? null;
    }
}
