<?php
namespace ControleOnline\Tests\Security;
use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post, Put, Delete, Operations};
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ControleOnline\Entity\Product;
use ControleOnline\Metadata\ProductCatalogResourceMetadataFactory;
use ControleOnline\Service\ProductCatalogAccessService;
use PHPUnit\Framework\{TestCase, Attributes\AllowMockObjectsWithoutExpectations};

#[AllowMockObjectsWithoutExpectations]
class ProductCatalogMetadataFactoryTest extends TestCase
{
    public function testAllCrudOperationsUseTheRealObjectAndPreserveSerialization(): void
    {
        $resource=(new ApiResource())->withNormalizationContext(['groups'=>['product:read']])->withOperations(new Operations([
            'get'=>new Get(), 'list'=>new GetCollection(fetchPartial:true), 'post'=>new Post(), 'put'=>new Put(), 'delete'=>new Delete(),
        ]));
        $inner=$this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $inner->method('create')->willReturn(new ResourceMetadataCollection(Product::class,[$resource]));
        $access=$this->createMock(ProductCatalogAccessService::class);$access->method('supports')->willReturn(true);
        $result=(new ProductCatalogResourceMetadataFactory($inner,$access))->create(Product::class)[0];
        $ops=iterator_to_array($result->getOperations());
        self::assertSame("is_granted('ROLE_HUMAN')",$ops['get']->getSecurity());
        self::assertTrue($ops['list']->getFetchPartial());
        self::assertStringContainsString('object',$ops['post']->getSecurityPostDenormalize());
        self::assertStringContainsString('previous_object',$ops['put']->getSecurityPostDenormalize());
        self::assertSame("is_granted('CATALOG_MANAGE', object)",$ops['delete']->getSecurity());
        self::assertSame(['groups'=>['product:read']],$result->getNormalizationContext());
    }
}
