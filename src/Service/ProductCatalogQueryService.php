<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductCategory;
use ControleOnline\Entity\ProductShowcase;
use ControleOnline\Entity\ProductShowcaseItem;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class ProductCatalogQueryService
{
    public function __construct(private EntityManagerInterface $manager) {}

    /**
     * @return array{0: ProductShowcaseItem[], 1: int}
     */
    public function fetchShowcaseItems(
        ProductShowcase $showcase,
        array $filters,
        int $page,
        int $itemsPerPage
    ): array {
        $qb = $this->createShowcaseItemsQueryBuilder($showcase, $filters);

        $countQb = clone $qb;
        $totalItems = (int) $countQb
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT item.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $this->applyShowcaseOrdering($qb)
            ->groupBy('item.id')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return [$items, $totalItems];
    }

    private function createShowcaseItemsQueryBuilder(
        ProductShowcase $showcase,
        array $filters
    ): QueryBuilder {
        $qb = $this->manager->getRepository(ProductShowcaseItem::class)
            ->createQueryBuilder('item')
            ->addSelect(
                'product',
                'showcase',
                'outInventory',
                'CASE WHEN item.sortOrder IS NULL THEN 1 ELSE 0 END AS HIDDEN showcaseSortOrderNull'
            )
            ->join('item.product', 'product')
            ->join('item.showcase', 'showcase')
            ->leftJoin('item.outInventory', 'outInventory')
            ->andWhere('item.showcase = :showcase')
            ->andWhere('item.active = true')
            ->andWhere('item.published = true')
            ->andWhere('product.active = true')
            ->andWhere('product.company = :showcaseCompany')
            ->setParameter('showcase', $showcase)
            ->setParameter('showcaseCompany', $showcase->getCompany());

        $this->applyProductFilters($qb, $filters, 'product');

        return $qb;
    }

    private function applyShowcaseOrdering(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->orderBy('showcaseSortOrderNull', 'ASC')
            ->addOrderBy('item.sortOrder', 'ASC')
            ->addOrderBy('product.product', 'ASC')
            ->addOrderBy('product.id', 'ASC');
    }

    /**
     * @return array{0: Product[], 1: int}
     */
    public function fetchFallbackProducts(
        People $company,
        array $filters,
        int $page,
        int $itemsPerPage
    ): array {
        $qb = $this->manager->getRepository(Product::class)
            ->createQueryBuilder('product')
            ->addSelect('defaultOutInventory')
            ->leftJoin('product.defaultOutInventory', 'defaultOutInventory')
            ->andWhere('product.company = :company')
            ->andWhere('product.active = true')
            ->setParameter('company', $company);

        $this->applyProductFilters($qb, $filters, 'product');

        $countQb = clone $qb;
        $totalItems = (int) $countQb
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT product.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $products = $qb
            ->groupBy('product.id')
            ->orderBy('product.product', 'ASC')
            ->addOrderBy('product.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return [$products, $totalItems];
    }

    private function applyProductFilters(QueryBuilder $qb, array $filters, string $productAlias): void
    {
        $types = $filters['type'] ?? ['custom', 'product', 'manufactured', 'service'];
        $types = is_array($types) ? array_filter($types) : [trim((string) $types)];
        if ($types !== []) {
            $qb->andWhere(sprintf('%s.type IN (:showcaseProductTypes)', $productAlias))
                ->setParameter('showcaseProductTypes', array_values($types));
        }

        $search = trim((string) ($filters['search'] ?? $filters['product'] ?? ''));
        if ($search !== '') {
            $qb->andWhere(sprintf('%s.product LIKE :showcaseProductSearch', $productAlias))
                ->setParameter('showcaseProductSearch', '%' . $search . '%');
        }

        $productId = $this->normalizeNumericId($filters['id'] ?? $filters['product.id'] ?? null);
        if ($productId > 0) {
            $qb->andWhere(sprintf('%s.id = :showcaseProductId', $productAlias))
                ->setParameter('showcaseProductId', $productId);
        }

        $categoryId = $this->normalizeCategoryId($filters);
        if ($categoryId > 0) {
            $qb->join(
                ProductCategory::class,
                'showcaseProductCategory',
                'WITH',
                sprintf('showcaseProductCategory.product = %s', $productAlias)
            )
                ->andWhere('IDENTITY(showcaseProductCategory.category) IN (:showcaseCategoryIds)')
                ->setParameter('showcaseCategoryIds', $this->resolveCategoryTreeIds($categoryId));
        }

        $requiresProductFile = strtolower((string) (
            $filters['exists']['productFiles']
            ?? $filters['exists[productFiles]']
            ?? ''
        ));
        $fileType = trim((string) (
            $filters['productFiles']['file']['fileType']
            ?? $filters['productFiles.file.fileType']
            ?? ''
        ));
        $requiresFile = in_array($requiresProductFile, ['1', 'true', 'yes'], true);

        if ($requiresFile || $fileType !== '') {
            $qb->innerJoin(sprintf('%s.productFiles', $productAlias), 'showcaseProductFile')
                ->innerJoin('showcaseProductFile.file', 'showcaseProductFileData');
        }
        if ($requiresFile) {
            $qb->andWhere('showcaseProductFile.id IS NOT NULL');
        }
        if ($fileType !== '') {
            $qb->andWhere('showcaseProductFileData.fileType = :showcaseProductFileType')
                ->setParameter('showcaseProductFileType', $fileType);
        }
    }

    private function normalizeCategoryId(array $filters): int
    {
        $productCategoryFilter = $filters['productCategory'] ?? null;
        $value = $filters['category']
            ?? $filters['productCategory.category']
            ?? $filters['productCategory_category']
            ?? (is_array($productCategoryFilter) ? ($productCategoryFilter['category'] ?? null) : null);

        return $this->normalizeNumericId($value);
    }

    private function normalizeNumericId(mixed $value): int
    {
        return (int) preg_replace('/\D+/', '', (string) $value);
    }

    /**
     * @return int[]
     */
    private function resolveCategoryTreeIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = $this->manager->getConnection()->fetchFirstColumn(
                'SELECT id FROM category WHERE parent_id IN (:parentIds)',
                ['parentIds' => $frontier],
                ['parentIds' => ArrayParameterType::INTEGER]
            );
            $children = array_values(array_diff(array_map('intval', $children), $ids));

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return array_values(array_unique($ids));
    }
}
