<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductCategory;
use ControleOnline\Entity\ProductShowcase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProductCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductCategory[]    findAll()
 * @method ProductCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCategory::class);
    }

    /**
     * @param string[] $productTypes
     * @param int[] $hiddenCategoryIds
     *
     * @return ProductCategory[]
     */
    public function findVisibleForMenuCatalog(
        People $company,
        string $categoryContext,
        array $productTypes,
        array $hiddenCategoryIds = []
    ): array {
        $qb = $this->createQueryBuilder('productCategory')
            ->distinct()
            ->addSelect(
                'category',
                'product',
                'categoryFile',
                'categoryFileData',
                'productFile',
                'productFileData'
            )
            ->join('productCategory.category', 'category')
            ->join('productCategory.product', 'product')
            ->leftJoin('category.categoryFiles', 'categoryFile')
            ->leftJoin('categoryFile.file', 'categoryFileData')
            ->leftJoin('product.productFiles', 'productFile')
            ->leftJoin('productFile.file', 'productFileData')
            ->andWhere('category.company = :company')
            ->andWhere('category.context = :context')
            ->andWhere('product.company = :company')
            ->andWhere('product.active = true')
            ->andWhere('product.type IN (:types)')
            ->setParameter('company', $company)
            ->setParameter('context', $categoryContext)
            ->setParameter('types', $productTypes)
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
     * @param Product[] $products
     *
     * @return ProductCategory[]
     */
    public function findCatalogRelations(array $products, People $company): array
    {
        if ($products === []) {
            return [];
        }

        return $this->createQueryBuilder('productCategory')
            ->addSelect(
                'category',
                'product',
                'CASE WHEN productCategory.sortOrder IS NULL THEN 1 ELSE 0 END AS HIDDEN placementOrderNull',
                'CASE WHEN category.sortOrder IS NULL THEN 1 ELSE 0 END AS HIDDEN categoryOrderNull'
            )
            ->join('productCategory.category', 'category')
            ->join('productCategory.product', 'product')
            ->andWhere('product IN (:catalogProducts)')
            ->andWhere('product.company = :catalogCompany')
            ->andWhere('category.company = :catalogCompany')
            ->andWhere('category.context = :catalogContext')
            ->setParameter('catalogProducts', $products)
            ->setParameter('catalogCompany', $company)
            ->setParameter('catalogContext', 'products')
            ->orderBy('product.id', 'ASC')
            ->addOrderBy('placementOrderNull', 'ASC')
            ->addOrderBy('productCategory.sortOrder', 'ASC')
            ->addOrderBy('categoryOrderNull', 'ASC')
            ->addOrderBy('category.sortOrder', 'ASC')
            ->addOrderBy('category.name', 'ASC')
            ->addOrderBy('category.id', 'ASC')
            ->addOrderBy('productCategory.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int[]
     */
    public function findPublishedCategoryIdsForShowcase(ProductShowcase $showcase): array
    {
        $rows = $this->createQueryBuilder('productCategory')
            ->select(
                'DISTINCT category.id AS categoryId',
                'category.sortOrder AS categorySortOrder',
                'category.name AS categoryName'
            )
            ->addSelect('CASE WHEN category.sortOrder IS NULL THEN 1 ELSE 0 END AS HIDDEN categoryOrderNull')
            ->join('productCategory.category', 'category')
            ->join('productCategory.product', 'product')
            ->join(
                'ControleOnline\Entity\ProductShowcaseItem',
                'showcaseItem',
                'WITH',
                'showcaseItem.product = product'
            )
            ->andWhere('showcaseItem.showcase = :categoryShowcase')
            ->andWhere('showcaseItem.active = true')
            ->andWhere('showcaseItem.published = true')
            ->andWhere('product.active = true')
            ->andWhere('product.company = :categoryCompany')
            ->andWhere('category.company = :categoryCompany')
            ->andWhere('category.context = :categoryContext')
            ->setParameter('categoryShowcase', $showcase)
            ->setParameter('categoryCompany', $showcase->getCompany())
            ->setParameter('categoryContext', 'products')
            ->orderBy('categoryOrderNull', 'ASC')
            ->addOrderBy('category.sortOrder', 'ASC')
            ->addOrderBy('category.name', 'ASC')
            ->addOrderBy('category.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $this->normalizeCategoryIds($rows);
    }

    /**
     * @return int[]
     */
    public function findActiveCategoryIdsForCompany(People $company): array
    {
        $rows = $this->createQueryBuilder('productCategory')
            ->select(
                'DISTINCT category.id AS categoryId',
                'category.sortOrder AS categorySortOrder',
                'category.name AS categoryName'
            )
            ->addSelect('CASE WHEN category.sortOrder IS NULL THEN 1 ELSE 0 END AS HIDDEN categoryOrderNull')
            ->join('productCategory.category', 'category')
            ->join('productCategory.product', 'product')
            ->andWhere('product.active = true')
            ->andWhere('product.company = :categoryCompany')
            ->andWhere('category.company = :categoryCompany')
            ->andWhere('category.context = :categoryContext')
            ->setParameter('categoryCompany', $company)
            ->setParameter('categoryContext', 'products')
            ->orderBy('categoryOrderNull', 'ASC')
            ->addOrderBy('category.sortOrder', 'ASC')
            ->addOrderBy('category.name', 'ASC')
            ->addOrderBy('category.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $this->normalizeCategoryIds($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return int[]
     */
    private function normalizeCategoryIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row['categoryId'] ?? 0);
            if ($categoryId > 0) {
                $ids[$categoryId] = $categoryId;
            }
        }

        return array_values($ids);
    }
}
