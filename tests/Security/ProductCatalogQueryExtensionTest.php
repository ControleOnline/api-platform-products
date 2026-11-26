<?php
namespace ControleOnline\Tests\Security;
use ControleOnline\Doctrine\Extension\ProductCatalogSecurityExtension;
use ControleOnline\Entity\{People, Product, ProductPeople, ProductGroupProduct};
use ControleOnline\Service\{PeopleRoleService, ProductCatalogAccessService};
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use PHPUnit\Framework\{TestCase, Attributes\AllowMockObjectsWithoutExpectations};

#[AllowMockObjectsWithoutExpectations]
class ProductCatalogQueryExtensionTest extends TestCase
{
    public function testBothItemAndCollectionDenyUnaffiliatedReaders(): void
    {
        $roles = $this->createMock(PeopleRoleService::class);
        $roles->method('getAccessibleCompaniesForPeople')->willReturn([]);
        $manager = $this->createMock(EntityManagerInterface::class);
        $extension = new ProductCatalogSecurityExtension(new ProductCatalogAccessService($roles,$manager));
        foreach (['item','collection'] as $kind) {
            $query = (new QueryBuilder($manager))->select('p')->from(Product::class,'p');
            if ($kind === 'item') $extension->applyToItem($query,new QueryNameGenerator(),Product::class,['id'=>999]);
            else $extension->applyToCollection($query,new QueryNameGenerator(),Product::class);
            self::assertStringContainsString('1 = 0',$query->getDQL());
        }
    }
    public function testProductPeopleAndGroupsCannotBypassCompanyFiltering(): void
    {
        $roles = $this->createMock(PeopleRoleService::class);
        $company=$this->createMock(People::class); $company->method('getId')->willReturn(7);
        $roles->method('getAccessibleCompaniesForPeople')->willReturn([$company]);
        $manager=$this->createMock(EntityManagerInterface::class);
        $access=new ProductCatalogAccessService($roles,$manager);
        foreach ([Product::class,ProductPeople::class,ProductGroupProduct::class] as $class) {
            $query=(new QueryBuilder($manager))->select('p')->from($class,'p');
            $access->filter($query,$class);
            self::assertStringContainsString('IN (:catalog_companies_0)',$query->getDQL());
            self::assertSame([7],$query->getParameter('catalog_companies_0')->getValue());
        }
    }
}
