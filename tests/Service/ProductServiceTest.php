<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Service\PeopleService;
use ControleOnline\Service\PrintService;
use ControleOnline\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AllowMockObjectsWithoutExpectations]
class ProductServiceTest extends TestCase
{
    #[DataProvider('allowedGroupPriceCalculationProvider')]
    public function testValidateImportRowAcceptsSupportedGroupPriceCalculations(string $value): void
    {
        $service = $this->createProductService();
        $method = new ReflectionMethod(ProductService::class, 'validateImportRow');
        $method->setAccessible(true);

        $method->invoke($service, [
            'category_name' => 'Categoria',
            'product_name' => 'Produto',
            'group_name' => 'Grupo',
            'group_price_calculation' => $value,
        ]);

        self::assertTrue(true);
    }

    public function testValidateImportRowRejectsInvalidGroupPriceCalculation(): void
    {
        $service = $this->createProductService();
        $method = new ReflectionMethod(ProductService::class, 'validateImportRow');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);

        $method->invoke($service, [
            'category_name' => 'Categoria',
            'product_name' => 'Produto',
            'group_name' => 'Grupo',
            'group_price_calculation' => 'invalid',
        ]);
    }

    public static function allowedGroupPriceCalculationProvider(): array
    {
        return [
            ['sum'],
            ['average'],
            ['biggest'],
            ['free'],
        ];
    }

    private function createProductService(): ProductService
    {
        return new ProductService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(PrintService::class),
            $this->createMock(PeopleService::class),
        );
    }
}
