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
use Symfony\Component\HttpFoundation\RequestStack;

class ProductShowcaseCatalogService
{
    public const POS_SHOWCASE_CONFIG_KEY = 'pos-product-showcase-id';
    public const GENERIC_SHOWCASE_CONFIG_KEY = 'product-showcase-id';

    public function __construct(
        private EntityManagerInterface $manager,
        private DeviceService $deviceService,
        private DomainService $domainService,
        private RequestStack $requestStack,
        private ProductCatalogQueryService $catalogQuery,
        private ProductCatalogProjectionService $catalogProjection,
        private ProductCatalogCategoryTreeService $catalogCategoryTree
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

        if ($showcase instanceof ProductShowcase) {
            [$items, $totalItems] = $this->catalogQuery
                ->fetchShowcaseItems($showcase, $filters, $page, $itemsPerPage);
            $products = array_map(
                static fn(ProductShowcaseItem $item): Product => $item->getProduct(),
                $items
            );
            $projection = $this->catalogProjection->build($products, $company, $showcase);
            $members = array_map(
                fn(ProductShowcaseItem $item): array => $this->buildShowcaseCatalogProduct(
                    $item,
                    $projection['products'][(int) $item->getProduct()->getId()]
                ),
                $items
            );

            // A resolved showcase is authoritative, including when it publishes no items.
            return $this->buildCatalogPayload(
                $members,
                $totalItems,
                $showcase,
                'showcase',
                $company,
                $projection['categoryIds']
            );
        }

        [$products, $totalItems] = $this->catalogQuery
            ->fetchFallbackProducts($company, $filters, $page, $itemsPerPage);
        $projection = $this->catalogProjection->build($products, $company, null);
        $members = array_map(
            fn(Product $product): array => $this->buildFallbackCatalogProduct(
                $product,
                $projection['products'][(int) $product->getId()]
            ),
            $products
        );

        return $this->buildCatalogPayload(
            $members,
            $totalItems,
            null,
            'product',
            $company,
            $projection['categoryIds']
        );
    }

    private function buildCatalogPayload(
        array $members,
        int $totalItems,
        ?ProductShowcase $showcase,
        string $source,
        People $company,
        array $categoryIds
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
                'peopleDomain' => $showcase->getPeopleDomain() instanceof PeopleDomain ? [
                    'id' => $showcase->getPeopleDomain()->getId(),
                    '@id' => '/people_domains/' . $showcase->getPeopleDomain()->getId(),
                    'domain' => $showcase->getPeopleDomain()->getDomain(),
                ] : null,
                'settings' => $showcase->getSettings(),
            ] : null,
            'source' => $source,
            'legacyFallback' => !$showcase instanceof ProductShowcase,
            'categoryIds' => $categoryIds,
            'categoryProjection' => [
                'source' => $showcase instanceof ProductShowcase ? 'showcase' : 'legacy-product',
                'ids' => $categoryIds,
            ],
            'categories' => $this->catalogCategoryTree->build($company, $categoryIds),
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

        return $app;
    }

    private function resolveShowcase(
        People $company,
        string $integrationKey,
        mixed $deviceReference = null,
        mixed $deviceType = null
    ): ?ProductShowcase {
        $normalizedIntegrationKey = $this->normalizeIntegrationKey($integrationKey);

        if (in_array($normalizedIntegrationKey, ['pos', 'totem'], true)) {
            $deviceShowcase = $this->resolveDeviceShowcase(
                $company,
                $normalizedIntegrationKey,
                $deviceReference,
                $deviceType
            );
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
        string $integrationKey,
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
                'integrationKey' => $integrationKey,
                'active' => true,
            ]);

            if ($showcase instanceof ProductShowcase) {
                return $showcase;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $productPayload
     *
     * @return array<string, mixed>
     */
    private function buildShowcaseCatalogProduct(ProductShowcaseItem $item, array $productPayload): array
    {
        $product = $item->getProduct();
        $outInventory = $item->getOutInventory() ?: $product->getDefaultOutInventory();

        return array_merge($productPayload, [
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
                'sortOrder' => $item->getSortOrder(),
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

    /**
     * @param array<string, mixed> $productPayload
     *
     * @return array<string, mixed>
     */
    private function buildFallbackCatalogProduct(Product $product, array $productPayload): array
    {
        $outInventory = $product->getDefaultOutInventory();

        return array_merge($productPayload, [
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
}
