<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\People;
use ControleOnline\Entity\ProductCategory;
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
            ->addSelect('category', 'product')
            ->join('productCategory.category', 'category')
            ->join('productCategory.product', 'product')
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
}
