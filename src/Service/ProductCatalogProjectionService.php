<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductCategory;
use ControleOnline\Entity\ProductFile;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupParent;
use ControleOnline\Entity\ProductGroupProduct;
use ControleOnline\Entity\ProductShowcase;
use ControleOnline\Repository\ProductCategoryRepository;
use ControleOnline\Repository\ProductGroupRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductCatalogProjectionService
{
    public function __construct(private EntityManagerInterface $manager) {}

    /**
     * @param Product[] $products
     *
     * @return array{products: array<int, array<string, mixed>>, categoryIds: int[]}
     */
    public function build(array $products, People $company, ?ProductShowcase $showcase): array
    {
        [$groupsByProduct, $allProducts] = $this->resolveCustomizationGraph($products, $company);
        $categoriesByProduct = $this->resolveCategoriesByProduct($allProducts, $company);
        $payloads = [];

        foreach ($products as $product) {
            $productId = (int) $product->getId();
            $payloads[$productId] = $this->buildProductPayload(
                $product,
                $groupsByProduct,
                $categoriesByProduct,
                $company,
                []
            );
        }

        return [
            'products' => $payloads,
            'categoryIds' => $this->resolveProjectedCategoryIds($company, $showcase),
        ];
    }

    /**
     * @param array<int, ProductGroup[]> $groupsByProduct
     * @param array<int, ProductCategory[]> $categoriesByProduct
     * @param array<int, true> $path
     *
     * @return array<string, mixed>
     */
    private function buildProductPayload(
        Product $product,
        array $groupsByProduct,
        array $categoriesByProduct,
        People $company,
        array $path
    ): array {
        $productId = (int) $product->getId();
        $productGroups = [];

        if ($productId > 0 && !isset($path[$productId])) {
            $path[$productId] = true;
            foreach ($groupsByProduct[$productId] ?? [] as $group) {
                $productGroups[] = $this->buildGroupPayload(
                    $group,
                    $groupsByProduct,
                    $categoriesByProduct,
                    $company,
                    $path
                );
            }
        }

        return [
            'id' => $productId,
            '@id' => '/products/' . $productId,
            'product' => $product->getProduct(),
            'description' => $product->getDescription(),
            'sku' => $product->getSku(),
            'type' => $product->getType(),
            'price' => $product->getPrice(),
            'active' => $product->isActive(),
            'featured' => $product->getFeatured(),
            'categoryIds' => $this->categoryIdsForProduct($categoriesByProduct[$productId] ?? []),
            'productCategories' => $this->buildProductCategoryPayloads(
                $categoriesByProduct[$productId] ?? []
            ),
            'productGroups' => $productGroups,
            'hasCustomizationGroups' => $productGroups !== [],
            'customizationGroupsLoaded' => true,
            'productFiles' => $this->buildProductFilesPayload($product),
        ];
    }

    /**
     * @param array<int, ProductGroup[]> $groupsByProduct
     * @param array<int, ProductCategory[]> $categoriesByProduct
     * @param array<int, true> $path
     *
     * @return array<string, mixed>
     */
    private function buildGroupPayload(
        ProductGroup $group,
        array $groupsByProduct,
        array $categoriesByProduct,
        People $company,
        array $path
    ): array {
        $items = array_values(array_filter(
            $group->getProducts()->toArray(),
            fn(mixed $item): bool => $item instanceof ProductGroupProduct
                && $item->isActive()
                && $item->getProductChild() instanceof Product
                && $item->getProductChild()->isActive()
                && $this->sameCompany($item->getProductChild()->getCompany(), $company)
        ));
        usort($items, fn(ProductGroupProduct $left, ProductGroupProduct $right): int =>
            $this->compareGroupProducts($left, $right));

        return [
            'id' => (int) $group->getId(),
            '@id' => '/product_groups/' . (int) $group->getId(),
            'productGroup' => $group->getProductGroup(),
            'priceCalculation' => $group->getPriceCalculation(),
            'required' => $group->isRequired(),
            'minimum' => $group->getMinimum(),
            'maximum' => $group->getMaximum(),
            'showInDisplay' => $group->isShowInDisplay(),
            'showInPrint' => $group->getShowInPrint(),
            'showUnitQuantity' => $group->getShowUnitQuantity(),
            'customizationType' => $group->getCustomizationType(),
            'groupOrder' => $group->getGroupOrder(),
            'products' => array_map(
                fn(ProductGroupProduct $item): array => [
                    'id' => (int) $item->getId(),
                    '@id' => '/product_group_products/' . (int) $item->getId(),
                    'sortOrder' => $item->getSortOrder(),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice(),
                    'productType' => $item->getProductType(),
                    'showInParentQueue' => $item->getShowInParentQueue(),
                    'product' => $this->buildProductPayload(
                        $item->getProductChild(),
                        $groupsByProduct,
                        $categoriesByProduct,
                        $company,
                        $path
                    ),
                ],
                $items
            ),
        ];
    }

    /**
     * @param Product[] $rootProducts
     *
     * @return array{0: array<int, ProductGroup[]>, 1: Product[]}
     */
    private function resolveCustomizationGraph(array $rootProducts, People $company): array
    {
        $repository = $this->manager->getRepository(ProductGroup::class);
        if (!$repository instanceof ProductGroupRepository) {
            return [[], $rootProducts];
        }

        $allProducts = $this->indexProducts($rootProducts);
        $pending = $allProducts;
        $visited = [];
        $groupsByProduct = [];

        while ($pending !== []) {
            $batch = [];
            foreach ($pending as $productId => $product) {
                if (!isset($visited[$productId])) {
                    $visited[$productId] = true;
                    $batch[] = $product;
                }
            }
            $pending = [];

            if ($batch === []) {
                break;
            }

            foreach ($repository->findActiveForParentProducts($batch, $company) as $group) {
                if (!$group instanceof ProductGroup || !$this->sameCompany($group->getCompany(), $company)) {
                    continue;
                }

                foreach ($group->getParentProducts() as $parentLink) {
                    if (!$parentLink instanceof ProductGroupParent || !$parentLink->isActive()) {
                        continue;
                    }

                    $parent = $parentLink->getParentProduct();
                    $parentId = $parent instanceof Product ? (int) $parent->getId() : 0;
                    if ($parentId > 0 && isset($allProducts[$parentId])) {
                        $groupsByProduct[$parentId][(int) $group->getId()] = $group;
                    }
                }

                foreach ($group->getProducts() as $groupProduct) {
                    $child = $groupProduct instanceof ProductGroupProduct
                        ? $groupProduct->getProductChild()
                        : null;
                    if (
                        !$groupProduct instanceof ProductGroupProduct
                        || !$groupProduct->isActive()
                        || !$child instanceof Product
                        || !$child->isActive()
                        || !$this->sameCompany($child->getCompany(), $company)
                    ) {
                        continue;
                    }

                    $childId = (int) $child->getId();
                    if ($childId > 0 && !isset($allProducts[$childId])) {
                        $allProducts[$childId] = $child;
                        $pending[$childId] = $child;
                    }
                }
            }
        }

        foreach ($groupsByProduct as &$groups) {
            $groups = array_values($groups);
            usort($groups, fn(ProductGroup $left, ProductGroup $right): int =>
                $this->compareGroups($left, $right));
        }

        return [$groupsByProduct, array_values($allProducts)];
    }

    /**
     * @param Product[] $products
     *
     * @return array<int, ProductCategory[]>
     */
    private function resolveCategoriesByProduct(array $products, People $company): array
    {
        $repository = $this->manager->getRepository(ProductCategory::class);
        if (!$repository instanceof ProductCategoryRepository) {
            return [];
        }

        $categoriesByProduct = [];
        foreach ($repository->findCatalogRelations($products, $company) as $relation) {
            if ($relation instanceof ProductCategory) {
                $categoriesByProduct[(int) $relation->getProduct()->getId()][] = $relation;
            }
        }

        return $categoriesByProduct;
    }

    /**
     * @return int[]
     */
    private function resolveProjectedCategoryIds(People $company, ?ProductShowcase $showcase): array
    {
        $repository = $this->manager->getRepository(ProductCategory::class);
        if (!$repository instanceof ProductCategoryRepository) {
            return [];
        }

        return $showcase instanceof ProductShowcase
            ? $repository->findPublishedCategoryIdsForShowcase($showcase)
            : $repository->findActiveCategoryIdsForCompany($company);
    }

    /**
     * @param ProductCategory[] $relations
     *
     * @return int[]
     */
    private function categoryIdsForProduct(array $relations): array
    {
        return array_values(array_unique(array_map(
            static fn(ProductCategory $relation): int => (int) $relation->getCategory()->getId(),
            $relations
        )));
    }

    /**
     * @param ProductCategory[] $relations
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildProductCategoryPayloads(array $relations): array
    {
        return array_map(static fn(ProductCategory $relation): array => [
            'id' => $relation->getId(),
            '@id' => '/product_categories/' . $relation->getId(),
            'sortOrder' => $relation->getSortOrder(),
            'category' => [
                'id' => (int) $relation->getCategory()->getId(),
                '@id' => '/categories/' . (int) $relation->getCategory()->getId(),
            ],
        ], $relations);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProductFilesPayload(Product $product): array
    {
        $files = [];
        foreach ($this->manager->getRepository(ProductFile::class)->findBy(['product' => $product]) as $productFile) {
            if (!$productFile instanceof ProductFile) {
                continue;
            }

            $file = $productFile->getFile();
            $files[] = [
                'id' => $productFile->getId(),
                '@id' => '/product_files/' . $productFile->getId(),
                'file' => [
                    'id' => $file->getId(),
                    '@id' => '/files/' . $file->getId(),
                    'fileType' => $file->getFileType(),
                    'fileName' => $file->getFileName(),
                    'extension' => $file->getExtension(),
                    'context' => $file->getContext(),
                    'public' => $file->isPublic(),
                ],
            ];
        }

        return $files;
    }

    /**
     * @param Product[] $products
     *
     * @return array<int, Product>
     */
    private function indexProducts(array $products): array
    {
        $indexed = [];
        foreach ($products as $product) {
            if ($product instanceof Product && (int) $product->getId() > 0) {
                $indexed[(int) $product->getId()] = $product;
            }
        }

        return $indexed;
    }

    private function compareGroups(ProductGroup $left, ProductGroup $right): int
    {
        return $this->compareValues([
            [(int) $left->getGroupOrder(), (int) $right->getGroupOrder()],
            [mb_strtolower($left->getProductGroup()), mb_strtolower($right->getProductGroup())],
            [(int) $left->getId(), (int) $right->getId()],
        ]);
    }

    private function compareGroupProducts(ProductGroupProduct $left, ProductGroupProduct $right): int
    {
        $leftOrder = $left->getSortOrder();
        $rightOrder = $right->getSortOrder();
        if ($leftOrder !== $rightOrder) {
            return $leftOrder === null ? 1 : ($rightOrder === null ? -1 : $leftOrder <=> $rightOrder);
        }

        return $this->compareValues([
            [mb_strtolower($left->getProductChild()?->getProduct() ?? ''), mb_strtolower($right->getProductChild()?->getProduct() ?? '')],
            [(int) $left->getProductChild()?->getId(), (int) $right->getProductChild()?->getId()],
            [(int) $left->getId(), (int) $right->getId()],
        ]);
    }

    /**
     * @param array<int, array{0: int|string, 1: int|string}> $pairs
     */
    private function compareValues(array $pairs): int
    {
        foreach ($pairs as [$left, $right]) {
            $comparison = $left <=> $right;
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    private function sameCompany(?People $left, People $right): bool
    {
        if (!$left instanceof People) {
            return false;
        }

        $leftId = (int) $left->getId();
        $rightId = (int) $right->getId();

        return $leftId > 0 && $rightId > 0 ? $leftId === $rightId : $left === $right;
    }
}
