<?php
namespace ControleOnline\Tests\Security;
use ControleOnline\Entity\Product;
use ControleOnline\Service\ProductCatalogAccessService;
use ControleOnline\Security\ProductCatalogVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use PHPUnit\Framework\{TestCase,Attributes\AllowMockObjectsWithoutExpectations};
#[AllowMockObjectsWithoutExpectations]
class ProductCatalogVoterTest extends TestCase
{
    public function testDeniedManagementGrantReturnsDenyWithActualSymfonyVoterSignature(): void
    {
        $access=$this->createMock(ProductCatalogAccessService::class);$access->method('supports')->willReturn(true);
        $access->method('assertWrite')->willThrowException(new AccessDeniedException());
        $voter=new ProductCatalogVoter($access);
        self::assertSame(VoterInterface::ACCESS_DENIED,$voter->vote($this->createMock(TokenInterface::class),new Product(),['CATALOG_MANAGE']));
    }
}
