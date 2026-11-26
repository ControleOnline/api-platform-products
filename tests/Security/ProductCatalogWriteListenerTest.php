<?php
namespace ControleOnline\Tests\Security;
use ControleOnline\Entity\{People,Product,ProductInventory};
use ControleOnline\Listener\ProductCatalogWriteListener;
use ControleOnline\Service\ProductCatalogAccessService;
use Doctrine\ORM\{EntityManagerInterface,UnitOfWork};
use Doctrine\ORM\Event\OnFlushEventArgs;
use PHPUnit\Framework\{TestCase,Attributes\AllowMockObjectsWithoutExpectations};

#[AllowMockObjectsWithoutExpectations]
class ProductCatalogWriteListenerTest extends TestCase
{
    public function testFlushRechecksInsertUpdateAndDeleteAgainstOriginalOwners(): void
    {
        $company=new People();$insert=(new Product())->setCompany($company);$update=(new Product())->setCompany($company);$delete=(new Product())->setCompany($company);
        $unit=$this->createMock(UnitOfWork::class);
        $unit->method('getScheduledEntityInsertions')->willReturn([$insert]);
        $unit->method('getScheduledEntityUpdates')->willReturn([$update]);
        $unit->method('getScheduledEntityDeletions')->willReturn([$delete]);
        $unit->method('getOriginalEntityData')->willReturn(['company'=>$company]);
        $manager=$this->createMock(EntityManagerInterface::class);$manager->method('getUnitOfWork')->willReturn($unit);
        $access=$this->createMock(ProductCatalogAccessService::class);$access->method('supports')->willReturn(true);
        $access->expects(self::exactly(3))->method('assertWrite')->with(self::isInstanceOf(Product::class),['company'=>$company]);
        (new ProductCatalogWriteListener($access))->onFlush(new OnFlushEventArgs($manager));
    }
    public function testOrderStockCountersRemainUnderOrderAuthorizationInsteadOfCatalogEditGrant(): void
    {
        $unit=$this->createMock(UnitOfWork::class);$unit->method('getScheduledEntityInsertions')->willReturn([]);
        $unit->method('getScheduledEntityUpdates')->willReturn([new ProductInventory()]);
        $unit->method('getEntityChangeSet')->willReturn(['sales'=>[0,1]]);$unit->method('getScheduledEntityDeletions')->willReturn([]);
        $manager=$this->createMock(EntityManagerInterface::class);$manager->method('getUnitOfWork')->willReturn($unit);
        $access=$this->createMock(ProductCatalogAccessService::class);$access->expects(self::never())->method('assertWrite');
        $access->expects(self::exactly(2))->method('assertReadCompany');
        (new ProductCatalogWriteListener($access))->onFlush(new OnFlushEventArgs($manager));
    }
}
