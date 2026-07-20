<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductShowcase;
use ControleOnline\Entity\ProductShowcaseItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProductShowcaseItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductShowcaseItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductShowcaseItem[]    findAll()
 * @method ProductShowcaseItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductShowcaseItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductShowcaseItem::class);
    }

    public function findActiveForProduct(ProductShowcase $showcase, Product $product): ?ProductShowcaseItem
    {
        return $this->findOneBy([
            'showcase' => $showcase,
            'product' => $product,
            'active' => true,
        ]);
    }

    /**
     * @return ProductShowcaseItem[]
     */
    public function findActiveForCompanyAndIntegration(People $company, string $integrationKey): array
    {
        return $this->createQueryBuilder('item')
            ->addSelect('showcase', 'product', 'outInventory')
            ->join('item.showcase', 'showcase')
            ->join('item.product', 'product')
            ->leftJoin('item.outInventory', 'outInventory')
            ->andWhere('showcase.company = :company')
            ->andWhere('showcase.integrationKey = :integrationKey')
            ->andWhere('showcase.active = true')
            ->andWhere('item.active = true')
            ->andWhere('product.active = true')
            ->setParameter('company', $company)
            ->setParameter('integrationKey', ProductShowcase::normalizeIntegrationKey($integrationKey))
            ->orderBy('product.product', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
