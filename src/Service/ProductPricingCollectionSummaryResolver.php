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
        $groupRepository = $this->manager->getRepository(ProductGroup::class);
        $groupItems = $groupRepository->findGroupsForProduct($mainProduct, $productGroupId, false);
        $groupItemCosts = $this->buildGroupItemCosts($groupItems);

        $requiredGroups = $groupRepository->findGroupsForProduct($mainProduct, $productGroupId, true);
        $groupsSummary = $this->buildRequiredGroupsSummary($requiredGroups, $groupItemCosts);

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
     * @param ProductGroup[] $groups
     */
    private function buildGroupItemCosts(array $groups): array
    {
        $costs = [];
        $feedstocksByGroupAndProduct = [];

        foreach ($groups as $group) {
            foreach ($this->resolveGroupItems($group) as $groupItem) {
                $groupEntity = $groupItem->getProductGroup();
                $child = $groupItem->getProductChild();
                $itemId = $this->normalizeId($groupItem);

                if (!$groupEntity instanceof ProductGroup || !$child instanceof Product || !$itemId) {
                    continue;
                }

                $groupId = $groupEntity->getId();
                $childId = $child->getId();
                $cacheKey = sprintf('%s:%s', $groupId, $childId);

                if (!array_key_exists($cacheKey, $feedstocksByGroupAndProduct)) {
                    $feedstocksByGroupAndProduct[$cacheKey] = $this->findFeedstocksForProduct($child, $groupEntity);
                }

                $feedstocks = $feedstocksByGroupAndProduct[$cacheKey];
                $costs[(string) $itemId] = [
                    'itemId' => (string) $itemId,
                    'cost' => $this->sumFeedstockPrices($feedstocks),
                    'hasFeedstocks' => [] !== $feedstocks,
                ];
            }
        }

        return $costs;
    }

    /**
     * @param ProductGroup[] $requiredGroups
     */
    private function buildRequiredGroupsSummary(array $requiredGroups, array $groupItemCosts): array
    {
        $summary = [];

        foreach ($requiredGroups as $group) {
            $selected = null;
            $selectedFeedstocks = [];

            foreach ($this->resolveGroupItems($group) as $item) {
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

    /**
     * @return ProductGroupProduct[]
     */
    private function resolveGroupItems(ProductGroup $group): array
    {
        $items = [];

        foreach ($group->getProducts() as $groupProduct) {
            if (!$groupProduct instanceof ProductGroupProduct) {
                continue;
            }

            if (!$groupProduct->isActive() || $groupProduct->getProductType() === 'feedstock') {
                continue;
            }

            $childProduct = $groupProduct->getProductChild();
            if (!$childProduct instanceof Product || !$childProduct->isActive()) {
                continue;
            }

            $items[] = $groupProduct;
        }

        usort($items, static function (ProductGroupProduct $left, ProductGroupProduct $right): int {
            return ($left->getId() ?? 0) <=> ($right->getId() ?? 0);
        });

        return $items;
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
