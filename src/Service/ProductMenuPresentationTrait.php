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

trait ProductMenuPresentationTrait
{
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
    }}
