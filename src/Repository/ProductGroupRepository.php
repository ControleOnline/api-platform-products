<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\People;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductGroup>
 *
 * @method ProductGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductGroup[]    findAll()
 * @method ProductGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductGroup::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(ProductGroup $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(ProductGroup $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function findSharedByNameAndCompany(string $groupName, People $company): ?ProductGroup
    {
        $qb = $this->createQueryBuilder('productGroup');
        $qb->andWhere('productGroup.productGroup = :groupName')
            ->andWhere('productGroup.company = :company')
            ->setParameter('groupName', $groupName)
            ->setParameter('company', $company)
            ->orderBy('productGroup.id', 'ASC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return ProductGroup[]
     */
    public function findGroupsForProduct(Product $product, ?int $productGroupId = null, bool $requiredOnly = false): array
    {
        $qb = $this->createQueryBuilder('productGroup')
            ->distinct()
            ->leftJoin('productGroup.parentProducts', 'groupParent')
            ->leftJoin('groupParent.parentProduct', 'groupParentProduct')
            ->leftJoin(
                'productGroup.products',
                'groupProduct',
                'WITH',
                'groupProduct.active = true'
            )
            ->leftJoin('groupProduct.productChild', 'childProduct')
            ->addSelect('groupParent', 'groupParentProduct', 'groupProduct', 'childProduct')
            ->andWhere('productGroup.active = true');

        $qb->andWhere($qb->expr()->andX(
            'groupParent.active = true',
            'groupParentProduct = :product'
        ));

        if ($requiredOnly) {
            $qb->andWhere('productGroup.required = true');
        }

        if (null !== $productGroupId) {
            $qb->andWhere('productGroup.id = :productGroupId')
                ->setParameter('productGroupId', $productGroupId);
        }

        return $qb->setParameter('product', $product)
            ->orderBy('productGroup.groupOrder', 'ASC')
            ->addOrderBy('productGroup.productGroup', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Product[] $products
     *
     * @return ProductGroup[]
     */
    public function findActiveForParentProducts(array $products, People $company): array
    {
        if ($products === []) {
            return [];
        }

        return $this->createQueryBuilder('productGroup')
            ->distinct()
            ->addSelect(
                'groupParent',
                'parentProduct',
                'groupProduct',
                'childProduct',
                'CASE WHEN groupProduct.sortOrder IS NULL THEN 1 ELSE 0 END AS HIDDEN groupProductOrderNull'
            )
            ->join(
                'productGroup.parentProducts',
                'groupParent',
                'WITH',
                'groupParent.active = true'
            )
            ->join('groupParent.parentProduct', 'parentProduct')
            ->leftJoin(
                'productGroup.products',
                'groupProduct',
                'WITH',
                'groupProduct.active = true'
            )
            ->leftJoin(
                'groupProduct.productChild',
                'childProduct',
                'WITH',
                'childProduct.active = true AND childProduct.company = :catalogCompany'
            )
            ->andWhere('parentProduct IN (:catalogParents)')
            ->andWhere('parentProduct.company = :catalogCompany')
            ->andWhere('productGroup.company = :catalogCompany')
            ->andWhere('productGroup.active = true')
            ->setParameter('catalogParents', $products)
            ->setParameter('catalogCompany', $company)
            ->orderBy('parentProduct.id', 'ASC')
            ->addOrderBy('productGroup.groupOrder', 'ASC')
            ->addOrderBy('productGroup.productGroup', 'ASC')
            ->addOrderBy('productGroup.id', 'ASC')
            ->addOrderBy('groupProductOrderNull', 'ASC')
            ->addOrderBy('groupProduct.sortOrder', 'ASC')
            ->addOrderBy('childProduct.product', 'ASC')
            ->addOrderBy('childProduct.id', 'ASC')
            ->addOrderBy('groupProduct.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Product[] $products
     * @param int[] $hiddenGroupIds
     *
     * @return ProductGroup[]
     */
    public function findVisibleComponentGroupsForMenuCatalog(
        array $products,
        array $productTypes,
        array $hiddenGroupIds = []
    ): array {
        if (empty($products)) {
            return [];
        }

        $productIds = array_map(
            static fn(Product $product): int => (int) $product->getId(),
            $products
        );
        $qb = $this->createQueryBuilder('productGroup')
            ->addSelect('groupParent', 'groupParentProduct', 'groupProduct', 'childProduct')
            ->distinct()
            ->andWhere('productGroup.active = true')
            ->setParameter('productIds', $productIds)
            ->setParameter('productTypes', $productTypes)
            ->orderBy('productGroup.groupOrder', 'ASC')
            ->addOrderBy('productGroup.productGroup', 'ASC')
            ->addOrderBy('childProduct.product', 'ASC');

        $qb
            ->leftJoin(
                'productGroup.parentProducts',
                'groupParent',
                'WITH',
                'groupParent.active = true AND IDENTITY(groupParent.parentProduct) IN (:productIds)'
            )
            ->leftJoin('groupParent.parentProduct', 'groupParentProduct')
            ->leftJoin(
                'productGroup.products',
                'groupProduct',
                'WITH',
                'groupProduct.active = true AND groupProduct.productType IN (:productTypes)'
            )
            ->leftJoin('groupProduct.productChild', 'childProduct')
            ->andWhere('groupParentProduct.id IN (:productIds)');

        if (!empty($hiddenGroupIds)) {
            $qb->andWhere('productGroup.id NOT IN (:hiddenGroupIds)')
                ->setParameter('hiddenGroupIds', $hiddenGroupIds);
        }

        return $qb->getQuery()->getResult();
    }
}
