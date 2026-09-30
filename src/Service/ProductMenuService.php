<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\File;
use ControleOnline\Entity\Model;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupProduct;
use ControleOnline\Repository\ModelRepository;
use ControleOnline\Repository\ProductCategoryRepository;
use ControleOnline\Repository\ProductGroupRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

class ProductMenuService
{
    public const CONFIG_MODEL = 'menu-catalog-model';
    public const CONFIG_HIDDEN_CATEGORY_IDS = 'menu-catalog-hidden-category-ids';
    public const CONFIG_HIDDEN_GROUP_IDS = 'menu-catalog-hidden-group-ids';

    private const CATEGORY_CONTEXT = 'products';
    private const MODEL_CONTEXT = 'menu';
    private const MAIN_PRODUCT_TYPES = ['manufactured', 'custom', 'product', 'service'];
    private const GROUP_MODIFIER_TYPES = ['component', 'package'];
    use ProductMenuImageTrait;
    use ProductMenuPresentationTrait;

    public function __construct(
        private ProductCategoryRepository $productCategoryRepository,
        private ProductGroupRepository $productGroupRepository,
        private ModelRepository $modelRepository,
        private ConfigService $configService,
        private PdfService $pdfService,
        private PeopleService $peopleService,
        private DomainService $domainService,
        private Environment $twig,
        private ProductPublicCatalogAccessService $catalogAccess,
    ) {}

    public function generateCatalogPdf(People $company, ?int $modelId = null): string
    {
        $this->resetPreparedAssets();

        try {
            $model = $this->resolveMenuModel($company, $modelId);
            $templateContent = $this->resolveMenuModelContent($model);
            $templateFeatures = $this->detectTemplateFeatures($templateContent);
            $catalog = $this->buildCatalog($company, $templateFeatures);

            $catalog['menuModel'] = $model;
            $catalog['menuModelName'] = trim((string) $model->getModel());

            $html = $this->renderMenuModel($templateContent, [
                ...$catalog,
                'catalog' => $catalog,
                'service' => $this,
            ]);

            return $this->pdfService->convertHtmlToPdf(
                $html,
                false,
                $this->assetDirectory !== null ? [$this->assetDirectory] : []
            );
        } finally {
            $this->cleanupPreparedAssets();
        }
    }

    public function buildCatalogFilename(People $company): string
    {
        $baseName = trim((string) ($company->getAlias() ?: $company->getName()));
        $slug = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $baseName));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '');
        $slug = trim((string) $slug, '-');

        return sprintf('cardapio-%s.pdf', $slug !== '' ? $slug : $company->getId());
    }

    public function buildCatalog(People $company, array $templateFeatures = []): array
    {
        $this->assertCompanyAccess($company);
        $this->catalogCompany = $company;
        $templateFeatures = $this->normalizeTemplateFeatures($templateFeatures);

        $hiddenCategoryIds = $this->normalizeIds(
            $this->configService->getConfig($company, self::CONFIG_HIDDEN_CATEGORY_IDS, true)
        );
        $hiddenGroupIds = $this->normalizeIds(
            $this->configService->getConfig($company, self::CONFIG_HIDDEN_GROUP_IDS, true)
        );

        $productCategories = $this->productCategoryRepository->findVisibleForMenuCatalog(
            $company,
            self::CATEGORY_CONTEXT,
            self::MAIN_PRODUCT_TYPES,
            $hiddenCategoryIds
        );

        $categoriesById = [];
        $customProducts = [];
        $heroImage = null;
        $includeCategoryImages = $templateFeatures['includeCategoryImages'];
        $includeProductImages = $templateFeatures['includeProductImages'];
        $includeHeroImage = $templateFeatures['includeHeroImage'];
        $includeGroups = $templateFeatures['includeGroups'];

        foreach ($productCategories as $productCategory) {
            $category = $productCategory->getCategory();
            $product = $productCategory->getProduct();
            $categoryId = $category->getId();

            if (!isset($categoriesById[$categoryId])) {
                $categoryImage = null;

                if ($includeCategoryImages) {
                    $categoryImage = $this->resolveImageSource($category->getCategoryFiles(), 'category');
                }

                if ($includeHeroImage && $heroImage === null) {
                    $heroImage = $this->resolveImageSource($category->getCategoryFiles(), 'hero')
                        ?? $categoryImage;
                }

                $categoriesById[$categoryId] = [
                    'id' => $categoryId,
                    'name' => trim((string) $category->getName()),
                    'image' => $includeCategoryImages ? $categoryImage : null,
                    'products' => [],
                ];
            }

            $productImage = null;

            if ($includeProductImages) {
                $productImage = $this->resolveImageSource($product->getProductFiles(), 'product');
            }

            if ($includeHeroImage && $heroImage === null) {
                $heroImage = $this->resolveImageSource($product->getProductFiles(), 'hero')
                    ?? $productImage;
            }

            $categoriesById[$categoryId]['products'][] = $this->buildProductCard(
                $product,
                $includeProductImages ? $productImage : null
            );

            if ($includeGroups && $product->getType() === 'custom') {
                $customProducts[$product->getId()] = $product;
            }
        }

        $groupsByProduct = $includeGroups
            ? $this->groupProductsByCustomProduct(
                $this->productGroupRepository->findVisibleComponentGroupsForMenuCatalog(
                    array_values($customProducts),
                    self::GROUP_MODIFIER_TYPES,
                    $hiddenGroupIds
                )
            )
            : [];

        foreach ($categoriesById as &$category) {
            foreach ($category['products'] as &$product) {
                if (!$includeGroups || $product['type'] !== 'custom') {
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

        $columns = $this->splitCategoriesInColumns($categories);

        return [
            'company' => $company,
            'companyName' => $this->resolveCompanyName($company),
            'generatedAt' => new \DateTimeImmutable(),
            'heroImage' => $includeHeroImage ? $heroImage : null,
            'columns' => $columns,
            'categoryCount' => count($categories),
            'productCount' => array_sum(array_map(
                fn(array $category): int => count($category['products']),
                $categories
            )),
            'hiddenCategoryCount' => count($hiddenCategoryIds),
            'hiddenGroupCount' => count($hiddenGroupIds),
            'menuModel' => null,
            'menuModelName' => null,
        ];
    }

    private function assertCompanyAccess(People $company): void
    {
        $this->catalogAccess->assertCatalog($company, 'shop');
    }

    private function resolveMenuModel(People $company, ?int $modelId = null): Model
    {
        $resolvedModelId = $modelId ?? $this->resolveConfiguredMenuModelId($company);

        if ($resolvedModelId === null) {
            throw new NotFoundHttpException(
                'Nenhum modelo de cardapio foi configurado nas configuracoes da empresa.'
            );
        }

        $model = $this->modelRepository->findCompanyContextModel(
            $company,
            self::MODEL_CONTEXT,
            $resolvedModelId
        );

        if (!$model instanceof Model) {
            throw new NotFoundHttpException(
                'O modelo de cardapio configurado para a empresa nao foi encontrado.'
            );
        }

        return $model;
    }

    private function resolveConfiguredMenuModelId(People $company): ?int
    {
        $configuredModel = $this->configService->getConfig($company, self::CONFIG_MODEL);
        $normalizedModelId = (int) preg_replace('/\D+/', '', (string) $configuredModel);

        return $normalizedModelId > 0 ? $normalizedModelId : null;
    }

    private function resolveMenuModelContent(Model $model): string
    {
        $file = $model->getFile();

        if (!$file instanceof File) {
            throw new NotFoundHttpException('O modelo de cardapio selecionado nao possui arquivo vinculado.');
        }

        $content = $file->getContent(true);
        if (trim($content) === '') {
            throw new NotFoundHttpException('O modelo de cardapio selecionado nao possui conteudo.');
        }

        return $content;
    }

    private function renderMenuModel(string $content, array $data = []): string
    {
        $template = $this->twig->createTemplate($content);

        return $template->render($data);
    }

    /**
     * @param ProductGroup[] $groups
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function groupProductsByCustomProduct(array $groups): array
    {
        $groupedProducts = [];

        foreach ($groups as $group) {
            $parentProductIds = $this->resolveGroupParentProductIds($group);
            if (empty($parentProductIds)) {
                continue;
            }

            $items = [];

            foreach ($group->getProducts() as $groupProduct) {
                if (!$groupProduct instanceof ProductGroupProduct) {
                    continue;
                }

                if (!$groupProduct->isActive() || !in_array($groupProduct->getProductType(), self::GROUP_MODIFIER_TYPES, true)) {
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

            foreach ($parentProductIds as $parentProductId) {
                $groupedProducts[$parentProductId][] = [
                    'id' => $group->getId(),
                    'name' => trim((string) $group->getProductGroup()),
                    'meta' => $this->buildGroupMeta($group),
                    'items' => $items,
                ];
            }
        }

        return $groupedProducts;
    }

    /**
     * @return array<int, int>
     */
    private function resolveGroupParentProductIds(ProductGroup $group): array
    {
        $parentProductIds = [];

        foreach ($group->getParentProducts() as $groupParent) {
            if (!$groupParent instanceof \ControleOnline\Entity\ProductGroupParent || !$groupParent->isActive()) {
                continue;
            }

            $parentProduct = $groupParent->getParentProduct();
            if ($parentProduct instanceof Product) {
                $parentProductIds[] = (int) $parentProduct->getId();
            }
        }

        return array_values(array_unique(array_filter($parentProductIds)));
    }

    /**
     * @return array<string, mixed>
     */

}
