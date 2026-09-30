<?php
namespace ControleOnline\Tests\Security;

use ControleOnline\Entity\{People, PeopleLink, Product, ProductPeople};
use ControleOnline\Service\{PeopleRoleService, ProductCatalogAccessService};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AllowMockObjectsWithoutExpectations]
class ProductCatalogAccessServiceTest extends TestCase
{
    public function testEmployeeCanReadButCannotEditCatalog(): void
    {
        $roles = $this->createMock(PeopleRoleService::class);
        $roles->method('canAccessCompany')->willReturnCallback(fn($company, $people, $types) => $types === PeopleLink::HUMAN_LINK);
        $access = new ProductCatalogAccessService($roles, $this->createMock(EntityManagerInterface::class));
        $company = new People();
        $access->assertReadCompany($company);
        $this->expectException(AccessDeniedException::class);
        $access->assertManageCompany($company);
    }

    public function testManagerCanManageOnlyItsDirectCompany(): void
    {
        $allowed = new People();
        $roles = $this->createMock(PeopleRoleService::class);
        $roles->method('canAccessCompany')->willReturnCallback(fn($company, $people, $types) => $company === $allowed && $types === PeopleLink::ADMIN_LINK);
        $access = new ProductCatalogAccessService($roles, $this->createMock(EntityManagerInterface::class));
        $access->assertManageCompany($allowed);
        $this->expectException(AccessDeniedException::class);
        $access->assertManageCompany(new People());
    }

    public function testTransferMustAuthorizeOriginalCompanyAsWellAsNewCompany(): void
    {
        $original = new People(); $target = new People();
        $product = (new Product())->setCompany($target);
        $roles = $this->createMock(PeopleRoleService::class);
        $roles->method('canAccessCompany')->willReturnCallback(fn($company) => $company === $target);
        $access = new ProductCatalogAccessService($roles, $this->createMock(EntityManagerInterface::class));
        $this->expectException(AccessDeniedException::class);
        $access->assertWrite($product, ['company' => $original]);
    }
    public function testProviderRelationshipMustBeEnabledAndScopedToProductCompany(): void
    {
        $company=new People();
        $supplier=$this->createMock(People::class);$supplier->method('getEnabled')->willReturn(true);
        $product=(new Product())->setCompany($company);
        $relation=(new ProductPeople())->setProduct($product)->setPeople($supplier);
        $roles=$this->createMock(PeopleRoleService::class);$roles->method('canAccessCompany')->willReturn(true);
        $repository=$this->createMock(\ControleOnline\Repository\PeopleLinkRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([
            'company'=>$company,'people'=>$supplier,'linkType'=>'provider','enable'=>true,
        ])->willReturn(null);
        $manager=$this->createMock(EntityManagerInterface::class);$manager->method('getRepository')->willReturn($repository);
        $this->expectException(AccessDeniedException::class);
        (new ProductCatalogAccessService($roles,$manager))->assertWrite($relation);
    }

    public function testEnabledProviderCanBeLinkedByCompanyManager(): void
    {
        $company=new People();
        $supplier=$this->createMock(People::class);$supplier->method('getEnabled')->willReturn(true);
        $relation=(new ProductPeople())->setProduct((new Product())->setCompany($company))->setPeople($supplier);
        $roles=$this->createMock(PeopleRoleService::class);$roles->method('canAccessCompany')->willReturn(true);
        $link=$this->createMock(PeopleLink::class);$link->method('getEnabled')->willReturn(true);
        $repository=$this->createMock(\ControleOnline\Repository\PeopleLinkRepository::class);$repository->method('findOneBy')->willReturn($link);
        $manager=$this->createMock(EntityManagerInterface::class);$manager->method('getRepository')->willReturn($repository);
        (new ProductCatalogAccessService($roles,$manager))->assertWrite($relation);
        self::assertTrue(true);
    }

    public function testForeignInventoryReferenceIsRejectedEvenForManagerOfBothCompanies(): void
    {
        $company=new People();$foreign=new People();
        $inventory=(new \ControleOnline\Entity\Inventory())->setPeople($foreign);
        $product=(new Product())->setCompany($company)->setDefaultOutInventory($inventory);
        $roles=$this->createMock(PeopleRoleService::class);$roles->method('canAccessCompany')->willReturn(true);
        $this->expectException(AccessDeniedException::class);
        (new ProductCatalogAccessService($roles,$this->createMock(EntityManagerInterface::class)))->assertWrite($product);
    }

}
