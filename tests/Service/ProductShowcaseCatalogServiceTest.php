<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleDomain;
use ControleOnline\Entity\Device;
use ControleOnline\Entity\DeviceConfig;
use ControleOnline\Entity\ProductShowcase;
use ControleOnline\Repository\ProductShowcaseRepository;
use ControleOnline\Service\DeviceService;
use ControleOnline\Service\DomainService;
use ControleOnline\Service\ProductCatalogCategoryTreeService;
use ControleOnline\Service\ProductCatalogProjectionService;
use ControleOnline\Service\ProductCatalogQueryService;
use ControleOnline\Service\ProductShowcaseCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use ReflectionProperty;

#[AllowMockObjectsWithoutExpectations]
class ProductShowcaseCatalogServiceTest extends TestCase
{
    public function testResolvedEmptyShowcaseDoesNotLeakLegacyProducts(): void
    {
        $company = $this->createMock(People::class);
        $showcase = (new ProductShowcase())
            ->setCompany($company)
            ->setName('Balcão')
            ->setIntegrationKey('external-store');
        $showcaseRepository = $this->createMock(ProductShowcaseRepository::class);
        $showcaseRepository->expects(self::once())
            ->method('findDefaultActive')
            ->with($company, 'external-store')
            ->willReturn($showcase);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())
            ->method('getRepository')
            ->with(ProductShowcase::class)
            ->willReturn($showcaseRepository);
        $query = $this->createMock(ProductCatalogQueryService::class);
        $query->expects(self::once())
            ->method('fetchShowcaseItems')
            ->with($showcase, [], 1, 50)
            ->willReturn([[], 0]);
        $query->expects(self::never())->method('fetchFallbackProducts');
        $projection = $this->createMock(ProductCatalogProjectionService::class);
        $projection->expects(self::once())
            ->method('build')
            ->with([], $company, $showcase)
            ->willReturn(['products' => [], 'categoryIds' => []]);
        $categoryTree = $this->createMock(ProductCatalogCategoryTreeService::class);
        $categoryTree->expects(self::once())
            ->method('build')
            ->with($company, [])
            ->willReturn([]);

        $payload = $this->createService($manager, $query, $projection, null, null, $categoryTree)
            ->buildCatalog($company, 'external-store');

        self::assertSame([], $payload['member']);
        self::assertSame(0, $payload['totalItems']);
        self::assertSame('showcase', $payload['source']);
        self::assertFalse($payload['legacyFallback']);
        self::assertSame(['source' => 'showcase', 'ids' => []], $payload['categoryProjection']);
        self::assertSame([], $payload['categories']);
    }

    public function testLegacyFallbackIsExplicitOnlyWhenNoShowcaseResolves(): void
    {
        $company = $this->createMock(People::class);
        $showcaseRepository = $this->createMock(ProductShowcaseRepository::class);
        $showcaseRepository->expects(self::once())
            ->method('findDefaultActive')
            ->with($company, 'external-store')
            ->willReturn(null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())
            ->method('getRepository')
            ->with(ProductShowcase::class)
            ->willReturn($showcaseRepository);
        $query = $this->createMock(ProductCatalogQueryService::class);
        $query->expects(self::never())->method('fetchShowcaseItems');
        $query->expects(self::once())
            ->method('fetchFallbackProducts')
            ->with($company, [], 1, 50)
            ->willReturn([[], 0]);
        $projection = $this->createMock(ProductCatalogProjectionService::class);
        $projection->expects(self::once())
            ->method('build')
            ->with([], $company, null)
            ->willReturn(['products' => [], 'categoryIds' => [12, 19]]);
        $categoryTree = $this->createMock(ProductCatalogCategoryTreeService::class);
        $categoryTree->expects(self::once())
            ->method('build')
            ->with($company, [12, 19])
            ->willReturn([['id' => 12], ['id' => 19]]);

        $payload = $this->createService($manager, $query, $projection, null, null, $categoryTree)
            ->buildCatalog($company, 'external-store');

        self::assertSame('product', $payload['source']);
        self::assertTrue($payload['legacyFallback']);
        self::assertSame([12, 19], $payload['categoryIds']);
        self::assertSame(
            ['source' => 'legacy-product', 'ids' => [12, 19]],
            $payload['categoryProjection']
        );
        self::assertSame([['id' => 12], ['id' => 19]], $payload['categories']);
    }

    public function testClientIdsCannotExpandTrustedShowcaseCategoryProjection(): void
    {
        $company = $this->createMock(People::class);
        $showcase = (new ProductShowcase())
            ->setCompany($company)
            ->setName('Shop')
            ->setIntegrationKey('external-store');
        $filters = [
            'categoryIds' => [12, 15, 99],
            'ids' => '99',
        ];
        $showcaseRepository = $this->createMock(ProductShowcaseRepository::class);
        $showcaseRepository->expects(self::once())
            ->method('findDefaultActive')
            ->with($company, 'external-store')
            ->willReturn($showcase);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())
            ->method('getRepository')
            ->with(ProductShowcase::class)
            ->willReturn($showcaseRepository);
        $query = $this->createMock(ProductCatalogQueryService::class);
        $query->expects(self::once())
            ->method('fetchShowcaseItems')
            ->with($showcase, $filters, 1, 50)
            ->willReturn([[], 0]);
        $projection = $this->createMock(ProductCatalogProjectionService::class);
        $projection->expects(self::once())
            ->method('build')
            ->with([], $company, $showcase)
            ->willReturn(['products' => [], 'categoryIds' => [15]]);
        $categoryTree = $this->createMock(ProductCatalogCategoryTreeService::class);
        $categoryTree->expects(self::once())
            ->method('build')
            ->with($company, [15])
            ->willReturn([['id' => 12], ['id' => 15]]);

        $payload = $this->createService(
            $manager,
            $query,
            $projection,
            null,
            null,
            $categoryTree
        )->buildCatalog($company, 'external-store', $filters);

        self::assertSame([15], $payload['categoryProjection']['ids']);
        self::assertSame([12, 15], array_column($payload['categories'], 'id'));
        self::assertNotContains(99, array_column($payload['categories'], 'id'));
    }

    public function testPosResolvesConfiguredShowcaseFromDeviceWithinCompany(): void
    {
        $company = $this->createMock(People::class);
        $device = $this->createMock(Device::class);
        $showcase = (new ProductShowcase())
            ->setCompany($company)
            ->setName('PDV')
            ->setIntegrationKey('pos');
        (new ReflectionProperty($showcase, 'id'))->setValue($showcase, 55);
        $deviceConfig = (new DeviceConfig())
            ->setPeople($company)
            ->setDevice($device)
            ->setType('PDV')
            ->setConfigs(['pos-product-showcase-id' => 55]);
        $deviceService = $this->createMock(DeviceService::class);
        $deviceService->expects(self::once())->method('resolveDeviceReference')->with('terminal')->willReturn($device);
        $deviceService->expects(self::once())->method('findDeviceConfig')->with($device, $company, null)->willReturn($deviceConfig);
        $deviceService->expects(self::once())->method('findDeviceConfigs')->with($device, $company)->willReturn([]);
        $showcaseRepository = $this->createMock(ProductShowcaseRepository::class);
        $showcaseRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => 55, 'company' => $company, 'integrationKey' => 'pos', 'active' => true])
            ->willReturn($showcase);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('getRepository')->with(ProductShowcase::class)->willReturn($showcaseRepository);
        [$query, $projection] = $this->emptyCatalogCollaborators($showcase, $company, ['device' => 'terminal']);

        $payload = $this->createService($manager, $query, $projection, $deviceService)
            ->buildCatalog($company, 'pos', ['device' => 'terminal']);

        self::assertSame(55, $payload['showcase']['id']);
        self::assertSame('showcase', $payload['source']);
    }

    public function testShopResolvesShowcaseFromTrustedDomain(): void
    {
        $company = $this->createMock(People::class);
        $domain = (new PeopleDomain())
            ->setPeople($company)
            ->setDomain('shop.example.test')
            ->setDomainType('SHOP');
        $showcase = (new ProductShowcase())
            ->setCompany($company)
            ->setPeopleDomain($domain)
            ->setName('Shop')
            ->setIntegrationKey('shop');
        $showcaseRepository = $this->createMock(ProductShowcaseRepository::class);
        $showcaseRepository->expects(self::once())
            ->method('findActiveForPeopleDomain')
            ->with($company, 'shop', $domain)
            ->willReturn($showcase);
        $showcaseRepository->expects(self::never())->method('findDefaultActive');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('getRepository')->with(ProductShowcase::class)->willReturn($showcaseRepository);
        $domainService = $this->createMock(DomainService::class);
        $domainService->expects(self::once())->method('getPeopleDomain')->willReturn($domain);
        [$query, $projection] = $this->emptyCatalogCollaborators($showcase, $company, []);

        $payload = $this->createService(
            $manager,
            $query,
            $projection,
            null,
            $domainService
        )->buildCatalog($company, 'shop');

        self::assertSame('shop', $payload['showcase']['integrationKey']);
        self::assertSame('shop.example.test', $payload['showcase']['peopleDomain']['domain']);
    }

    private function createService(
        EntityManagerInterface $manager,
        ProductCatalogQueryService $query,
        ProductCatalogProjectionService $projection,
        ?DeviceService $deviceService = null,
        ?DomainService $domainService = null,
        ?ProductCatalogCategoryTreeService $categoryTree = null
    ): ProductShowcaseCatalogService {
        return new ProductShowcaseCatalogService(
            $manager,
            $deviceService ?? $this->createMock(DeviceService::class),
            $domainService ?? $this->createMock(DomainService::class),
            new RequestStack(),
            $query,
            $projection,
            $categoryTree ?? $this->createMock(ProductCatalogCategoryTreeService::class)
        );
    }

    /**
     * @return array{0: ProductCatalogQueryService, 1: ProductCatalogProjectionService}
     */
    private function emptyCatalogCollaborators(
        ProductShowcase $showcase,
        People $company,
        array $filters
    ): array {
        $query = $this->createMock(ProductCatalogQueryService::class);
        $query->expects(self::once())
            ->method('fetchShowcaseItems')
            ->with($showcase, $filters, 1, 50)
            ->willReturn([[], 0]);
        $projection = $this->createMock(ProductCatalogProjectionService::class);
        $projection->expects(self::once())
            ->method('build')
            ->with([], $company, $showcase)
            ->willReturn(['products' => [], 'categoryIds' => []]);

        return [$query, $projection];
    }
}
