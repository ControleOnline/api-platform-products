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
                    && $query['type'] === ['product', 'manufactured'];
            }))
            ->willReturn(['items' => []]);

        $controller = new ProductController(
            $productService,
            $this->createMock(ProductMenuService::class),
            $this->createMock(ProductCatalogNormalizedExportService::class),
            $catalogService,
            $this->createMock(HydratorService::class),
            $this->createMock(RequestPayloadService::class),
            $this->createMock(ProductRepository::class),
            $this->createMock(OrderRepository::class),
        );

        $response = $controller->getProductShowcaseCatalog(Request::create('/product-showcases/catalog', 'GET', [
            'company' => '1',
            'integration_key' => 'shop',
            'active' => '1',
            'type' => ['product', 'manufactured'],
        ]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['items' => []], json_decode((string) $response->getContent(), true));
    }
}
