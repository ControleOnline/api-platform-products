<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Category;
use ControleOnline\Entity\Device;
use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductCategory;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupProduct;
use ControleOnline\Entity\ProductUnity;
use ControleOnline\Entity\Spool;
use ControleOnline\Security\ProductAccessPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;

class ProductService
{
    private const PRODUCT_CATEGORY_CONTEXT = 'products';
    private const PRODUCT_TYPES = ['product', 'custom', 'component'];
    private const PRODUCT_CONDITIONS = ['new', 'used', 'refurbished'];
    private const GROUP_PRICE_CALCULATIONS = ['sum'];
    private const GROUP_ITEM_TYPES = ['feedstock', 'component', 'package'];

    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PrintService $printService,
        private PeopleService $PeopleService
    ) {}

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        $myPeople = $this->PeopleService->getMyPeople();
        $accessibleCompanyIds = $this->getAccessibleCompanyIds();

        if (!$myPeople instanceof People && $accessibleCompanyIds === []) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $companyAlias = 'product_company';
        if (!in_array($companyAlias, $queryBuilder->getAllAliases(), true)) {
            $queryBuilder->innerJoin(sprintf('%s.company', $rootAlias), $companyAlias);
        }

        $visibilityConditions = [];

        if ($myPeople instanceof People) {
            $visibilityConditions[] = sprintf('%s.id = :myPeopleId', $companyAlias);
            $queryBuilder->setParameter('myPeopleId', (int) $myPeople->getId());
        }

        if ($accessibleCompanyIds !== []) {
            $visibilityConditions[] = sprintf('%s.id IN(:accessibleCompanyIds)', $companyAlias);
            $queryBuilder->setParameter('accessibleCompanyIds', $accessibleCompanyIds);
        }

        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$visibilityConditions));
    }

    public function getProductsInventory(People $company): array
    {
        $this->denyUnlessCanReadCompany($company);

        return $this->manager->getRepository(Product::class)->getProductsInventory($company);
    }

    public function resolveCompanyReference(mixed $reference): ?People
    {
        return $this->manager->getRepository(People::class)->find(
            $this->normalizeReferenceId($reference)
        );
    }

    public function resolveDeviceReference(mixed $reference): ?Device
    {
        return $this->manager->getRepository(Device::class)->findOneBy([
            'device' => trim((string) $reference),
        ]);
    }

    public function printProductsInventoryFromPayload(array $payload): Spool
    {
        return $this->productsInventoryPrintData(
            $this->requireCompanyReference($payload['people'] ?? null),
            $this->requireDeviceReference($payload['device'] ?? null)
        );
    }

    public function printProductsInventoryFromContent(?string $content): Spool
    {
        return $this->printProductsInventoryFromPayload(
            $this->decodePayload($content)
        );
    }

    public function prePersist(Product $product): Product
    {
        $this->denyUnlessCanManageCatalog($product);

        return $product;
    }

    public function preUpdate(Product $product): Product
    {
        $this->denyUnlessCanManageCatalog($product);

        return $product;
    }

    public function preRemove(Product $product): Product
    {
        $this->denyUnlessCanManageCatalog($product);

        return $product;
    }

    public function productsInventoryPrintData(People $provider, Device $device): Spool
    {
        $products = $this->getProductsInventory($provider);

        $groupedByInventory = [];
        foreach ($products as $product) {
            $inventoryName = $product['inventory_name'];
            if (!isset($groupedByInventory[$inventoryName])) {
                $groupedByInventory[$inventoryName] = [];
            }
            $groupedByInventory[$inventoryName][] = $product;
        }

        foreach ($groupedByInventory as $inventoryName => $items) {
            $companyName = $items[0]['company_name'];
            $this->printService->addLine('', '', '-');
            $this->printService->addLine($companyName, '', ' ');
            $this->printService->addLine('INVENTARIO: ' . $inventoryName, '', ' ');
            $this->printService->addLine('', '', '-');
            $this->printService->addLine('Produto', 'Disponivel', ' ');
            $this->printService->addLine('', '', '-');

            foreach ($items as $item) {
                $productName = substr($item['product_name'], 0, 20);
                if (!empty($item['description'])) {
                    $productName .= ' ' . substr($item['description'], 0, 10);
                }
                $productName .= ' (' . $item['productUnit'] . ')';
                $available = str_pad($item['available'], 4, ' ', STR_PAD_LEFT);
                $this->printService->addLine($productName, $available, ' ');
            }

            $this->printService->addLine('', '', '-');
        }
        return $this->printService->generatePrintData($device, $provider);
    }

    public function getPurchasingSuggestion(People $company)
    {
        $this->denyUnlessCanReadCompany($company);

        return $this->manager->getRepository(Product::class)->getPurchasingSuggestion($company);
    }

    public function printPurchasingSuggestionFromPayload(array $payload): Spool
    {
        return $this->purchasingSuggestionPrintData(
            $this->requireCompanyReference($payload['people'] ?? null),
            $this->requireDeviceReference($payload['device'] ?? null)
        );
    }

    public function printPurchasingSuggestionFromContent(?string $content): Spool
    {
        return $this->printPurchasingSuggestionFromPayload(
            $this->decodePayload($content)
        );
    }

    public function findProductBySkuPayload(array $payload): Product
    {
        if (!isset($payload['sku'], $payload['people'])) {
            throw new BadRequestHttpException('Parâmetros obrigatórios: sku e people');
        }

        $sku = (int) ltrim((string) $payload['sku'], '0');
        $company = $this->resolveCompanyReference($payload['people']);

        if (!$company instanceof People) {
            throw new NotFoundHttpException('Empresa não encontrada');
        }

        $this->denyUnlessCanReadCompany($company);

        $product = $this->manager
            ->getRepository(Product::class)
            ->findProductBySkuAsInteger($sku, $company);

        if (!$product instanceof Product) {
            throw new NotFoundHttpException('Produto não encontrado');
        }

        return $product;
    }

    public function findProductBySkuFromContent(?string $content): Product
    {
        return $this->findProductBySkuPayload($this->decodePayload($content));
    }

    public function purchasingSuggestionPrintData(People $provider, Device $device): Spool
    {
        $products = $this->getPurchasingSuggestion($provider);

        $groupedByCompany = [];
        foreach ($products as $product) {
            $companyName = $product['company_name'];
            if (!isset($groupedByCompany[$companyName])) {
                $groupedByCompany[$companyName] = [];
            }
            $groupedByCompany[$companyName][] = $product;
        }

        $this->printService->addLine('', '', '-');
        $this->printService->addLine('SUGESTAO DE COMPRA', '', ' ');
        $this->printService->addLine('', '', '-');

        foreach ($groupedByCompany as $companyName => $items) {
            $this->printService->addLine($companyName, '', ' ');
            $this->printService->addLine('', '', '-');
            $this->printService->addLine('Produto', 'Necessario', ' ');
            $this->printService->addLine('', '', '-');

            foreach ($items as $item) {
                $productName = substr($item['product_name'], 0, 20);
                if (!empty($item['description'])) {
                    $productName .= ' ' . substr($item['description'], 0, 10);
                }
                if (!empty($item['unity'])) {
                    $productName .= ' (' . $item['unity'] . ')';
                }
                $needed = str_pad($item['needed'], 4, ' ', STR_PAD_LEFT);
                $this->printService->addLine($productName, $needed, ' ');
            }

            $this->printService->addLine('', '', '-');
        }

        return $this->printService->generatePrintData($device, $provider);
    }

    public function importFromCSV(array $row, ?People $company): void
    {
        if (!$company instanceof People) {
            throw new \InvalidArgumentException('Empresa da importacao nao informada.');
        }

        $this->denyUnlessCanManageCompany($company);

        $data = $this->normalizeImportRow($row);
        $this->validateImportRow($data);

        $category = $this->resolveImportCategory($data, $company);
        $product = $this->resolveImportProduct(
            $company,
            $data,
            'product_name',
            'product_description',
            'product_sku',
            'product_price',
            'product_type',
            'product_condition',
            'product_unit',
            'product_active'
        );

        $this->linkProductToCategory($product, $category);

        if (!$this->hasValue($data['group_name'])) {
            $this->manager->flush();
            return;
        }

        $group = $this->resolveImportGroup($product, $data);

        if (!$this->hasValue($data['item_name'])) {
            $this->manager->flush();
            return;
        }

        $item = $this->resolveImportProduct(
            $company,
            $data,
            'item_name',
            'item_description',
            'item_sku',
            'item_price',
            'item_product_type',
            'product_condition',
            'item_unit',
            'item_active',
            'component'
        );

        $this->linkGroupItem($product, $group, $item, $data);
        $this->manager->flush();
    }

    private function normalizeImportRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$key] = is_string($value) ? trim($value) : $value;

            if ($normalized[$key] === '') {
                $normalized[$key] = null;
            }
        }

        return $normalized;
    }

    private function requireCompanyReference(mixed $reference): People
    {
        $company = $this->resolveCompanyReference($reference);
        if (!$company instanceof People) {
            throw new \InvalidArgumentException('Empresa não encontrada');
        }

        return $company;
    }

    private function decodePayload(?string $content): array
    {
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function requireDeviceReference(mixed $reference): Device
    {
        $device = $this->resolveDeviceReference($reference);
        if (!$device instanceof Device) {
            throw new \InvalidArgumentException('Dispositivo não encontrado');
        }

        return $device;
    }

    private function normalizeReferenceId(mixed $reference): int
    {
        return (int) preg_replace('/\D+/', '', (string) $reference);
    }

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

    private function resolveImportCategory(array $data, People $company): Category
    {
        $parent = null;

        if ($this->hasValue($data['category_parent_name'] ?? null)) {
            $parent = $this->findOrCreateCategory($company, $data['category_parent_name'], null);
        }

        return $this->findOrCreateCategory($company, $data['category_name'], $parent);
    }

    private function findOrCreateCategory(People $company, string $name, ?Category $parent): Category
    {
        $criteria = [
            'company' => $company,
            'context' => self::PRODUCT_CATEGORY_CONTEXT,
            'name' => $name,
            'parent' => $parent,
        ];

        $category = $this->manager->getRepository(Category::class)->findOneBy($criteria);

        if ($category instanceof Category) {
            return $category;
        }

        $category = new Category();
        $category->setCompany($company);
        $category->setContext(self::PRODUCT_CATEGORY_CONTEXT);
        $category->setName($name);
        $category->setParent($parent);

        $this->manager->persist($category);

        return $category;
    }

    private function resolveImportProduct(
        People $company,
        array $data,
        string $nameField,
        string $descriptionField,
        string $skuField,
        string $priceField,
        string $typeField,
        string $conditionField,
        string $unitField,
        string $activeField,
        string $defaultType = 'product'
    ): Product {
        $sku = $data[$skuField] ?? null;
        $name = $data[$nameField] ?? null;

        if ($sku !== null) {
            $product = $this->manager->getRepository(Product::class)->findOneBy([
                'company' => $company,
                'sku' => $sku,
            ]);

            if ($product instanceof Product) {
                return $this->applyImportProductData($product, $data, $descriptionField, $priceField, $typeField, $conditionField, $unitField, $activeField, false);
            }
        }

        $product = $this->manager->getRepository(Product::class)->findOneBy([
            'company' => $company,
            'product' => $name,
        ]);

        if (!$product instanceof Product) {
            $product = new Product();
            $product->setCompany($company);
            $product->setProduct($name);
            $this->manager->persist($product);

            return $this->applyImportProductData($product, $data, $descriptionField, $priceField, $typeField, $conditionField, $unitField, $activeField, true, $skuField, $defaultType);
        }

        return $this->applyImportProductData($product, $data, $descriptionField, $priceField, $typeField, $conditionField, $unitField, $activeField, false, $skuField, $defaultType);
    }

    private function applyImportProductData(
        Product $product,
        array $data,
        string $descriptionField,
        string $priceField,
        string $typeField,
        string $conditionField,
        string $unitField,
        string $activeField,
        bool $isNew,
        string $skuField = 'product_sku',
        string $defaultType = 'product'
    ): Product {
        $sku = $data[$skuField] ?? null;
        if ($sku !== null && ($isNew || $product->getSku() === null)) {
            $product->setSku($sku);
        }

        if ($isNew) {
            $product->setDescription('');
            $product->setPrice(0);
            $product->setType($defaultType);
            $product->setProductCondition('new');
            $product->setActive(true);
            $product->setProductUnit($this->resolveProductUnit('UN'));
        }

        if (($data[$descriptionField] ?? null) !== null) {
            $product->setDescription($data[$descriptionField]);
        }

        $price = $this->parseNullableFloat($data[$priceField] ?? null, $priceField);
        if ($price !== null) {
            $product->setPrice($price);
        }

        if (($data[$typeField] ?? null) !== null) {
            $product->setType($data[$typeField]);
        } elseif ($isNew) {
            $product->setType($defaultType);
        }

        if (($data[$conditionField] ?? null) !== null) {
            $product->setProductCondition($data[$conditionField]);
        }

        if (($data[$unitField] ?? null) !== null) {
            $product->setProductUnit($this->resolveProductUnit($data[$unitField]));
        }

        $active = $this->parseNullableBool($data[$activeField] ?? null, $activeField);
        if ($active !== null) {
            $product->setActive($active);
        }

        return $product;
    }

    private function resolveProductUnit(?string $productUnit): ProductUnity
    {
        $productUnit = $productUnit ?: 'UN';

        $unit = $this->manager->getRepository(ProductUnity::class)->findOneBy([
            'productUnit' => $productUnit,
        ]);

        if (!$unit instanceof ProductUnity) {
            throw new \InvalidArgumentException(sprintf('Unidade "%s" nao encontrada.', $productUnit));
        }

        return $unit;
    }

    private function linkProductToCategory(Product $product, Category $category): void
    {
        $link = $this->manager->getRepository(ProductCategory::class)->findOneBy([
            'product' => $product,
            'category' => $category,
        ]);

        if ($link instanceof ProductCategory) {
            return;
        }

        $link = new ProductCategory();
        $link->setProduct($product);
        $link->setCategory($category);
        $this->manager->persist($link);
    }

    private function resolveImportGroup(Product $parentProduct, array $data): ProductGroup
    {
        $group = $this->manager->getRepository(ProductGroup::class)->findOneBy([
            'parentProduct' => $parentProduct,
            'productGroup' => $data['group_name'],
        ]);

        $isNew = !$group instanceof ProductGroup;

        if ($isNew) {
            $group = new ProductGroup();
            $group->setParentProduct($parentProduct);
            $group->setProductGroup($data['group_name']);
            $group->setRequired(false);
            $group->setMinimum(0);
            $group->setMaximum(0);
            $group->setGroupOrder(0);
            $group->setPriceCalculation('sum');
            $group->setActive(true);
            $this->manager->persist($group);
        }

        $required = $this->parseNullableBool($data['group_required'] ?? null, 'group_required');
        $minimum = $this->parseNullableInt($data['group_minimum'] ?? null, 'group_minimum');
        $maximum = $this->parseNullableInt($data['group_maximum'] ?? null, 'group_maximum');

        if ($required === true && $minimum === null) {
            $minimum = 1;
        }

        if ($required !== null) {
            $group->setRequired($required);
        }

        if ($minimum !== null) {
            $group->setMinimum($minimum);
        }

        if ($maximum !== null) {
            $group->setMaximum($maximum);
        }

        $groupOrder = $this->parseNullableInt($data['group_order'] ?? null, 'group_order');
        if ($groupOrder !== null) {
            $group->setGroupOrder($groupOrder);
        }

        if (($data['group_price_calculation'] ?? null) !== null) {
            $group->setPriceCalculation($data['group_price_calculation']);
        }

        $active = $this->parseNullableBool($data['group_active'] ?? null, 'group_active');
        if ($active !== null) {
            $group->setActive($active);
        }

        return $group;
    }

    private function linkGroupItem(Product $parentProduct, ProductGroup $group, Product $item, array $data): void
    {
        $link = $this->manager->getRepository(ProductGroupProduct::class)->findOneBy([
            'productGroup' => $group,
            'productChild' => $item,
        ]);

        if (!$link instanceof ProductGroupProduct) {
            $link = new ProductGroupProduct();
            $link->setProduct($parentProduct);
            $link->setProductGroup($group);
            $link->setProductChild($item);
            $link->setProductType('component');
            $link->setQuantity(1);
            $link->setPrice(0);
            $link->setActive(true);
            $this->manager->persist($link);
        }

        $itemProductType = $data['item_product_type'] ?? null;
        if ($itemProductType !== null) {
            $link->setProductType($itemProductType);
        }

        $itemQuantity = $this->parseNullableFloat($data['item_quantity'] ?? null, 'item_quantity');
        if ($itemQuantity !== null) {
            $link->setQuantity($itemQuantity);
        }

        $itemPrice = $this->parseNullableFloat($data['item_price'] ?? null, 'item_price');
        if ($itemPrice !== null) {
            $link->setPrice($itemPrice);
        }

        $active = $this->parseNullableBool($data['item_active'] ?? null, 'item_active');
        if ($active !== null) {
            $link->setActive($active);
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
    }

    private function denyUnlessCanReadCompany(People $company): void
    {
        if ($this->getAccessPolicy()->canReadCompany(
            (int) $company->getId(),
            $this->normalizePeopleId($this->PeopleService->getMyPeople()),
            $this->getAccessibleCompanyIds()
        )) {
            return;
        }

        throw new AccessDeniedHttpException('Company access denied.');
    }

    private function denyUnlessCanManageCatalog(Product $product): void
    {
        $company = $product->getCompany();
        if (!$company instanceof People) {
            throw new AccessDeniedHttpException('Product company is required.');
        }

        $this->denyUnlessCanManageCompany($company);
    }

    private function denyUnlessCanManageCompany(People $company): void
    {
        if ($this->canManageCompany($company)) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to manage this company catalog.');
    }

    private function canManageCompany(People $company): bool
    {
        if (method_exists($this->PeopleService, 'canAccessCompany')) {
            return (bool) $this->PeopleService->canAccessCompany($company, null, PeopleLink::MANAGER_LINK);
        }

        return $this->getAccessPolicy()->canManageCompany(
            (int) $company->getId(),
            $this->getManagedCompanyIds()
        );
    }

    private function getAccessibleCompanyIds(): array
    {
        if (!method_exists($this->PeopleService, 'getMyCompanies')) {
            return [];
        }

        return $this->extractPeopleIds($this->PeopleService->getMyCompanies());
    }

    private function getManagedCompanyIds(): array
    {
        if (!method_exists($this->PeopleService, 'getMyCompanies')) {
            return [];
        }

        try {
            $companies = $this->PeopleService->getMyCompanies(PeopleLink::MANAGER_LINK);
        } catch (\Throwable) {
            $companies = [];
        }

        return $this->extractPeopleIds($companies);
    }

    private function extractPeopleIds(iterable $companies): array
    {
        $ids = [];

        foreach ($companies as $company) {
            if (!$company instanceof People) {
                continue;
            }

            $companyId = (int) $company->getId();
            if ($companyId > 0) {
                $ids[$companyId] = $companyId;
            }
        }

        return array_values($ids);
    }

    private function normalizePeopleId(mixed $people): ?int
    {
        if (!$people instanceof People) {
            return null;
        }

        $peopleId = (int) $people->getId();

        return $peopleId > 0 ? $peopleId : null;
    }

    private function getAccessPolicy(): ProductAccessPolicy
    {
        return new ProductAccessPolicy();
    }
}
