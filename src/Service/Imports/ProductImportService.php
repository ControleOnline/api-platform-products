<?php

namespace ControleOnline\Service\Imports;

use ControleOnline\Entity\Import;
use ControleOnline\Service\ProductService;

class ProductImportService extends ImportCommon
{
    private const CSV_HEADERS = [
        'category_name',
        'category_parent_name',
        'product_name',
        'product_description',
        'product_sku',
        'product_price',
        'product_type',
        'product_condition',
        'product_unit',
        'product_active',
        'group_name',
        'group_required',
        'group_minimum',
        'group_maximum',
        'group_order',
        'group_price_calculation',
        'group_active',
        'item_name',
        'item_description',
        'item_sku',
        'item_price',
        'item_quantity',
        'item_product_type',
        'item_unit',
        'item_active',
    ];

    public function __construct(
        private ProductService $productService
    ) {}

    public function getType(): string
    {
        return 'product';
    }

    public function process(Import $import): void
    {
        $this->import($import, self::CSV_HEADERS, $this->productService);
    }

    public function getExampleCsv(): array
    {
        return [
            [
                ...self::CSV_HEADERS,
            ],
            [
                'Lanches',
                '',
                'Alpha Gyros',
                'Sanduiche da casa',
                'ALPHA001',
                '49.90',
                'custom',
                'new',
                'UN',
                '1',
                'Escolha a Proteina',
                '1',
                '1',
                '1',
                '1',
                'sum',
                '1',
                'Frango',
                '',
                'FRANGO001',
                '0',
                '1',
                'component',
                'UN',
                '1',
            ],
            [
                'Lanches',
                '',
                'Alpha Gyros',
                'Sanduiche da casa',
                'ALPHA001',
                '49.90',
                'custom',
                'new',
                'UN',
                '1',
                'Escolha a Proteina',
                '1',
                '1',
                '1',
                '1',
                'sum',
                '1',
                'Carne',
                '',
                'CARNE001',
                '3',
                '1',
                'component',
                'UN',
                '1',
            ],
            [
                'Refrigerantes',
                'Bebidas',
                'Coca-Cola lata 350 ml',
                '',
                'COCA350',
                '8',
                'component',
                'new',
                'UN',
                '1',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];
    }
}
