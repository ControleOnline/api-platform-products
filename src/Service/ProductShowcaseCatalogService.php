<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\DeviceConfig;
use ControleOnline\Entity\Inventory;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleDomain;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductInventory;
use ControleOnline\Entity\ProductShowcase;
use ControleOnline\Entity\ProductShowcaseItem;
use ControleOnline\Repository\ProductShowcaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductShowcaseCatalogService
{
    public const POS_SHOWCASE_CONFIG_KEY = 'pos-product-showcase-id';
    public const GENERIC_SHOWCASE_CONFIG_KEY = 'product-showcase-id';

    public function __construct(
        private EntityManagerInterface $manager,
        private DeviceService $deviceService,
        private DomainService $domainService,
        private RequestStack $requestStack
    ) {}

    public function normalizeIntegrationKey(?string $integrationKey): string
    {
        return ProductShowcase::normalizeIntegrationKey($integrationKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCatalog(People $company, string $integrationKey, array $filters = []): array
    {
        $normalizedIntegrationKey = $this->normalizeIntegrationKey($integrationKey);
        $showcase = $this->resolveShowcase(
            $company,
            $normalizedIntegrationKey,
            $filters['device'] ?? null,
            $filters['deviceType'] ?? null
        );

        $page = max(1, (int) ($filters['page'] ?? 1));
        $itemsPerPage = max(1, min(100, (int) ($filters['itemsPerPage'] ?? 50)));

        // @agents Catalog source rule: use a configured showcase when it has usable items; an empty showcase falls back to active company products.
        if ($showcase instanceof ProductShowcase) {
            [$items, $totalItems] = $this->fetchShowcaseItems($showcase, $filters, $page, $itemsPerPage);

            if ($totalItems > 0 || $this->showcaseHasActiveCatalogItems($showcase)) {
                $customizationGroupFlags = $this->resolveCustomizationGroupFlags(
                    array_map(fn(ProductShowcaseItem $item): Product => $item->getProduct(), $items)
                );
                $members = array_map(
                    fn(ProductShowcaseItem $item): array => $this->buildShowcaseCatalogProduct($item, $customizationGroupFlags),
                    $items
                );

                return $this->buildCatalogPayload($members, $totalItems, $showcase, 'showcase');
            }
        }

        [$products, $totalItems] = $this->fetchFallbackProducts($company, $filters, $page, $itemsPerPage);
        $customizationGroupFlags = $this->resolveCustomizationGroupFlags($products);
        $members = array_map(
            fn(Product $product): array => $this->buildFallbackCatalogProduct($product, $customizationGroupFlags),
            $products
        );

        return $this->buildCatalogPayload($members, $totalItems, null, 'product');
    }

    private function buildCatalogPayload(
        array $members,
        int $totalItems,
        ?ProductShowcase $showcase,
        string $source
    ): array {
        return [
            '@id' => '/product-showcases/catalog',
            '@type' => 'Collection',
            'member' => $members,
            'totalItems' => $totalItems,
            'showcase' => $showcase instanceof ProductShowcase ? [
                'id' => $showcase->getId(),
                '@id' => '/product_showcases/' . $showcase->getId(),
                'name' => $showcase->getName(),
                'integrationKey' => $showcase->getIntegrationKey(),
            ] : null,
            'source' => $source,
        ];
    }

    public function resolveShowcaseForOrder(Order $order, Product $product, array $item = []): ?ProductShowcaseItem
    {
        $company = $order->getProvider();
        if (!$company instanceof People) {
            return null;
        }

        $integrationKey = $this->resolveOrderIntegrationKey($order);
        if ($integrationKey === '') {
            return null;
        }

        $showcase = $this->resolveShowcase(
            $company,
            $integrationKey,
            $item['device'] ?? null,
            $item['deviceType'] ?? null
        );

        if (!$showcase instanceof ProductShowcase) {
            return null;
        }

        $showcaseItem = $this->manager
            ->getRepository(ProductShowcaseItem::class)
            ->findActiveForProduct($showcase, $product);

        return $showcaseItem instanceof ProductShowcaseItem ? $showcaseItem : null;
    }

    public function assertShowcaseItemStock(ProductShowcaseItem $showcaseItem, float $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantidade inválida.');
        }

        $inventory = $showcaseItem->getOutInventory() ?: $showcaseItem->getProduct()->getDefaultOutInventory();
        if (!$inventory instanceof Inventory) {
            return;
        }

        $available = $this->resolveAvailableStock($showcaseItem->getProduct(), $inventory);

        if ($available < $quantity) {
            throw new \InvalidArgumentException(sprintf(
                'Estoque insuficiente para %s na vitrine %s.',
                $showcaseItem->getProduct()->getProduct(),
                $showcaseItem->getShowcase()->getName()
            ));
        }
    }

    public function resolveAvailableStock(Product $product, ?Inventory $inventory): float
    {
        if (!$inventory instanceof Inventory) {
            return 0.0;
        }

        $productInventory = $this->manager->getRepository(ProductInventory::class)->findOneBy([
            'product' => $product,
            'inventory' => $inventory,
        ]);

        if (!$productInventory instanceof ProductInventory) {
            return 0.0;
        }

        return max(
            0.0,
            (float) $productInventory->getAvailable()
            + (float) $productInventory->getPurchases()
            + (float) $productInventory->getTransit()
            - (float) $productInventory->getSales()
        );
    }

    private function resolveOrderIntegrationKey(Order $order): string
    {
        $app = $this->normalizeIntegrationKey($order->getApp());

        return match ($app) {
            'pos', 'shop', 'ifood', '99food' => $app,
            default => '',
        };
    }

    private function resolveShowcase(
        People $company,
        string $integrationKey,
        mixed $deviceReference = null,
        mixed $deviceType = null
    ): ?ProductShowcase {
        $normalizedIntegrationKey = $this->normalizeIntegrationKey($integrationKey);

        if ($normalizedIntegrationKey === 'pos') {
            $deviceShowcase = $this->resolveDeviceShowcase($company, $deviceReference, $deviceType);
            if ($deviceShowcase instanceof ProductShowcase) {
                return $deviceShowcase;
            }
        }

        $repository = $this->manager->getRepository(ProductShowcase::class);
        if (
            $normalizedIntegrationKey === 'shop'
            && $repository instanceof ProductShowcaseRepository
        ) {
            $domainShowcase = $this->resolveShopDomainShowcase($repository, $company);
            if ($domainShowcase instanceof ProductShowcase) {
                return $domainShowcase;
            }
        }

        return $repository->findDefaultActive($company, $normalizedIntegrationKey);
    }

    private function resolveShopDomainShowcase(ProductShowcaseRepository $repository, People $company): ?ProductShowcase
    {
        try {
            $peopleDomain = $this->domainService->getPeopleDomain();
        } catch (\Throwable) {
            return null;
        }

        if (
            !$peopleDomain instanceof PeopleDomain
            || strtoupper(trim((string) $peopleDomain->getDomainType())) !== 'SHOP'
        ) {
            return null;
        }

        return $repository->findActiveForPeopleDomain($company, 'shop', $peopleDomain);
    }

    private function resolveDeviceShowcase(
        People $company,
        mixed $deviceReference = null,
        mixed $deviceType = null
    ): ?ProductShowcase {
        $request = $this->requestStack->getCurrentRequest();

        $reference = trim((string) (
            $deviceReference
            ?? $request?->query->get('device')
            ?? $request?->headers->get('device')
            ?? ''
        ));

        if ($reference === '') {
            return null;
        }

        $device = $this->deviceService->resolveDeviceReference($reference);
        if (!$device) {
            return null;
        }

        $type = trim((string) (
            $deviceType
            ?? $request?->query->get('deviceType')
            ?? $request?->headers->get('device-type')
            ?? ''
        ));

        $configs = [];
        $deviceConfig = $this->deviceService->findDeviceConfig(
            $device,
            $company,
            $type !== '' ? $type : null
        );

        if ($deviceConfig instanceof DeviceConfig) {
            $configs[] = $deviceConfig->getConfigs(true);
        }

        foreach ($this->deviceService->findDeviceConfigs($device, $company) as $candidate) {
            if ($candidate instanceof DeviceConfig) {
                $configs[] = $candidate->getConfigs(true);
            }
        }

        foreach ($configs as $config) {
            $showcaseId = (int) preg_replace(
                '/\D+/',
                '',
                (string) ($config[self::POS_SHOWCASE_CONFIG_KEY] ?? $config[self::GENERIC_SHOWCASE_CONFIG_KEY] ?? '')
            );

            if ($showcaseId <= 0) {
                continue;
            }

            $showcase = $this->manager->getRepository(ProductShowcase::class)->findOneBy([
                'id' => $showcaseId,
                'company' => $company,
                'integrationKey' => 'pos',
                'active' => true,
            ]);

            if ($showcase instanceof ProductShowcase) {
                return $showcase;
            }
        }

        return null;
    }

    /**
     * @return array{0: ProductShowcaseItem[], 1: int}
     */
    private function fetchShowcaseItems(
        ProductShowcase $showcase,
        array $filters,
        int $page,
        int $itemsPerPage
    ): array {
        $qb = $this->manager->getRepository(ProductShowcaseItem::class)
            ->createQueryBuilder('item')
            ->addSelect('product', 'showcase', 'outInventory', 'productFile', 'productFileData')
            ->join('item.product', 'product')
            ->join('item.showcase', 'showcase')
            ->leftJoin('item.outInventory', 'outInventory')
            ->leftJoin('product.productFiles', 'productFile')
            ->leftJoin('productFile.file', 'productFileData')
            ->andWhere('item.showcase = :showcase')
            ->andWhere('item.active = true')
            ->andWhere('product.active = true')
            // @agents A showcase can only expose products owned by the same company as the showcase.
            ->andWhere('product.company = :showcaseCompany')
            ->setParameter('showcase', $showcase)
            ->setParameter('showcaseCompany', $showcase->getCompany());

        $this->applyProductFilters($qb, $filters, 'product');

        $countQb = clone $qb;
        $totalItems = (int) $countQb
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT item.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('product.product', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return [$items, $totalItems];
    }

    /**
     * @return array{0: Product[], 1: int}
     */
    private function fetchFallbackProducts(People $company, array $filters, int $page, int $itemsPerPage): array
    {
        $qb = $this->manager->getRepository(Product::class)
            ->createQueryBuilder('product')
            ->addSelect('productFile', 'productFileData', 'defaultOutInventory')
            ->leftJoin('product.productFiles', 'productFile')
            ->leftJoin('productFile.file', 'productFileData')
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
            ->orderBy('product.product', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return [$products, $totalItems];
    }

    private function showcaseHasActiveCatalogItems(ProductShowcase $showcase): bool
    {
        return $this->manager
            ->getRepository(ProductShowcaseItem::class)
            ->hasActiveCatalogItems($showcase);
    }

    private function applyProductFilters(QueryBuilder $qb, array $filters, string $productAlias): void
    {
        $types = $filters['type'] ?? ['custom', 'product', 'manufactured', 'service'];
        $types = is_array($types) ? array_filter($types) : [trim((string) $types)];
        if (!empty($types)) {
            $qb->andWhere(sprintf('%s.type IN (:showcaseProductTypes)', $productAlias))
                ->setParameter('showcaseProductTypes', array_values($types));
        }

        $search = trim((string) ($filters['search'] ?? $filters['product'] ?? ''));
        if ($search !== '') {
            $qb->andWhere(sprintf('%s.product LIKE :showcaseProductSearch', $productAlias))
                ->setParameter('showcaseProductSearch', '%' . $search . '%');
        }

        $productId = (int) preg_replace(
            '/\D+/',
            '',
            (string) ($filters['id'] ?? $filters['product.id'] ?? '')
        );
        if ($productId > 0) {
            $qb->andWhere(sprintf('%s.id = :showcaseProductId', $productAlias))
                ->setParameter('showcaseProductId', $productId);
        }

        $categoryId = (int) preg_replace(
            '/\D+/',
            '',
            (string) ($filters['category'] ?? $filters['productCategory.category'] ?? '')
        );
        if ($categoryId > 0) {
            $categoryIds = $this->resolveCategoryTreeIds($categoryId);
            $qb->join('ControleOnline\Entity\ProductCategory', 'showcaseProductCategory', 'WITH', sprintf('showcaseProductCategory.product = %s', $productAlias))
                ->andWhere('IDENTITY(showcaseProductCategory.category) IN (:showcaseCategoryIds)')
                ->setParameter('showcaseCategoryIds', $categoryIds);
        }

        $requiresProductFile = (string) (
            $filters['exists']['productFiles']
            ?? $filters['exists[productFiles]']
            ?? ''
        );
        if (in_array(strtolower($requiresProductFile), ['1', 'true', 'yes'], true)) {
            $qb->andWhere('productFile.id IS NOT NULL');
        }

        $fileType = trim((string) (
            $filters['productFiles']['file']['fileType']
            ?? $filters['productFiles.file.fileType']
            ?? ''
        ));
        if ($fileType !== '') {
            $qb->andWhere('productFileData.fileType = :showcaseProductFileType')
                ->setParameter('showcaseProductFileType', $fileType);
        }
    }

    /**
     * @return int[]
     */
    private function resolveCategoryTreeIds(int $categoryId): array
    {
        $connection = $this->manager->getConnection();
        $ids = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = $connection->fetchFirstColumn(
                'SELECT id FROM category WHERE parent_id IN (:parentIds)',
                ['parentIds' => $frontier],
                ['parentIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
            );

            $children = array_values(array_diff(
                array_map('intval', $children),
                $ids
            ));
            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return array_values(array_unique($ids));
    }

    private function buildShowcaseCatalogProduct(ProductShowcaseItem $item, array $customizationGroupFlags = []): array
    {
        $product = $item->getProduct();
        $outInventory = $item->getOutInventory();

        return array_merge($this->buildProductPayload(
            $product,
            !empty($customizationGroupFlags[(int) $product->getId()])
        ), [
            'price' => $item->getPrice(),
            'outInventory' => $outInventory instanceof Inventory ? [
                'id' => $outInventory->getId(),
                '@id' => '/inventories/' . $outInventory->getId(),
                'inventory' => $outInventory->getInventory(),
            ] : null,
            'showcaseItem' => [
                'id' => $item->getId(),
                '@id' => '/product_showcase_items/' . $item->getId(),
                'externalCode' => $item->getExternalCode(),
                'published' => $item->isPublished(),
                'showcase' => [
                    'id' => $item->getShowcase()->getId(),
                    'name' => $item->getShowcase()->getName(),
                    'integrationKey' => $item->getShowcase()->getIntegrationKey(),
                ],
            ],
            'stock' => [
                'available' => $outInventory instanceof Inventory
                    ? $this->resolveAvailableStock($product, $outInventory)
                    : null,
            ],
        ]);
    }

    private function buildFallbackCatalogProduct(Product $product, array $customizationGroupFlags = []): array
    {
        $outInventory = $product->getDefaultOutInventory();

        return array_merge($this->buildProductPayload(
            $product,
            !empty($customizationGroupFlags[(int) $product->getId()])
        ), [
            'outInventory' => $outInventory instanceof Inventory ? [
                'id' => $outInventory->getId(),
                '@id' => '/inventories/' . $outInventory->getId(),
                'inventory' => $outInventory->getInventory(),
            ] : null,
            'showcaseItem' => null,
            'stock' => [
                'available' => $outInventory instanceof Inventory
                    ? $this->resolveAvailableStock($product, $outInventory)
                    : null,
            ],
        ]);
    }

    private function buildProductPayload(Product $product, bool $hasCustomizationGroups = false): array
    {
        return [
            'id' => $product->getId(),
            '@id' => '/products/' . $product->getId(),
            'product' => $product->getProduct(),
            'description' => $product->getDescription(),
            'sku' => $product->getSku(),
            'type' => $product->getType(),
            'price' => $product->getPrice(),
            'active' => $product->isActive(),
            'featured' => $product->getFeatured(),
            'hasCustomizationGroups' => $hasCustomizationGroups,
            'customizationGroupsLoaded' => true,
            'productFiles' => $this->buildProductFilesPayload($product),
        ];
    }

    /**
     * @param Product[] $products
     *
     * @return array<int, bool>
     */
    private function resolveCustomizationGroupFlags(array $products): array
    {
        $productIds = array_values(array_unique(array_filter(array_map(
            static fn(Product $product): int => (int) $product->getId(),
            $products
        ))));

        if ($productIds === []) {
            return [];
        }

        $rows = $this->manager->getConnection()->fetchFirstColumn(
            <<<'SQL'
SELECT DISTINCT product_group_parent.parent_product_id
FROM product_group_parent
INNER JOIN product_group ON product_group.id = product_group_parent.product_group_id
WHERE product_group_parent.active = 1
  AND product_group.active = 1
  AND product_group_parent.parent_product_id IN (:productIds)
SQL,
            ['productIds' => $productIds],
            ['productIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        );

        $flags = [];
        foreach ($rows as $productId) {
            $flags[(int) $productId] = true;
        }

        return $flags;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProductFilesPayload(Product $product): array
    {
        $files = [];

        foreach ($product->getProductFiles() as $productFile) {
            $file = $productFile->getFile();
            $files[] = [
                'id' => $productFile->getId(),
                '@id' => '/product_files/' . $productFile->getId(),
                'file' => [
                    'id' => $file->getId(),
                    '@id' => '/files/' . $file->getId(),
                    'fileType' => $file->getFileType(),
                    'fileName' => $file->getFileName(),
                    'extension' => $file->getExtension(),
                    'context' => $file->getContext(),
                    'public' => $file->isPublic(),
                ],
            ];
        }

        return $files;
    }
}
