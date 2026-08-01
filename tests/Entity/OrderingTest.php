<?php

namespace ControleOnline\Tests\Entity;

use ControleOnline\Entity\ProductCategory;
use ControleOnline\Entity\ProductGroupProduct;
use ControleOnline\Entity\ProductShowcaseItem;
use PHPUnit\Framework\TestCase;

class OrderingTest extends TestCase
{
    public function testProductPlacementOrdersAreNullableAndAcceptZero(): void
    {
        $categoryItem = new ProductCategory();
        $groupItem = new ProductGroupProduct();
        $showcaseItem = new ProductShowcaseItem();

        self::assertNull($categoryItem->getSortOrder());
        self::assertNull($groupItem->getSortOrder());
        self::assertNull($showcaseItem->getSortOrder());

        self::assertSame($categoryItem, $categoryItem->setSortOrder(0));
        self::assertSame($groupItem, $groupItem->setSortOrder(0));
        self::assertSame($showcaseItem, $showcaseItem->setSortOrder(0));

        self::assertSame(0, $categoryItem->getSortOrder());
        self::assertSame(0, $groupItem->getSortOrder());
        self::assertSame(0, $showcaseItem->getSortOrder());
    }
}
