<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
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

    /**
     * @param Product[] $products
     * @param int[] $hiddenGroupIds
     *
     * @return ProductGroup[]
     */
    public function findVisibleComponentGroupsForMenuCatalog(
        array $products,
        string $componentType,
        array $hiddenGroupIds = []
    ): array {
        if (empty($products)) {
            return [];
        }

        $qb = $this->createQueryBuilder('productGroup')
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
            ->setParameter('products', $products)
            ->setParameter('productType', $componentType)
            ->orderBy('productGroup.groupOrder', 'ASC')
            ->addOrderBy('productGroup.productGroup', 'ASC')
            ->addOrderBy('childProduct.product', 'ASC');

        if (!empty($hiddenGroupIds)) {
            $qb->andWhere('productGroup.id NOT IN (:hiddenGroupIds)')
                ->setParameter('hiddenGroupIds', $hiddenGroupIds);
        }

        return $qb->getQuery()->getResult();
    }
}
