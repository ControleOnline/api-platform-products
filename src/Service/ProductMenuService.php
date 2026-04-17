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
    public const CONFIG_HIDDEN_CATEGORY_IDS = 'menu-catalog-hidden-category-ids';
    public const CONFIG_HIDDEN_GROUP_IDS = 'menu-catalog-hidden-group-ids';

    private const CATEGORY_CONTEXT = 'products';
    private const MODEL_CONTEXT = 'menu';
    private const MAIN_PRODUCT_TYPES = ['manufactured', 'custom', 'product', 'service'];
    private const GROUP_COMPONENT_TYPE = 'component';

    public function __construct(
        private ProductCategoryRepository $productCategoryRepository,
        private ProductGroupRepository $productGroupRepository,
        private ModelRepository $modelRepository,
        private ConfigService $configService,
        private PdfService $pdfService,
        private PeopleService $peopleService,
        private Environment $twig,
    ) {}

    public function generateCatalogPdf(People $company, ?int $modelId = null): string
    {
        $catalog = $this->buildCatalog($company);
        $model = $this->resolveMenuModel($company, $modelId);

        $catalog['menuModel'] = $model;
        $catalog['menuModelName'] = trim((string) $model->getModel());

        $html = $this->renderMenuModel($model, [
            ...$catalog,
            'catalog' => $catalog,
            'service' => $this,
        ]);

        return $this->pdfService->convertHtmlToPdf($html);
    }

    public function buildCatalogFilename(People $company): string
    {
        $baseName = trim((string) ($company->getAlias() ?: $company->getName()));
        $slug = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $baseName));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '');
        $slug = trim((string) $slug, '-');

        return sprintf('cardapio-%s.pdf', $slug !== '' ? $slug : $company->getId());
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

        $productCategories = $this->productCategoryRepository->findVisibleForMenuCatalog(
            $company,
            self::CATEGORY_CONTEXT,
            self::MAIN_PRODUCT_TYPES,
            $hiddenCategoryIds
        );

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

        $groupsByProduct = $this->groupProductsByCustomProduct(
            $this->productGroupRepository->findVisibleComponentGroupsForMenuCatalog(
                array_values($customProducts),
                self::GROUP_COMPONENT_TYPE,
                $hiddenGroupIds
            )
        );

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
            'menuModel' => null,
            'menuModelName' => null,
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

    private function resolveMenuModel(People $company, ?int $modelId = null): Model
    {
        $model = $this->modelRepository->findCompanyContextModel(
            $company,
            self::MODEL_CONTEXT,
            $modelId
        );

        if (!$model instanceof Model) {
            throw new NotFoundHttpException(
                'Nenhum modelo de cardapio com contexto menu foi encontrado para a empresa.'
            );
        }

        return $model;
    }

    private function renderMenuModel(Model $model, array $data = []): string
    {
        $file = $model->getFile();

        if (!$file instanceof File) {
            throw new NotFoundHttpException('O modelo de cardapio selecionado nao possui arquivo vinculado.');
        }

        $content = $file->getContent(true);
        if (trim($content) === '') {
            throw new NotFoundHttpException('O modelo de cardapio selecionado nao possui conteudo.');
        }

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
