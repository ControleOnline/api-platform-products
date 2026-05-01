[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/controleonline/api-platform-products/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/controleonline/api-platform-products/?branch=master)

# products

`composer require controleonline/products:dev-master`

## Service import

Add the module service import in `config/services.yaml`:

```yaml
imports:
    - { resource: "../modules/controleonline/orders/products/services/products.yaml" }
```

## Product access rules

This module now enforces the product catalog access rules in the backend.

- product reads are limited to companies the authenticated actor can actually access
- create, update, delete and CSV import require catalog-management access for the target company
- inventory, SKU lookup and purchase-suggestion endpoints reuse the same company visibility rule

## Service billing units

For products with `type=service`, the backend only accepts billing units compatible with one-time or recurring service charging.

Examples of compatible units:

- `unitario`
- `hora`
- `diaria`
- `semanal`
- `mensal`

Examples of incompatible physical units:

- `litro`
- `grama`
- `fracao`

The entity validation rejects incompatible units during persist and update, even if a request bypasses the frontend form restrictions.

## Validation

The pull request checks for this module currently cover:

- `composer validate --no-check-publish`
- `composer install --no-interaction --prefer-dist`
- `vendor/bin/phpunit`
