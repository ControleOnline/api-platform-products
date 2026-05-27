<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductGroupProduct>
 *
 * @method ProductGroupProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductGroupProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductGroupProduct[]    findAll()
 * @method ProductGroupProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductGroupProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductGroupProduct::class);
    }

    public function findSharedGroupItem(
        ProductGroup $group,
        Product $productChild,
        ?string $productType = null,
        ?float $quantity = null
    ): ?ProductGroupProduct
    {
        $qb = $this->createQueryBuilder('groupProduct')
            ->andWhere('groupProduct.productGroup = :productGroup')
            ->andWhere('groupProduct.productChild = :productChild')
            ->setParameter('productGroup', $group)
            ->setParameter('productChild', $productChild)
            ->orderBy('groupProduct.id', 'ASC')
            ->setMaxResults(1);

        if ($productType !== null && trim($productType) !== '') {
            $qb->andWhere('groupProduct.productType = :productType')
                ->setParameter('productType', $productType);
        }

        if ($quantity !== null) {
            $qb->andWhere('groupProduct.quantity = :quantity')
                ->setParameter('quantity', $quantity);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findLinkedGroupItemForParent(
        Product $parentProduct,
        Product $productChild,
        ?string $productType = null,
        ?float $quantity = null
    ): ?ProductGroupProduct
    {
        $qb = $this->createQueryBuilder('groupProduct')
            ->innerJoin('groupProduct.productGroup', 'productGroup')
            ->leftJoin('productGroup.parentProducts', 'groupParent')
            ->andWhere('groupProduct.productChild = :productChild')
            ->setParameter('productChild', $productChild)
            ->orderBy('groupProduct.active', 'DESC')
            ->addOrderBy('groupProduct.id', 'ASC')
            ->setMaxResults(1);

        if ($productType !== null && trim($productType) !== '') {
            $qb->andWhere('groupProduct.productType = :productType')
                ->setParameter('productType', $productType);
        }

        if ($quantity !== null) {
            $qb->andWhere('groupProduct.quantity = :quantity')
                ->setParameter('quantity', $quantity);
        }

        $qb->andWhere($qb->expr()->andX(
            'groupParent.active = true',
            'groupParent.parentProduct = :parentProduct'
        ));

        $qb->setParameter('parentProduct', $parentProduct);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
