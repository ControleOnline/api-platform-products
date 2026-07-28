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

    public function hasActiveCatalogItems(ProductShowcase $showcase): bool
    {
        $totalItems = (int) $this->createQueryBuilder('item')
            ->select('COUNT(item.id)')
            ->join('item.product', 'product')
            ->andWhere('item.showcase = :showcase')
            ->andWhere('item.active = true')
            ->andWhere('product.active = true')
            // @agents Showcase catalog items must stay inside the showcase company boundary; product_id alone is not authorization.
            ->andWhere('product.company = :company')
            ->setParameter('showcase', $showcase)
            ->setParameter('company', $showcase->getCompany())
            ->getQuery()
            ->getSingleScalarResult();

        return $totalItems > 0;
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
            // @agents Showcase exports must never leak products from another company linked by a raw product_id.
            ->andWhere('product.company = showcase.company')
            ->setParameter('company', $company)
            ->setParameter('integrationKey', ProductShowcase::normalizeIntegrationKey($integrationKey))
            ->orderBy('product.product', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
