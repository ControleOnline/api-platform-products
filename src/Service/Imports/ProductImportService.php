<?php

namespace ControleOnline\Service\Imports;

use ControleOnline\Entity\Import;
use ControleOnline\Service\ProductService;

class ProductImportService implements ImportProcessorInterface
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
        $file = $import->getFile();
        $content = $file?->getContent(true) ?? '';

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Nao foi possivel abrir o arquivo CSV para importacao.');
        }

        fwrite($handle, $content);
        rewind($handle);

        $headers = null;
        $lineNumber = 0;
        $successCount = 0;
        $errorMessages = [];

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($row === [null] || $this->isEmptyRow($row)) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normalizeHeaders($row);
                $this->validateHeaders($headers);
                continue;
            }

            $rowData = $this->mapRowToHeaders($headers, $row);

            try {
                $this->productService->importProductsFromCSV(
                    $rowData,
                    $import->getPeople()
                );
                $successCount++;
            } catch (\Throwable $e) {
                $errorMessages[] = sprintf('Linha %d: %s', $lineNumber, $e->getMessage());
            }
        }

        fclose($handle);

        if ($headers === null) {
            throw new \InvalidArgumentException('O arquivo CSV enviado esta vazio.');
        }

        if ($errorMessages !== []) {
            $feedback = array_merge(
                [sprintf('%d linha(s) importada(s) com sucesso.', $successCount)],
                $errorMessages
            );
            $import->setFeedback(implode("\n", $feedback));
            return;
        }

        $import->setFeedback(sprintf('%d linha(s) importada(s) com sucesso.', $successCount));
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

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $header = is_string($header) ? trim($header) : '';
            return ltrim($header, "\xEF\xBB\xBF");
        }, $headers);
    }

    private function validateHeaders(array $headers): void
    {
        $missingHeaders = array_values(array_diff(self::CSV_HEADERS, $headers));

        if ($missingHeaders !== []) {
            throw new \InvalidArgumentException(
                'Cabecalho CSV invalido. Colunas ausentes: ' . implode(', ', $missingHeaders)
            );
        }
    }

    private function mapRowToHeaders(array $headers, array $row): array
    {
        $mappedRow = [];

        foreach (self::CSV_HEADERS as $index => $header) {
            $headerIndex = array_search($header, $headers, true);
            $mappedRow[$header] = $headerIndex === false ? null : ($row[$headerIndex] ?? null);
        }

        return $mappedRow;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }
}
