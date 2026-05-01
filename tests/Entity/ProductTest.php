<?php

namespace ControleOnline\Products\Tests\Entity;

use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductUnity;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testServiceRejectsPhysicalMeasurementUnits(): void
    {
        $product = (new Product())
            ->setType('service')
            ->setProductUnit($this->createUnit('LT', 'Litro'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Produtos do tipo service aceitam apenas unidades de cobranca');

        $product->validateServiceUnitCompatibility();
    }

    public function testServiceAllowsRecurringAndExecutionBillingUnits(): void
    {
        $allowedUnits = [
            $this->createUnit('UN', 'Unitario'),
            $this->createUnit('MES', 'Mensal'),
            $this->createUnit('HR', 'Hora tecnica'),
        ];

        foreach ($allowedUnits as $unit) {
            $product = (new Product())
                ->setType('service')
                ->setProductUnit($unit);

            $product->validateServiceUnitCompatibility();
            self::assertTrue(true);
        }
    }

    public function testNonServiceKeepsPhysicalMeasurementUnits(): void
    {
        $product = (new Product())
            ->setType('product')
            ->setProductUnit($this->createUnit('LT', 'Litro'));

        $product->validateServiceUnitCompatibility();

        self::assertTrue(true);
    }

    private function createUnit(string $code, string $description): ProductUnity
    {
        return (new ProductUnity())
            ->setProductUnit($code)
            ->setDescription($description);
    }
}
