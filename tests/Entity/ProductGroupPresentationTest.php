<?php

namespace ControleOnline\Products\Tests\Entity;

use ControleOnline\Entity\ProductGroup;
use PHPUnit\Framework\TestCase;

class ProductGroupPresentationTest extends TestCase
{
    public function testCompactPresentationDefaultsAndPrintFallback(): void
    {
        $group = (new ProductGroup())
            ->setProductGroup('Adicionais')
            ->setShowInDisplay(false);

        self::assertSame(ProductGroup::CUSTOMIZATION_TYPE_NEUTRAL, $group->getCustomizationType());
        self::assertNull($group->getShowUnitQuantity());
        self::assertFalse($group->getShowInPrint());

        $group->setShowInDisplay(true);
        self::assertTrue($group->getShowInPrint());

        $group->setShowInPrint(false);
        self::assertFalse($group->getShowInPrint());
    }
}
