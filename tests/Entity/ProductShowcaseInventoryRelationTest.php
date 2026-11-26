<?php

declare(strict_types=1);

namespace ControleOnline\Tests\Entity;

use ControleOnline\Entity\Inventory;
use ControleOnline\Entity\ProductShowcase;
use PHPUnit\Framework\TestCase;

final class ProductShowcaseInventoryRelationTest extends TestCase
{
    public function testAddAndRemoveInventory(): void
    {
        $showcase = new ProductShowcase();
        $inventory = new Inventory();
        $inventory->setInventory('Geladeiras');
        $inventory->setType('warehouse');

        $showcase->addInventory($inventory);
        self::assertTrue($showcase->getInventories()->contains($inventory));

        $showcase->removeInventory($inventory);
        self::assertFalse($showcase->getInventories()->contains($inventory));
    }
}
