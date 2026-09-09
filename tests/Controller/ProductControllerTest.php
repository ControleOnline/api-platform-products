<?php

namespace ControleOnline\Tests\Controller;

use ControleOnline\Controller\ProductController;
use ControleOnline\Entity\People;
use ControleOnline\Repository\OrderRepository;
use ControleOnline\Repository\ProductRepository;
use ControleOnline\Service\HydratorService;
use ControleOnline\Service\ProductCatalogNormalizedExportService;
use ControleOnline\Service\ProductMenuService;
use ControleOnline\Service\ProductService;
use ControleOnline\Service\ProductShowcaseCatalogService;
use ControleOnline\Service\RequestPayloadService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ProductControllerTest extends TestCase
{
    private function controller(
        ?ProductService $productService = null,
        ?ProductShowcaseCatalogService $catalogService = null,
        ?ProductMenuService $menuService = null,
        ?ProductCatalogNormalizedExportService $exportService = null,
        ?RequestPayloadService $payloadService = null,
    ): ProductController {
        return new ProductController(
            $productService ?? $this->createMock(ProductService::class),
            $menuService ?? $this->createMock(ProductMenuService::class),
            $exportService ?? $this->createMock(ProductCatalogNormalizedExportService::class),
            $catalogService ?? $this->createMock(ProductShowcaseCatalogService::class),
            $this->createMock(HydratorService::class),
            $payloadService ?? $this->createMock(RequestPayloadService::class),
            $this->createMock(ProductRepository::class),
            $this->createMock(OrderRepository::class),
        );
    }

    public function testProductShowcaseCatalogReadsCompanyAndIntegrationKeyFromQuery(): void
    {
        $company = new People();
        $productService = $this->createMock(ProductService::class);
        $productService->expects(self::once())
            ->method('resolveCompanyReference')
            ->with('1')
            ->willReturn($company);

        $catalogService = $this->createMock(ProductShowcaseCatalogService::class);
        $catalogService->expects(self::once())
            ->method('buildCatalog')
            ->with($company, 'shop', self::callback(static function (array $query): bool {
                return $query['company'] === '1'
                    && $query['integration_key'] === 'shop'
                    && $query['active'] === '1'
                    && $query['type'] === ['product', 'manufactured', 'custom', 'service']
                    && ($query['order']['product'] ?? null) === 'ASC'
                    && $query['page'] === '1'
                    && $query['itemsPerPage'] === '30';
            }))
            ->willReturn(['items' => []]);

        $response = $this->controller($productService, $catalogService)->getProductShowcaseCatalog(
            Request::create('/product-showcases/catalog', 'GET', [
                'company' => '1',
                'integration_key' => 'shop',
                'active' => '1',
                'type' => ['product', 'manufactured', 'custom', 'service'],
                'order' => ['product' => 'ASC'],
                'page' => '1',
                'itemsPerPage' => '30',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['items' => []], json_decode((string) $response->getContent(), true));
    }

    public function testPurchasingSuggestionReadsCompanyFromQuery(): void
    {
        $company = new People();
        $productService = $this->createMock(ProductService::class);
        $productService->expects(self::once())
            ->method('resolveCompanyReference')
            ->with('7')
            ->willReturn($company);
        $productService->expects(self::once())
            ->method('getPurchasingSuggestion')
            ->with($company)
            ->willReturn(['ok' => true]);

        $response = $this->controller($productService)->getPurchasingSuggestion(
            Request::create('/products/purchasing-suggestion', 'GET', ['company' => '7'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['ok' => true], json_decode((string) $response->getContent(), true));
    }

    public function testInventoryReadsCompanyFromQuery(): void
    {
        $company = new People();
        $productService = $this->createMock(ProductService::class);
        $productService->expects(self::once())
            ->method('resolveCompanyReference')
            ->with('3')
            ->willReturn($company);
        $productService->expects(self::once())
            ->method('getProductsInventory')
            ->with($company)
            ->willReturn(['stock' => []]);

        $response = $this->controller($productService)->getProductsInventory(
            Request::create('/products/inventory', 'GET', ['company' => '3'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['stock' => []], json_decode((string) $response->getContent(), true));
    }

    public function testMenuDownloadReadsCompanyAndModelFromQuery(): void
    {
        $company = new People();
        $productService = $this->createMock(ProductService::class);
        $productService->expects(self::once())
            ->method('resolveCompanyReference')
            ->with('1')
            ->willReturn($company);

        $payloadService = $this->createMock(RequestPayloadService::class);
        $payloadService->expects(self::once())
            ->method('normalizeOptionalNumericId')
            ->with('9')
            ->willReturn(9);

        $menuService = $this->createMock(ProductMenuService::class);
        $menuService->expects(self::once())
            ->method('generateCatalogPdf')
            ->with($company, 9)
            ->willReturn('%PDF-fake');
        $menuService->expects(self::once())
            ->method('buildCatalogFilename')
            ->with($company)
            ->willReturn('menu.pdf');

        $response = $this->controller($productService, null, $menuService, null, $payloadService)->downloadMenuCatalog(
            Request::create('/products/menu/download', 'GET', ['company' => '1', 'model' => '9'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('%PDF-fake', $response->getContent());
    }

    public function testNormalizedCatalogDownloadReadsCompanyAndContextFromQuery(): void
    {
        $company = new People();
        $productService = $this->createMock(ProductService::class);
        $productService->expects(self::once())
            ->method('resolveCompanyReference')
            ->with('1')
            ->willReturn($company);

        $exportService = $this->createMock(ProductCatalogNormalizedExportService::class);
        $exportService->expects(self::once())
            ->method('buildNormalizedCatalogCsv')
            ->with($company, 'shop')
            ->willReturn("sku,name\n");
        $exportService->expects(self::once())
            ->method('buildNormalizedCatalogFilename')
            ->with($company)
            ->willReturn('catalog.csv');

        $response = $this->controller($productService, null, null, $exportService)->downloadNormalizedCatalog(
            Request::create('/products/catalog/download-normalized', 'GET', [
                'company' => '1',
                'context' => 'shop',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame("sku,name\n", $response->getContent());
    }
}
