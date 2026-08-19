<?php

namespace ControleOnline\Tests\Service\Imports;

use ControleOnline\Service\Imports\ProductImportService;
use ControleOnline\Service\ProductService;
use PHPUnit\Framework\TestCase;

class ProductImportServiceTest extends TestCase
{
    public function testExampleCsvMarksOnlyRequiredHeadersWithAsterisk(): void
    {
        $productService = $this->createMock(ProductService::class);
        $service = new ProductImportService($productService);

        $rows = $service->getExampleCsv();
        $this->assertNotEmpty($rows);

        $headerRow = $rows[0];
        $this->assertContains('category_name*', $headerRow);
        $this->assertContains('product_name*', $headerRow);
        $this->assertContains('category_parent_name', $headerRow);
        $this->assertContains('product_sku', $headerRow);
        $this->assertNotContains('category_name', $headerRow);
        $this->assertNotContains('product_name', $headerRow);

        $marked = array_values(array_filter(
            $headerRow,
            static fn(string $h): bool => str_ends_with($h, '*')
        ));
        $this->assertSame(['category_name*', 'product_name*'], $marked);
    }

    public function testExampleCsvKeepsSampleDataRowsWithoutAsteriskDecoration(): void
    {
        $productService = $this->createMock(ProductService::class);
        $service = new ProductImportService($productService);

        $rows = $service->getExampleCsv();
        $this->assertGreaterThan(1, count($rows));

        foreach (array_slice($rows, 1) as $dataRow) {
            foreach ($dataRow as $cell) {
                $this->assertIsString($cell);
                $this->assertStringNotContainsString('*', $cell);
            }
        }
    }
}
