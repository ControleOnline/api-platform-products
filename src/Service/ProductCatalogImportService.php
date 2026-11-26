<?php
namespace ControleOnline\Service;

use ControleOnline\Entity\Category;
use ControleOnline\Entity\Device;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductCategory;
use ControleOnline\Entity\ProductGroup;
use ControleOnline\Entity\ProductGroupParent;
use ControleOnline\Entity\ProductGroupProduct;
use ControleOnline\Entity\ProductUnity;
use ControleOnline\Entity\Spool;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
as Security;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductCatalogImportService
{
    use ProductImportValueNormalizer;
    private const PRODUCT_CATEGORY_CONTEXT = 'products';
    private const PRODUCT_TYPES = ['product', 'custom', 'component', 'service'];
    private const PRODUCT_CONDITIONS = ['new', 'used', 'refurbished'];
    private const GROUP_PRICE_CALCULATIONS = ['sum', 'average', 'biggest', 'free'];
    private const GROUP_ITEM_TYPES = ['feedstock', 'component', 'package'];

    public function __construct(private EntityManagerInterface $manager, private ProductCatalogAccessService $catalogAccess) {}

    public function importFromCSV(array $row, ?People $company): void
    {
        $this->catalogAccess->assertManageCompany($company);
        if (!$company instanceof People) {
            throw new \InvalidArgumentException('Empresa da importacao nao informada.');
        }

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

        $group = $this->resolveImportGroup($product, $company, $data);

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

    private function resolveImportGroup(Product $parentProduct, People $company, array $data): ProductGroup
    {
        $group = $this->manager->getRepository(ProductGroup::class)
            ->findSharedByNameAndCompany($data['group_name'], $company);

        $isNew = !$group instanceof ProductGroup;

        if ($isNew) {
            $group = new ProductGroup();
            $group->setCompany($company);
            $group->setProductGroup($data['group_name']);
            $group->setRequired(false);
            $group->setMinimum(0);
            $group->setMaximum(0);
            $group->setGroupOrder(0);
            $group->setPriceCalculation('sum');
            $group->setActive(true);
            $group->setShowInDisplay(false);
            $this->manager->persist($group);
        }

        $this->linkParentProductToGroup($parentProduct, $group);

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

    private function linkParentProductToGroup(Product $parentProduct, ProductGroup $group): void
    {
        $link = $this->manager->getRepository(ProductGroupParent::class)->findOneBy([
            'parentProduct' => $parentProduct,
            'productGroup' => $group,
        ]);

        if (!$link instanceof ProductGroupParent) {
            $link = new ProductGroupParent();
            $link->setParentProduct($parentProduct);
            $link->setProductGroup($group);
            $this->manager->persist($link);
        }

        $link->setActive(true);
    }

    private function linkGroupItem(Product $parentProduct, ProductGroup $group, Product $item, array $data): void
    {
        $productType = $data['item_product_type'] ?? null;
        $itemQuantity = $this->parseNullableFloat($data['item_quantity'] ?? null, 'item_quantity');
        $itemPrice = $this->parseNullableFloat($data['item_price'] ?? null, 'item_price');
        $quantity = $itemQuantity ?? 1.0;
        $groupProductRepository = $this->manager->getRepository(ProductGroupProduct::class);
        $link = $groupProductRepository->findSharedGroupItem($group, $item, $productType, $quantity);

        if (!$link instanceof ProductGroupProduct) {
            $link = new ProductGroupProduct();
            $link->setProductGroup($group);
            $link->setProductChild($item);
            $link->setProductType('component');
            $link->setQuantity($quantity);
            $link->setPrice(0);
            $link->setActive(true);
            $this->manager->persist($link);
        }

        if ($productType !== null) {
            $link->setProductType($productType);
        }

        if ($itemPrice !== null) {
            $link->setPrice($itemPrice);
        }

        $active = $this->parseNullableBool($data['item_active'] ?? null, 'item_active');
        if ($active !== null) {
            $link->setActive($active);
        }

        $showInParentQueue = $this->parseNullableBool($data['item_show_in_parent_queue'] ?? null, 'item_show_in_parent_queue');
        if ($showInParentQueue !== null) {
            $link->setShowInParentQueue($showInParentQueue);
        }

        if (($link->getProductType() ?? 'component') === 'feedstock') {
            $link->setProduct($parentProduct);
        } else {
            $link->setProduct(null);
        }
    }

}
