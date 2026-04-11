<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\File;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductCategory;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Twig\Environment;

class ProductMenuService
{
    public const CONFIG_HIDDEN_CATEGORY_IDS = 'menu-catalog-hidden-category-ids';
    public const CONFIG_HIDDEN_GROUP_IDS = 'menu-catalog-hidden-group-ids';

    private const CATEGORY_CONTEXT = 'products';
    private const MAIN_PRODUCT_TYPES = ['manufactured', 'custom', 'product', 'service'];
    private const GROUP_COMPONENT_TYPE = 'component';

    public function __construct(
        private EntityManagerInterface $manager,
        private ConfigService $configService,
        private PdfService $pdfService,
        private PeopleService $peopleService,
        private Environment $twig,
    ) {}

    public function generateCatalogPdf(People $company): string
    {
        $catalog = $this->buildCatalog($company);
        $html = $this->twig->render('products/menu_catalog.html.twig', $catalog);

        return $this->pdfService->convertHtmlToPdf($html);
    }

    public function buildCatalog(People $company): array
    {
        $this->assertCompanyAccess($company);

        $hiddenCategoryIds = $this->normalizeIds(
            $this->configService->getConfig($company, self::CONFIG_HIDDEN_CATEGORY_IDS, true)
        );
        $hiddenGroupIds = $this->normalizeIds(
            $this->configService->getConfig($company, self::CONFIG_HIDDEN_GROUP_IDS, true)
        );

        $productCategories = $this->getVisibleProductCategories($company, $hiddenCategoryIds);

        $categoriesById = [];
        $customProducts = [];

        foreach ($productCategories as $productCategory) {
            $category = $productCategory->getCategory();
            $product = $productCategory->getProduct();
            $categoryId = $category->getId();

            if (!isset($categoriesById[$categoryId])) {
                $categoriesById[$categoryId] = [
                    'id' => $categoryId,
                    'name' => trim((string) $category->getName()),
                    'image' => $this->resolveImageDataUri($category->getCategoryFiles()),
                    'products' => [],
                ];
            }

            $categoriesById[$categoryId]['products'][] = $this->buildProductCard($product);

            if ($product->getType() === 'custom') {
                $customProducts[$product->getId()] = $product;
            }
        }

        $groupsByProduct = $this->getProductGroups(array_values($customProducts), $hiddenGroupIds);

        foreach ($categoriesById as &$category) {
            foreach ($category['products'] as &$product) {
                if ($product['type'] !== 'custom') {
                    continue;
                }

                $product['groups'] = $groupsByProduct[$product['id']] ?? [];
            }

            $category['description'] = $this->buildCategoryDescription($category['products']);
        }
        unset($category, $product);

        $categories = array_values(array_filter(
            $categoriesById,
            fn(array $category): bool => !empty($category['products'])
        ));

        foreach ($categories as $index => &$category) {
            $category['position'] = $index + 1;
        }
        unset($category);

        $heroImage = $this->resolveHeroImage($categories);
        $columns = $this->splitCategoriesInColumns($categories);

        return [
            'company' => $company,
            'companyName' => $this->resolveCompanyName($company),
            'generatedAt' => new \DateTimeImmutable(),
            'heroImage' => $heroImage,
            'columns' => $columns,
            'categoryCount' => count($categories),
            'productCount' => array_sum(array_map(
                fn(array $category): int => count($category['products']),
                $categories
            )),
            'hiddenCategoryCount' => count($hiddenCategoryIds),
            'hiddenGroupCount' => count($hiddenGroupIds),
        ];
    }

    private function assertCompanyAccess(People $company): void
    {
        $currentPeople = $this->peopleService->getMyPeople();

        if (!$currentPeople instanceof People) {
            throw new AccessDeniedHttpException('Usuario nao autenticado.');
        }

        if ($currentPeople->getId() === $company->getId()) {
            return;
        }

        $companyIds = array_map(
            fn(People $myCompany): int => $myCompany->getId(),
            $this->peopleService->getMyCompanies()
        );

        if (!in_array($company->getId(), $companyIds, true)) {
            throw new AccessDeniedHttpException('Empresa nao autorizada.');
        }
    }

    /**
     * @return ProductCategory[]
     */
    private function getVisibleProductCategories(People $company, array $hiddenCategoryIds): array
    {
        $qb = $this->manager->getRepository(ProductCategory::class)
            ->createQueryBuilder('productCategory')
            ->addSelect('category', 'product')
            ->join('productCategory.category', 'category')
            ->join('productCategory.product', 'product')
            ->andWhere('category.company = :company')
            ->andWhere('category.context = :context')
            ->andWhere('product.company = :company')
            ->andWhere('product.active = true')
            ->andWhere('product.type IN (:types)')
            ->setParameter('company', $company)
            ->setParameter('context', self::CATEGORY_CONTEXT)
            ->setParameter('types', self::MAIN_PRODUCT_TYPES)
            ->orderBy('category.name', 'ASC')
            ->addOrderBy('product.featured', 'DESC')
            ->addOrderBy('product.product', 'ASC');

        if (!empty($hiddenCategoryIds)) {
            $qb->andWhere('category.id NOT IN (:hiddenCategoryIds)')
                ->setParameter('hiddenCategoryIds', $hiddenCategoryIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Product[] $customProducts
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function getProductGroups(array $customProducts, array $hiddenGroupIds): array
    {
        if (empty($customProducts)) {
            return [];
        }

        $qb = $this->manager->getRepository(ProductGroup::class)
            ->createQueryBuilder('productGroup')
            ->addSelect('groupProduct', 'childProduct')
            ->leftJoin(
                'productGroup.products',
                'groupProduct',
                'WITH',
                'groupProduct.active = true AND groupProduct.productType = :productType'
            )
            ->leftJoin('groupProduct.productChild', 'childProduct')
            ->andWhere('productGroup.parentProduct IN (:products)')
            ->andWhere('productGroup.active = true')
            ->setParameter('products', $customProducts)
            ->setParameter('productType', self::GROUP_COMPONENT_TYPE)
            ->orderBy('productGroup.groupOrder', 'ASC')
            ->addOrderBy('productGroup.productGroup', 'ASC')
            ->addOrderBy('childProduct.product', 'ASC');

        if (!empty($hiddenGroupIds)) {
            $qb->andWhere('productGroup.id NOT IN (:hiddenGroupIds)')
                ->setParameter('hiddenGroupIds', $hiddenGroupIds);
        }

        /** @var ProductGroup[] $groups */
        $groups = $qb->getQuery()->getResult();
        $groupedProducts = [];

        foreach ($groups as $group) {
            $items = [];

            foreach ($group->getProducts() as $groupProduct) {
                if (!$groupProduct instanceof ProductGroupProduct) {
                    continue;
                }

                if (!$groupProduct->isActive() || $groupProduct->getProductType() !== self::GROUP_COMPONENT_TYPE) {
                    continue;
                }

                $childProduct = $groupProduct->getProductChild();
                if (!$childProduct instanceof Product || !$childProduct->isActive()) {
                    continue;
                }

                $items[] = [
                    'id' => $childProduct->getId(),
                    'name' => trim((string) $childProduct->getProduct()),
                    'description' => $this->normalizeDescription($childProduct->getDescription()),
                    'priceLabel' => $groupProduct->getPrice() > 0
                        ? '+ ' . $this->formatMoney($groupProduct->getPrice())
                        : null,
                ];
            }

            if (empty($items)) {
                continue;
            }

            $groupedProducts[$group->getParentProduct()->getId()][] = [
                'id' => $group->getId(),
                'name' => trim((string) $group->getProductGroup()),
                'meta' => $this->buildGroupMeta($group),
                'items' => $items,
            ];
        }

        return $groupedProducts;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductCard(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'type' => $product->getType(),
            'name' => trim((string) $product->getProduct()),
            'description' => $this->normalizeDescription($product->getDescription()),
            'priceLabel' => $product->getPrice() > 0 ? $this->formatMoney($product->getPrice()) : null,
            'image' => $this->resolveImageDataUri($product->getProductFiles()),
            'groups' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function buildCategoryDescription(array $products): string
    {
        $productCount = count($products);
        $customizableCount = count(array_filter(
            $products,
            fn(array $product): bool => ($product['type'] ?? null) === 'custom'
        ));

        if ($productCount === 0) {
            return 'Nenhum item disponivel para esta secao.';
        }

        if ($customizableCount > 0) {
            return sprintf(
                '%d itens com leitura rapida e opcoes de customizacao quando aplicavel.',
                $productCount
            );
        }

        return sprintf(
            '%d itens organizados para compartilhamento rapido do cardapio.',
            $productCount
        );
    }

    private function buildGroupMeta(ProductGroup $group): string
    {
        $parts = [];

        if ($group->isRequired()) {
            $parts[] = 'obrigatorio';
        }

        if ($group->getMinimum() !== null && $group->getMaximum() !== null) {
            $parts[] = sprintf('escolha de %d a %d', $group->getMinimum(), $group->getMaximum());
        } elseif ($group->getMaximum() !== null) {
            $parts[] = sprintf('ate %d item(ns)', $group->getMaximum());
        } elseif ($group->getMinimum() !== null && $group->getMinimum() > 0) {
            $parts[] = sprintf('minimo de %d item(ns)', $group->getMinimum());
        }

        return implode(' • ', $parts);
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function splitCategoriesInColumns(array $categories): array
    {
        $columns = [[], []];
        $weights = [0, 0];

        foreach ($categories as $category) {
            $weight = 4;

            foreach ($category['products'] as $product) {
                $weight += 2;
                $weight += count($product['groups'] ?? []);
            }

            $columnIndex = $weights[0] <= $weights[1] ? 0 : 1;
            $columns[$columnIndex][] = $category;
            $weights[$columnIndex] += $weight;
        }

        return $columns;
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     */
    private function resolveHeroImage(array $categories): ?string
    {
        foreach ($categories as $category) {
            if (!empty($category['image'])) {
                return $category['image'];
            }

            foreach ($category['products'] as $product) {
                if (!empty($product['image'])) {
                    return $product['image'];
                }
            }
        }

        return null;
    }

    private function resolveCompanyName(People $company): string
    {
        $alias = trim((string) $company->getAlias());
        $name = trim((string) $company->getName());

        return $alias !== '' ? $alias : $name;
    }

    private function normalizeDescription(?string $description): ?string
    {
        $normalized = trim((string) $description);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param iterable<int, mixed> $relations
     */
    private function resolveImageDataUri(iterable $relations): ?string
    {
        foreach ($relations as $relation) {
            if (!method_exists($relation, 'getFile')) {
                continue;
            }

            $file = $relation->getFile();
            if (!$file instanceof File) {
                continue;
            }

            if (strtolower((string) $file->getFileType()) !== 'image') {
                continue;
            }

            return sprintf(
                'data:%s;base64,%s',
                $this->resolveMimeType($file),
                $file->getContent()
            );
        }

        return null;
    }

    private function resolveMimeType(File $file): string
    {
        $extension = strtolower((string) $file->getExtension());

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/' . ($extension !== '' ? $extension : 'png'),
        };
    }

    /**
     * @return int[]
     */
    private function normalizeIds(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        $values = is_array($value)
            ? $value
            : (preg_split('/[\r\n,;]+/', (string) $value) ?: []);

        $ids = [];

        foreach ($values as $item) {
            $normalized = (int) preg_replace('/\D+/', '', (string) $item);

            if ($normalized > 0) {
                $ids[$normalized] = $normalized;
            }
        }

        return array_values($ids);
    }

    private function formatMoney(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
