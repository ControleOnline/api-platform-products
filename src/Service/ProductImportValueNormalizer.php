<?php
namespace ControleOnline\Service;

trait ProductImportValueNormalizer
{
    private function validateImportRow(array $data): void
    {
        if (!$this->hasValue($data['category_name'] ?? null)) {
            throw new \InvalidArgumentException('category_name e obrigatorio.');
        }

        if (!$this->hasValue($data['product_name'] ?? null)) {
            throw new \InvalidArgumentException('product_name e obrigatorio.');
        }

        $hasGroupFields = $this->hasAnyValue([
            $data['group_name'] ?? null,
            $data['group_required'] ?? null,
            $data['group_minimum'] ?? null,
            $data['group_maximum'] ?? null,
            $data['group_order'] ?? null,
            $data['group_price_calculation'] ?? null,
            $data['group_active'] ?? null,
        ]);

        $hasItemFields = $this->hasAnyValue([
            $data['item_name'] ?? null,
            $data['item_description'] ?? null,
            $data['item_sku'] ?? null,
            $data['item_price'] ?? null,
            $data['item_quantity'] ?? null,
            $data['item_product_type'] ?? null,
            $data['item_unit'] ?? null,
            $data['item_active'] ?? null,
            $data['item_show_in_parent_queue'] ?? null,
        ]);

        if ($hasItemFields && !$this->hasValue($data['group_name'] ?? null)) {
            throw new \InvalidArgumentException('item_* exige group_name preenchido.');
        }

        if ($hasGroupFields && !$this->hasValue($data['group_name'] ?? null)) {
            throw new \InvalidArgumentException('Campos de grupo exigem group_name preenchido.');
        }

        if (($data['product_type'] ?? null) !== null) {
            $this->assertAllowedValue($data['product_type'], self::PRODUCT_TYPES, 'product_type');
        }

        if (($data['item_product_type'] ?? null) !== null) {
            $this->assertAllowedValue($data['item_product_type'], self::GROUP_ITEM_TYPES, 'item_product_type');
        }

        if (($data['product_condition'] ?? null) !== null) {
            $this->assertAllowedValue($data['product_condition'], self::PRODUCT_CONDITIONS, 'product_condition');
        }

        if (($data['group_price_calculation'] ?? null) !== null) {
            $this->assertAllowedValue(
                $data['group_price_calculation'],
                self::GROUP_PRICE_CALCULATIONS,
                'group_price_calculation'
            );
        }

        $minimum = $this->parseNullableInt($data['group_minimum'] ?? null, 'group_minimum');
        $maximum = $this->parseNullableInt($data['group_maximum'] ?? null, 'group_maximum');
        $itemQuantity = $this->parseNullableFloat($data['item_quantity'] ?? null, 'item_quantity');

        if ($this->parseNullableBool($data['group_required'] ?? null, 'group_required') === true && $minimum === null) {
            $minimum = 1;
        }

        if ($maximum !== null && $minimum !== null && $maximum < $minimum) {
            throw new \InvalidArgumentException('group_maximum nao pode ser menor que group_minimum.');
        }

        if ($itemQuantity !== null && $itemQuantity <= 0) {
            throw new \InvalidArgumentException('item_quantity deve ser maior que zero.');
        }
    }

    private function parseNullableFloat(mixed $value, string $field): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);

        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException(sprintf('%s precisa ser numerico.', $field));
        }

        return (float) $normalized;
    }

    private function parseNullableInt(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException(sprintf('%s precisa ser inteiro.', $field));
        }

        return (int) $value;
    }

    private function parseNullableBool(mixed $value, string $field): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        $map = [
            '1' => true,
            '0' => false,
            'true' => true,
            'false' => false,
            'yes' => true,
            'no' => false,
            'sim' => true,
            'nao' => false,
            'não' => false,
        ];

        if (!array_key_exists($normalized, $map)) {
            throw new \InvalidArgumentException(sprintf('%s precisa ser booleano.', $field));
        }

        return $map[$normalized];
    }

    private function assertAllowedValue(string $value, array $allowedValues, string $field): void
    {
        if (!in_array($value, $allowedValues, true)) {
            throw new \InvalidArgumentException(
                sprintf('%s invalido. Valores aceitos: %s.', $field, implode(', ', $allowedValues))
            );
        }
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    private function hasAnyValue(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->hasValue($value)) {
                return true;
            }
        }

        return false;
    }}
