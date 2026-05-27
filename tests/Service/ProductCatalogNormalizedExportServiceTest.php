<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\People;
use ControleOnline\Service\ProductCatalogNormalizedExportService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ProductCatalogNormalizedExportServiceTest extends TestCase
{
    public function testBuildNormalizedCatalogCsvIncludesBomHeadersAndRows(): void
    {
        $company = $this->createMock(People::class);
        $company->method('getId')->willReturn(123);
        $company->method('getAlias')->willReturn('Batata Gratinada');
        $company->method('getName')->willReturn('Empresa Teste');

        $rows = [
            [
                'category_id' => '7',
                'category_name' => 'Lanches',
                'category_parent_id' => '2',
                'category_parent_name' => 'Cardapio',
                'product_id' => '1115',
                'product_name' => 'Proteina Gratinada',
                'product_description' => 'Proteina gratinada com queijo',
                'product_sku' => 'PROT001',
                'product_price' => '34.90',
                'product_type' => 'custom',
                'product_condition' => 'new',
                'product_unit' => 'UN',
                'product_active' => '1',
                'product_group_parent_id' => '321',
                'product_group_parent_active' => '1',
                'product_group_id' => '55',
                'group_name' => 'Escolha sua proteina',
                'group_required' => '1',
                'group_minimum' => '1',
                'group_maximum' => '2',
                'group_order' => '1',
                'group_price_calculation' => 'sum',
                'group_active' => '1',
                'group_show_in_display' => '0',
                'product_group_product_id' => '998',
                'item_id' => '2001',
                'item_name' => 'Frango',
                'item_description' => '',
                'item_sku' => 'FRANGO001',
                'item_price' => '0.00',
                'item_quantity' => '1.00',
                'item_product_type' => 'component',
                'item_unit' => 'UN',
                'item_active' => '1',
                'item_show_in_parent_queue' => '1',
                'ifood_item_id' => null,
                'ifood_option_id' => null,
                'ifood_status' => null,
                'food99_code' => 'F99-001',
                'food99_published' => '1',
                'ifood_sync_hash' => 'hash-ifood',
                'ifood_sync_synced_at' => '2026-05-26 12:00:00',
                'food99_sync_hash' => 'hash-99',
                'food99_sync_synced_at' => '2026-05-26 12:30:00',
            ],
            [
                'category_id' => '7',
                'category_name' => 'Lanches',
                'category_parent_id' => '2',
                'category_parent_name' => 'Cardapio',
                'product_id' => '1115',
                'product_name' => 'Proteina Gratinada',
                'product_description' => 'Proteina gratinada com queijo',
                'product_sku' => 'PROT001',
                'product_price' => '34.90',
                'product_type' => 'custom',
                'product_condition' => 'new',
                'product_unit' => 'UN',
                'product_active' => '1',
                'product_group_parent_id' => '321',
                'product_group_parent_active' => '1',
                'product_group_id' => '56',
                'group_name' => 'Adicionais',
                'group_required' => '0',
                'group_minimum' => '0',
                'group_maximum' => '0',
                'group_order' => '2',
                'group_price_calculation' => 'average',
                'group_active' => '1',
                'group_show_in_display' => '1',
                'product_group_product_id' => null,
                'item_id' => null,
                'item_name' => null,
                'item_description' => null,
                'item_sku' => null,
                'item_price' => null,
                'item_quantity' => null,
                'item_product_type' => null,
                'item_unit' => null,
                'item_active' => null,
                'item_show_in_parent_queue' => null,
                'ifood_item_id' => null,
                'ifood_option_id' => null,
                'ifood_status' => null,
                'food99_code' => null,
                'food99_published' => null,
                'ifood_sync_hash' => null,
                'ifood_sync_synced_at' => null,
                'food99_sync_hash' => null,
                'food99_sync_synced_at' => null,
            ],
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn($rows);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $service = new ProductCatalogNormalizedExportService($entityManager);
        $csv = $service->buildNormalizedCatalogCsv($company, 'products');

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(
            explode("\n", str_replace("\r", '', substr($csv, 3))),
            static fn(string $line): bool => trim($line) !== ''
        ));

        self::assertCount(3, $lines);

        $headers = str_getcsv($lines[0]);
        self::assertCount(44, $headers);
        self::assertSame('category_id', $headers[0]);
        self::assertSame('food99_sync_synced_at', $headers[43]);

        $firstRow = array_combine($headers, str_getcsv($lines[1]));
        self::assertSame('1115', $firstRow['product_id']);
        self::assertSame('Frango', $firstRow['item_name']);
        self::assertSame('F99-001', $firstRow['food99_code']);
        self::assertSame('hash-99', $firstRow['food99_sync_hash']);

        $secondRow = array_combine($headers, str_getcsv($lines[2]));
        self::assertSame('56', $secondRow['product_group_id']);
        self::assertSame('', $secondRow['item_name']);
        self::assertSame('', $secondRow['product_group_product_id']);
        self::assertSame('', $secondRow['ifood_item_id']);
    }

    public function testBuildNormalizedCatalogFilenameSlugifiesCompanyName(): void
    {
        $company = $this->createMock(People::class);
        $company->method('getId')->willReturn(123);
        $company->method('getAlias')->willReturn('Batata Gratinada - Grande');
        $company->method('getName')->willReturn('Empresa Teste');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new ProductCatalogNormalizedExportService($entityManager);

        self::assertSame(
            'catalogo-normalizado-batata-gratinada-grande.csv',
            $service->buildNormalizedCatalogFilename($company)
        );
    }
}
