<?php

namespace ControleOnline\Tests\Entity;

use ControleOnline\Entity\Inventory;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductShowcase;
use ControleOnline\Entity\ProductShowcaseItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ProductShowcaseItemOwnershipTest extends TestCase
{
    public function testProductAndInventoryMustBelongToShowcaseCompany(): void
    {
        $company = $this->people(1);
        $otherCompany = $this->people(2);
        $showcase = (new ProductShowcase())
            ->setCompany($company)
            ->setName('Shop')
            ->setIntegrationKey('shop');
        $product = (new Product())
            ->setId(10)
            ->setProduct('Produto')
            ->setCompany($company);
        $inventory = (new Inventory())
            ->setId(20)
            ->setInventory('Saída')
            ->setType('out')
            ->setPeople($company);
        $item = (new ProductShowcaseItem())
            ->setShowcase($showcase)
            ->setProduct($product)
            ->setOutInventory($inventory);

        self::assertTrue($item->hasConsistentOwnership());

        $product->setCompany($otherCompany);
        self::assertFalse($item->hasConsistentOwnership());

        $product->setCompany($company);
        $inventory->setPeople($otherCompany);
        self::assertFalse($item->hasConsistentOwnership());
    }

    private function people(int $id): People
    {
        $people = $this->createMock(People::class);
        $people->method('getId')->willReturn($id);

        return $people;
    }
}
