<?php
namespace ControleOnline\Tests\Security;
use ControleOnline\Controller\ProductController;
use ControleOnline\Entity\{People,Product};
use ControleOnline\Repository\{ProductRepository,OrderRepository};
use ControleOnline\Service\{ProductService,ProductMenuService,ProductCatalogNormalizedExportService,ProductShowcaseCatalogService,HydratorService,RequestPayloadService,ProductCatalogAccessService,ProductPublicCatalogAccessService};
use PHPUnit\Framework\{TestCase,Attributes\AllowMockObjectsWithoutExpectations};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AllowMockObjectsWithoutExpectations]
class ProductControllerAuthorizationTest extends TestCase
{
    private function controller(ProductCatalogAccessService $access, ProductPublicCatalogAccessService $public, ?ProductMenuService $menu=null, ?ProductCatalogNormalizedExportService $export=null): ProductController
    {
        $company=new People();$products=$this->createMock(ProductService::class);$products->method('resolveCompanyReference')->willReturn($company);
        $repository=$this->createMock(ProductRepository::class);$repository->method('find')->willReturn((new Product())->setCompany($company));
        return new ProductController($products,$menu??$this->createMock(ProductMenuService::class),$export??$this->createMock(ProductCatalogNormalizedExportService::class),$this->createMock(ProductShowcaseCatalogService::class),$this->createMock(HydratorService::class),$this->createMock(RequestPayloadService::class),$repository,$this->createMock(OrderRepository::class),$access,$public);
    }
    public function testSummaryCannotReadForeignProductThroughDirectRepository(): void
    {
        $access=$this->createMock(ProductCatalogAccessService::class);$access->method('assertReadCompany')->willThrowException(new AccessDeniedException());
        $this->expectException(AccessDeniedException::class);
        $this->controller($access,$this->createMock(ProductPublicCatalogAccessService::class))->getProductSummary(99,Request::create('/products/99/summary'));
    }
    public function testNormalizedExportReturnsForbiddenBeforePrivateCsvIsBuilt(): void
    {
        $access=$this->createMock(ProductCatalogAccessService::class);$access->method('assertReadCompany')->willThrowException(new AccessDeniedException());
        $export=$this->createMock(ProductCatalogNormalizedExportService::class);$export->expects(self::never())->method('buildNormalizedCatalogCsv');
        $response=$this->controller($access,$this->createMock(ProductPublicCatalogAccessService::class),export:$export)->downloadNormalizedCatalog(Request::create('/products/catalog/download-normalized','GET',['company'=>'99']));
        self::assertSame(403,$response->getStatusCode());
    }
    public function testPublicMenuRejectsUnpublishedCompanyBeforePdfRendering(): void
    {
        $public=$this->createMock(ProductPublicCatalogAccessService::class);$public->method('assertCatalog')->willThrowException(new AccessDeniedException());
        $menu=$this->createMock(ProductMenuService::class);$menu->expects(self::never())->method('generateCatalogPdf');
        $response=$this->controller($this->createMock(ProductCatalogAccessService::class),$public,$menu)->downloadMenuCatalog(Request::create('/products/menu/download','GET',['company'=>'99']));
        self::assertSame(403,$response->getStatusCode());
    }
    public function testOperationalRoutesUseInstalledSymfonyAuthorizationAttribute(): void
    {
        foreach (['getProductsInventory','getPurchasingSuggestion','getProductBySku','getProductSummary','downloadNormalizedCatalog','printLabel','print','printPurchasingSuggestion'] as $method) {
            $attributes=(new \ReflectionMethod(ProductController::class,$method))->getAttributes(IsGranted::class);
            self::assertCount(1,$attributes);
            self::assertSame('ROLE_HUMAN',$attributes[0]->newInstance()->attribute);
        }
    }
}
