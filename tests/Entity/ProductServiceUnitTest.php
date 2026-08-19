<?php

namespace ControleOnline\Tests\Entity;

use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductUnity;
use PHPUnit\Framework\TestCase;

class ProductServiceUnitTest extends TestCase
{
    public function testNonServiceProductSkipsUnitValidation(): void
    {
        $product = (new Product())->setType('product');
        $product->setProductUnit($this->unit('KG', 'Quilograma'));

        $product->validateServiceUnitCompatibility();
        $this->addToAssertionCount(1);
    }

    public function testServiceAcceptsMonthlyBillingUnit(): void
    {
        $product = (new Product())->setType('service');
        $product->setProductUnit($this->unit('MES', 'Mensal'));

        $product->validateServiceUnitCompatibility();
        $this->addToAssertionCount(1);
    }

    public function testServiceAcceptsHourlyBillingUnit(): void
    {
        $product = (new Product())->setType('service');
        $product->setProductUnit($this->unit('HR', 'Hora'));

        $product->validateServiceUnitCompatibility();
        $this->addToAssertionCount(1);
    }

    public function testServiceAcceptsUnitaryBillingUnit(): void
    {
        $product = (new Product())->setType('service');
        $product->setProductUnit($this->unit('UN', 'Unitário'));

        $product->validateServiceUnitCompatibility();
        $this->addToAssertionCount(1);
    }

    public function testServiceRejectsPhysicalKilogramUnit(): void
    {
        $product = (new Product())->setType('service');
        $product->setProductUnit($this->unit('KG', 'Quilograma'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unidades de cobranca');
        $product->validateServiceUnitCompatibility();
    }

    public function testServiceRejectsPhysicalLiterUnit(): void
    {
        $product = (new Product())->setType('service');
        $product->setProductUnit($this->unit('L', 'Litro'));

        $this->expectException(\InvalidArgumentException::class);
        $product->validateServiceUnitCompatibility();
    }

    public function testServiceWithoutUnitDoesNotThrow(): void
    {
        $product = (new Product())->setType('service');
        // productUnit left unset / not an instance
        $product->validateServiceUnitCompatibility();
        $this->addToAssertionCount(1);
    }

    private function unit(string $code, string $description): ProductUnity
    {
        return (new ProductUnity())
            ->setProductUnit($code)
            ->setDescription($description);
    }
}
