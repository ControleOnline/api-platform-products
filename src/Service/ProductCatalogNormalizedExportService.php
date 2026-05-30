<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Order;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;

class ProductCatalogNormalizedExportService
{
    private const CSV_HEADERS = [
        'category_id',
        'category_name',
        'category_parent_id',
        'category_parent_name',
        'product_id',
        'product_name',
        'product_description',
        'product_sku',
        'product_price',
        'product_type',
        'product_condition',
        'product_unit',
        'product_active',
        'product_group_parent_id',
        'product_group_parent_active',
        'product_group_id',
        'group_name',
        'group_required',
        'group_minimum',
        'group_maximum',
        'group_order',
        'group_price_calculation',
        'group_active',
        'group_show_in_display',
        'product_group_product_id',
        'item_id',
        'item_name',
        'item_description',
        'item_sku',
        'item_price',
        'item_quantity',
        'item_product_type',
        'item_unit',
        'item_active',
        'item_show_in_parent_queue',
        'ifood_item_id',
        'ifood_option_id',
        'ifood_status',
        'food99_code',
        'food99_published',
        'ifood_sync_hash',
        'ifood_sync_synced_at',
        'food99_sync_hash',
        'food99_sync_synced_at',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function buildNormalizedCatalogFilename(People $company): string
    {
        $baseName = trim((string) ($company->getAlias() ?: $company->getName()));
        if ($baseName === '') {
            $baseName = (string) $company->getId();
        }

        $slug = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $baseName));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '');
        $slug = trim((string) $slug, '-');

        return sprintf(
            'catalogo-normalizado-%s.csv',
            $slug !== '' ? $slug : $company->getId()
        );
    }

    public function buildNormalizedCatalogCsv(People $company, string $context = 'products'): string
    {
        $rows = $this->fetchRows($company, $context);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Nao foi possivel preparar o arquivo CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::CSV_HEADERS);

        foreach ($rows as $row) {
            $csvRow = [];
            foreach (self::CSV_HEADERS as $header) {
                $csvRow[] = $this->normalizeCsvValue($row[$header] ?? null);
            }

            fputcsv($handle, $csvRow);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(People $company, string $context): array
    {
        $normalizedContext = $this->normalizeContext($context);
        $params = [
            'companyId' => (int) $company->getId(),
            'categoryContext' => $normalizedContext,
        ];
        $groupProductJoinCondition = 'pgp.product_group_id = pg.id';

        $baseSelects = [
            'cat.id AS category_id',
            'cat.name AS category_name',
            'cat_parent.id AS category_parent_id',
            'cat_parent.name AS category_parent_name',
            'parent.id AS product_id',
            'parent.product AS product_name',
            'parent.description AS product_description',
            'parent.sku AS product_sku',
            'parent.price AS product_price',
            'parent.type AS product_type',
            'parent.product_condition AS product_condition',
            'parent_unit.product_unit AS product_unit',
            'parent.active AS product_active',
            'pg_parent.id AS product_group_parent_id',
            'pg_parent.active AS product_group_parent_active',
            'pg.id AS product_group_id',
            'pg.product_group AS group_name',
            'pg.required AS group_required',
            'pg.minimum AS group_minimum',
            'pg.maximum AS group_maximum',
            'pg.group_order AS group_order',
            'pg.price_calculation AS group_price_calculation',
            'pg.active AS group_active',
            'pg.show_in_display AS group_show_in_display',
            'pgp.id AS product_group_product_id',
            'child.id AS item_id',
            'child.product AS item_name',
            'child.description AS item_description',
            'child.sku AS item_sku',
            'child.price AS item_price',
            'pgp.quantity AS item_quantity',
            'pgp.product_type AS item_product_type',
            'child_unit.product_unit AS item_unit',
            'pgp.active AS item_active',
            'pgp.show_in_parent_queue AS item_show_in_parent_queue',
        ];

        $fieldSpecs = [
            [
                'alias' => 'ifood_item_id',
                'entityAlias' => 'child',
                'contextParam' => 'ifoodItemContext',
                'fieldParam' => 'ifoodItemFieldName',
                'context' => Order::APP_IFOOD,
                'field' => 'ifood_item_id',
            ],
            [
                'alias' => 'ifood_option_id',
                'entityAlias' => 'child',
                'contextParam' => 'ifoodOptionContext',
                'fieldParam' => 'ifoodOptionFieldName',
                'context' => Order::APP_IFOOD,
                'field' => 'ifood_option_id',
            ],
            [
                'alias' => 'ifood_status',
                'entityAlias' => 'child',
                'contextParam' => 'ifoodStatusContext',
                'fieldParam' => 'ifoodStatusFieldName',
                'context' => Order::APP_IFOOD,
                'field' => 'ifood_status',
            ],
            [
                'alias' => 'food99_code',
                'entityAlias' => 'child',
                'contextParam' => 'food99CodeContext',
                'fieldParam' => 'food99CodeFieldName',
                'context' => Order::APP_FOOD99,
                'field' => 'food99_code',
            ],
            [
                'alias' => 'food99_published',
                'entityAlias' => 'child',
                'contextParam' => 'food99PublishedContext',
                'fieldParam' => 'food99PublishedFieldName',
                'context' => Order::APP_FOOD99,
                'field' => 'food99_published',
            ],
            [
                'alias' => 'ifood_sync_hash',
                'entityAlias' => 'child',
                'contextParam' => 'ifoodSyncHashContext',
                'fieldParam' => 'ifoodSyncHashFieldName',
                'context' => Order::APP_IFOOD,
                'field' => 'sync_hash',
            ],
            [
                'alias' => 'ifood_sync_synced_at',
                'entityAlias' => 'child',
                'contextParam' => 'ifoodSyncSyncedAtContext',
                'fieldParam' => 'ifoodSyncSyncedAtFieldName',
                'context' => Order::APP_IFOOD,
                'field' => 'sync_synced_at',
            ],
            [
                'alias' => 'food99_sync_hash',
                'entityAlias' => 'child',
                'contextParam' => 'food99SyncHashContext',
                'fieldParam' => 'food99SyncHashFieldName',
                'context' => Order::APP_FOOD99,
                'field' => 'sync_hash',
            ],
            [
                'alias' => 'food99_sync_synced_at',
                'entityAlias' => 'child',
                'contextParam' => 'food99SyncSyncedAtContext',
                'fieldParam' => 'food99SyncSyncedAtFieldName',
                'context' => Order::APP_FOOD99,
                'field' => 'sync_synced_at',
            ],
        ];

        $extraSelects = [];
        $extraJoins = [];
        foreach ($fieldSpecs as $spec) {
            [$select, $join, $specParams] = $this->buildExtraDataJoin(
                $spec['alias'],
                $spec['entityAlias'],
                $spec['contextParam'],
                $spec['fieldParam'],
                $spec['context'],
                $spec['field']
            );

            $extraSelects[] = $select;
            $extraJoins[] = $join;
            $params = array_merge($params, $specParams);
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
    %s,
    %s
FROM product_group pg
LEFT JOIN product_group_parent pg_parent
    ON pg_parent.product_group_id = pg.id
LEFT JOIN product parent
    ON parent.id = pg_parent.parent_product_id
LEFT JOIN product_unity parent_unit
    ON parent_unit.id = parent.product_unity_id
LEFT JOIN product_category pc ON pc.id = (
    SELECT MIN(pc2.id)
    FROM product_category pc2
    INNER JOIN category c2 ON c2.id = pc2.category_id
    WHERE pc2.product_id = parent.id
      AND c2.context = :categoryContext
)
	LEFT JOIN category cat ON cat.id = pc.category_id
	LEFT JOIN category cat_parent ON cat_parent.id = cat.parent_id
	LEFT JOIN product_group_product pgp
	    ON %s
	LEFT JOIN product child
	    ON child.id = pgp.product_child_id
	LEFT JOIN product_unity child_unit
	    ON child_unit.id = child.product_unity_id
	%s
WHERE parent.company_id = :companyId
ORDER BY
    COALESCE(cat_parent.name, '') ASC,
    COALESCE(cat.name, '') ASC,
    parent.product ASC,
    COALESCE(pg.group_order, 0) ASC,
    pg.product_group ASC,
    COALESCE(child.product, '') ASC,
    COALESCE(child.id, 0) ASC
SQL,
            implode(",\n    ", $baseSelects),
            implode(",\n    ", $extraSelects),
            $groupProductJoinCondition,
            implode("\n", $extraJoins)
        );

        return $this->entityManager->getConnection()->fetchAllAssociative($sql, $params);
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, string>}
     */
    private function buildExtraDataJoin(
        string $selectAlias,
        string $entityAlias,
        string $contextParam,
        string $fieldParam,
        string $contextValue,
        string $fieldName
    ): array {
        $dataAlias = 'ed_' . preg_replace('/[^a-z0-9_]+/i', '_', $selectAlias);
        $select = sprintf('%s.data_value AS %s', $dataAlias, $selectAlias);

        $join = sprintf(
            <<<'SQL'
LEFT JOIN extra_data %1$s
    ON %1$s.id = (
        SELECT ed2.id
        FROM extra_data ed2
        INNER JOIN extra_fields ef2 ON ef2.id = ed2.extra_fields_id
        WHERE ef2.context = :%2$s
          AND ef2.field_name = :%3$s
          AND LOWER(ed2.entity_name) = 'product'
          AND ed2.entity_id = %4$s.id
        ORDER BY ed2.id DESC
        LIMIT 1
    )
SQL,
            $dataAlias,
            $contextParam,
            $fieldParam,
            $entityAlias
        );

        return [
            $select,
            $join,
            [
                $contextParam => $contextValue,
                $fieldParam => $fieldName,
            ],
        ];
    }

    private function normalizeContext(string $context): string
    {
        return strtolower(trim($context)) === 'supplies' ? 'supplies' : 'products';
    }

    private function normalizeCsvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
