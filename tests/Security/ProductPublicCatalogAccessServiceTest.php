<?php
namespace ControleOnline\Tests\Security;
use ControleOnline\Entity\{People,PeopleDomain};
use ControleOnline\Service\{ProductCatalogAccessService,ProductPublicCatalogAccessService,PublicShopCategoryService,DomainService};
use ControleOnline\Repository\PeopleDomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\{TestCase, Attributes\AllowMockObjectsWithoutExpectations};
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AllowMockObjectsWithoutExpectations]
class ProductPublicCatalogAccessServiceTest extends TestCase
{
    private function service(?PeopleDomain $domain, ?int $allowed): ProductPublicCatalogAccessService
    {
        $access=$this->createMock(ProductCatalogAccessService::class);
        $access->method('assertReadCompany')->willThrowException(new AccessDeniedException());
        $domains=$this->createMock(DomainService::class);$domains->method('getDomain')->willReturn('shop.example.test');
        $repository=$this->createMock(PeopleDomainRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['domain'=>'shop.example.test'])->willReturn($domain);
        $manager=$this->createMock(EntityManagerInterface::class);$manager->method('getRepository')->willReturn($repository);
        $categories=$this->createMock(PublicShopCategoryService::class);$categories->method('resolvePublicShopCompanyId')->willReturn($allowed);
        return new ProductPublicCatalogAccessService($access,$domains,$categories,$manager);
    }
    private function company(int $id): People
    { $company=$this->createMock(People::class);$company->method('getId')->willReturn($id);$company->method('getEnabled')->willReturn(true);return $company; }
    private function shop(People $company): PeopleDomain
    { return (new PeopleDomain())->setPeople($company)->setDomain('shop.example.test')->setDomainType('SHOP'); }
    public function testAnonymousShopOwnCompanyAndAllowlistedFranchiseRetainProjectionAccess(): void
    {
        $owner=$this->company(7);
        foreach ([$owner,$this->company(8)] as $company) {
            $this->service($this->shop($owner),$company->getId())->assertCatalog($company,'shop');
            self::assertTrue(true);
        }
    }
    public function testUnknownDomainCannotUseMainDomainFallback(): void
    { $this->expectException(AccessDeniedException::class);$this->service(null,7)->assertCatalog($this->company(7),'shop'); }
    public function testUnpublishedForeignCompanyIsDenied(): void
    { $this->expectException(AccessDeniedException::class);$this->service($this->shop($this->company(7)),null)->assertCatalog($this->company(99),'shop'); }
    public function testAuthenticatedAccessiblePosCompanyDoesNotRequireShopDomain(): void
    {
        $access=$this->createMock(ProductCatalogAccessService::class);$access->expects(self::once())->method('assertReadCompany');
        $domains=$this->createMock(DomainService::class);$domains->expects(self::never())->method('getDomain');
        $service=new ProductPublicCatalogAccessService($access,$domains,$this->createMock(PublicShopCategoryService::class),$this->createMock(EntityManagerInterface::class));
        $service->assertCatalog($this->company(7),'pos');
    }
}
