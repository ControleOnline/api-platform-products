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
    private const GROUP_COMPONENT_TYPE = 'component';
    private const IMAGE_VARIANTS = [
        'hero' => ['maxWidth' => 1400, 'maxHeight' => 900, 'maxBytes' => 800000, 'quality' => 82],
        'category' => ['maxWidth' => 900, 'maxHeight' => 520, 'maxBytes' => 500000, 'quality' => 80],
        'product' => ['maxWidth' => 220, 'maxHeight' => 220, 'maxBytes' => 180000, 'quality' => 76],
        'default' => ['maxWidth' => 900, 'maxHeight' => 900, 'maxBytes' => 500000, 'quality' => 80],
    ];

    /**
     * @var array<string, string|null>
     */
    private array $imageSourceCache = [];
    private ?string $assetDirectory = null;

    public function __construct(
        private ProductCategoryRepository $productCategoryRepository,
        private ProductGroupRepository $productGroupRepository,
        private ModelRepository $modelRepository,
        private ConfigService $configService,
        private PdfService $pdfService,
        private PeopleService $peopleService,
        private DomainService $domainService,
        private Environment $twig,
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
                    self::GROUP_COMPONENT_TYPE,
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
        $currentPeople = $this->peopleService->getMyPeople();

        if ($currentPeople instanceof People) {
            if ($currentPeople->getId() === $company->getId()) {
                return;
            }

            $companyIds = array_map(
                fn(People $myCompany): int => $myCompany->getId(),
                $this->peopleService->getMyCompanies()
            );

            if (in_array($company->getId(), $companyIds, true)) {
                return;
            }
        }

        try {
            $domainCompany = $this->domainService->getPeopleDomain()->getPeople();
        } catch (\Throwable) {
            throw new AccessDeniedHttpException('Empresa nao autorizada.');
        }

        if (!$domainCompany instanceof People || $domainCompany->getId() !== $company->getId()) {
            throw new AccessDeniedHttpException('Empresa nao autorizada.');
        }
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
    private function buildProductCard(Product $product, ?string $image = null): array
    {
        return [
            'id' => $product->getId(),
            'type' => $product->getType(),
            'name' => trim((string) $product->getProduct()),
            'description' => $this->normalizeDescription($product->getDescription()),
            'priceLabel' => $product->getPrice() > 0 ? $this->formatMoney($product->getPrice()) : null,
            'image' => $image,
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
     * @return array{
     *   includeHeroImage: bool,
     *   includeCategoryImages: bool,
     *   includeProductImages: bool,
     *   includeGroups: bool
     * }
     */
    private function normalizeTemplateFeatures(array $templateFeatures): array
    {
        return [
            'includeHeroImage' => (bool) ($templateFeatures['includeHeroImage'] ?? true),
            'includeCategoryImages' => (bool) ($templateFeatures['includeCategoryImages'] ?? true),
            'includeProductImages' => (bool) ($templateFeatures['includeProductImages'] ?? true),
            'includeGroups' => (bool) ($templateFeatures['includeGroups'] ?? true),
        ];
    }

    /**
     * @return array{
     *   includeHeroImage: bool,
     *   includeCategoryImages: bool,
     *   includeProductImages: bool,
     *   includeGroups: bool
     * }
     */
    private function detectTemplateFeatures(string $content): array
    {
        return [
            'includeHeroImage' => $this->templateContains($content, 'heroImage'),
            'includeCategoryImages' => $this->templateContains($content, 'category.image'),
            'includeProductImages' => $this->templateContains($content, 'product.image'),
            'includeGroups' => $this->templateContains($content, 'product.groups')
                || preg_match('/\bgroup\./i', $content) === 1,
        ];
    }

    private function templateContains(string $content, string $needle): bool
    {
        return str_contains(strtolower($content), strtolower($needle));
    }

    /**
     * @param iterable<int, mixed> $relations
     */
    private function resolveImageSource(iterable $relations, string $variant = 'default'): ?string
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

            $cacheKey = sprintf('%s:%s', $file->getId(), $variant);

            if (array_key_exists($cacheKey, $this->imageSourceCache)) {
                return $this->imageSourceCache[$cacheKey];
            }

            return $this->imageSourceCache[$cacheKey] = $this->buildImageAssetSource($file, $variant);
        }

        return null;
    }

    private function buildImageAssetSource(File $file, string $variant): ?string
    {
        $content = $file->getContent(true);
        if ($content === '') {
            return null;
        }

        $extension = strtolower(trim((string) $file->getExtension()));
        if ($extension === '') {
            return null;
        }

        if ($extension === 'svg') {
            $path = $this->writeTemporaryAsset($content, 'svg');

            return $path !== null ? 'file://' . $path : null;
        }

        $variantConfig = self::IMAGE_VARIANTS[$variant] ?? self::IMAGE_VARIANTS['default'];
        $imageInfo = function_exists('getimagesizefromstring') ? @getimagesizefromstring($content) : false;
        if (
            is_array($imageInfo)
            && isset($imageInfo[0], $imageInfo[1])
            && (int) $imageInfo[0] > 0
            && (int) $imageInfo[1] > 0
            && (int) $imageInfo[0] <= $variantConfig['maxWidth']
            && (int) $imageInfo[1] <= $variantConfig['maxHeight']
            && strlen($content) <= $variantConfig['maxBytes']
        ) {
            $path = $this->writeTemporaryAsset($content, $extension);

            return $path !== null ? 'file://' . $path : null;
        }

        if (!function_exists('imagecreatefromstring')) {
            $path = $this->writeTemporaryAsset($content, $extension);

            return $path !== null ? 'file://' . $path : null;
        }

        $image = @imagecreatefromstring($content);
        if (!$image instanceof \GdImage) {
            $path = $this->writeTemporaryAsset($content, $extension);

            return $path !== null ? 'file://' . $path : null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            $path = $this->writeTemporaryAsset($content, $extension);

            return $path !== null ? 'file://' . $path : null;
        }

        $scale = min(
            1,
            $variantConfig['maxWidth'] / max(1, $width),
            $variantConfig['maxHeight'] / max(1, $height)
        );
        $targetWidth = (int) max(1, floor($width * $scale));
        $targetHeight = (int) max(1, floor($height * $scale));

        $normalized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($normalized, true);
        imagesavealpha($normalized, false);
        $white = imagecolorallocate($normalized, 255, 255, 255);
        imagefilledrectangle($normalized, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($normalized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        $path = $this->createTemporaryAssetPath('jpg');
        if ($path === null) {
            imagedestroy($normalized);
            return null;
        }

        $quality = (int) $variantConfig['quality'];
        $saved = false;

        while ($quality >= 58) {
            $saved = imagejpeg($normalized, $path, $quality);
            if (
                $saved
                && is_file($path)
                && filesize($path) !== false
                && filesize($path) <= $variantConfig['maxBytes']
            ) {
                break;
            }

            $quality -= 6;
        }

        imagedestroy($normalized);

        if (!$saved || !is_file($path)) {
            @unlink($path);

            return null;
        }

        if (filesize($path) !== false && filesize($path) > $variantConfig['maxBytes']) {
            @unlink($path);

            return null;
        }

        return 'file://' . $path;
    }

    private function prepareAssetDirectory(): ?string
    {
        if ($this->assetDirectory !== null) {
            return $this->assetDirectory;
        }

        $path = tempnam(sys_get_temp_dir(), 'menu_pdf_');
        if ($path === false) {
            return null;
        }

        if (is_file($path)) {
            @unlink($path);
        }

        if (!@mkdir($path, 0700, true) && !is_dir($path)) {
            return null;
        }

        $this->assetDirectory = $path;

        return $this->assetDirectory;
    }

    private function createTemporaryAssetPath(string $extension): ?string
    {
        $directory = $this->prepareAssetDirectory();
        if ($directory === null) {
            return null;
        }

        return sprintf(
            '%s/%s.%s',
            rtrim($directory, '/'),
            bin2hex(random_bytes(12)),
            ltrim(strtolower($extension), '.')
        );
    }

    private function writeTemporaryAsset(string $content, string $extension): ?string
    {
        $path = $this->createTemporaryAssetPath($extension);
        if ($path === null) {
            return null;
        }

        $bytes = @file_put_contents($path, $content);
        if ($bytes === false) {
            @unlink($path);

            return null;
        }

        return $path;
    }

    private function resetPreparedAssets(): void
    {
        $this->imageSourceCache = [];
        $this->assetDirectory = null;
    }

    private function cleanupPreparedAssets(): void
    {
        $assetDirectory = $this->assetDirectory;

        $this->imageSourceCache = [];
        $this->assetDirectory = null;

        if ($assetDirectory === null || !is_dir($assetDirectory)) {
            return;
        }

        $files = glob(rtrim($assetDirectory, '/') . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        @rmdir($assetDirectory);
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
