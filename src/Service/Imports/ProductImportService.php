<?php

namespace ControleOnline\Service\Imports;

use ControleOnline\Entity\Import;
use ControleOnline\Service\ProductService;

class ProductImportService extends AbstractCsvImportProcessor
{
    public function __construct(
        private ProductService $productService
    ) {}

    public function getType(): string
    {
        return 'product';
    }

    public function process(Import $import): void
    {
        $file = $import->getFile();

        $rows = explode("\n", $file->getContent());

        foreach ($rows as $index => $row) {

            if ($index === 0 || trim($row) === '') {
                continue;
            }

            $data = str_getcsv($row);

            [
                $name,
                $description,
                $sku,
                $price,
                $category,
                $type,
                $condition,
                $unit
            ] = array_pad($data, 8, null);

            $price = $price !== null
                ? (float) str_replace(',', '.', $price)
                : 0;

            $this->productService->importProductsFromCSV(
                $name,
                $description,
                $sku,
                $price,
                $category,
                $type,
                $condition,
                $unit,
                $import->getPeople()
            );
        }
    }

    public function getExampleCsv(): string
    {
        $rows = [
            [
                'Nome',
                'Descrição',
                'SKU',
                'Preço',
                'Categoria',
                'Tipo',
                'Condição',
                'Unidade'
            ],
            [
                'Produto Exemplo',
                'Produto de teste para importação',
                'SKU123',
                '19.90',
                'Eletrônicos',
                'Produto',
                'Novo',
                'UN'
            ]
        ];

        return $this->generateUtf8Csv($rows);
    }
}
