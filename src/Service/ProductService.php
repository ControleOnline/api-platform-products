<?php

/*
 * Contract imported from AGENTS.md
 * ## Escopo
 * - Modulo de produtos e estoque.
 * - Cobre `Product`, categorias, grupos, arquivos, inventario e relacoes do produto com outras entidades.
 *
 * ## Quando usar
 * - Prompts sobre produto, categoria, inventario, estoque, anexos de produto e estrutura de catalogo.
 *
 * ## Limites
 * - Regras de venda e pedido pertencem a `orders`.
 * - Regras de fila operacional de preparo pertencem a `queue`.
 * - Os metadados de grupo de produto (`priceCalculation`, `required`, `minimum`, `maximum`) e a quantidade/preco padrao de `product_group_product` formam o contrato de catalogo consumido pela tela de customizacao no frontend. Mudancas nesses campos precisam manter a leitura previsivel para `CustomizeScreen`.
 * - O endpoint publico de catalogo do shop deve entregar categorias, produtos por categoria e sinalizacao de grupos em lote para evitar uma requisicao de produtos por categoria no frontend.
 * - `ProductGroup.showInDisplay` e um metadado operacional de visibilidade. O backend deve persistir o campo, os novos grupos devem nascer com `false` e a leitura de catalogo/preview precisa respeitar o valor salvo sem quebrar o agrupamento dos itens.
 * - `extra_data` e `extra_fields` nao sao destino para novo estado de catalogo, sincronizacao ou configuracao de produto. O unico uso aceitavel e legado para IDs, chaves remotas e codigos que ainda nao tenham coluna ou tabela canonica; qualquer outro dado deve ser materializado na entidade dona e removido depois do backfill.
 *
 * ## Regras de seguranca e autorizacao
 * - Entidade analisada: `Product`.
 * - Service correspondente: `src/Service/ProductService.php`.
 * - `ProductService::securityFilter()` e obrigatorio e precisa aplicar filtro real de leitura e escrita. Metodo vazio, comentado ou apenas nominal nao conta como protecao valida.
 * - `Product` nao deve depender apenas de `Get` ou `GetCollection` com `PUBLIC_ACCESS`, nem de `Put`/`Delete` guardados so por `ROLE_HUMAN`, para expor ou alterar catalogo de empresa.
 * - Leitura de `Product` deve ficar restrita ao contexto de empresa realmente acessivel ao ator autenticado ou a regra administrativa equivalente explicitamente comprovada.
 * - Criacao, edicao e exclusao de `Product` devem exigir autorizacao explicita para gerir catalogo/estoque da empresa alvo; nao basta estar autenticado nem informar `company` arbitraria no payload.
 * - Entidade analisada: `ProductPeople`.
 * - Service correspondente: `src/Service/ProductPeopleService.php` ou camada equivalente que proteja a relacao.
 * - Toda criacao, edicao e exclusao de `ProductPeople` precisa validar ao mesmo tempo o direito de gerir o `Product` alvo e o direito de vincular a `People` alvo como fornecedor/fabricante/distribuidor. `ROLE_HUMAN` isolado nao e protecao suficiente.
 * - Quando o frontend abrir criacao de produto contextualizada por fornecedor, o primeiro salvamento so pode materializar a relacao `supplier` se o backend reaplicar essa fronteira por identidade autenticada. Nao confiar em `people` ou `product` enviados pelo cliente como prova de autorizacao.
 * - Para `type=service`, a API precisa rejeitar em persistencia e atualizacao unidades fisicas incompativeis e aceitar apenas unidades de cobranca coerentes com execucao unica ou recorrencia.
 * - Em edicao de legado, manter a unidade antiga visivel no frontend pode ser aceitavel para preservar contexto, mas a persistencia de novo valor invalido continua proibida no backend.
 */


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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductService
{
    private const PRODUCT_CATEGORY_CONTEXT = 'products';
    private const PRODUCT_TYPES = ['product', 'custom', 'component'];
    private const PRODUCT_CONDITIONS = ['new', 'used', 'refurbished'];
    private const GROUP_PRICE_CALCULATIONS = ['sum', 'average', 'biggest', 'free'];
    private const GROUP_ITEM_TYPES = ['feedstock', 'component', 'package'];

    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PrintService $printService,
        private PeopleService $PeopleService
    ) {}

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        $this->PeopleService->checkCompany('company', $queryBuilder, $resourceClass, $applyTo, $rootAlias);
    }

    public function prePersist(Product $product): void
    {
        $this->assertCanManageProduct($product);
    }

    public function preUpdate(Product $product): void
    {
        $this->assertCanManageProduct($product);
    }

    public function preRemove(Product $product): void
    {
        $this->assertCanManageProduct($product);
    }

    public function assertCanManageProduct(Product $product): void
    {
        $company = $product->getCompany();

        if (!$company instanceof People) {
            throw new BadRequestHttpException('Empresa não encontrada para o produto informado.');
        }

        if (!$this->PeopleService->canAccessCompany($company)) {
            throw new AccessDeniedHttpException('Você não pode gerir o catálogo desta empresa.');
        }
    }

    public function getProductsInventory(People $company): array
    {
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
            $this->printService->addLine("", "", "-");
            $this->printService->addLine($companyName, "", " ");
            $this->printService->addLine("INVENTARIO: " . $inventoryName, "", " ");
            $this->printService->addLine("", "", "-");
            $this->printService->addLine("Produto", "Disponivel", " ");
            $this->printService->addLine("", "", "-");

            foreach ($items as $item) {
                $productName = substr($item['product_name'], 0, 20);
                if (!empty($item['description'])) {
                    $productName .= " " . substr($item['description'], 0, 10);
                }
                $productName .= " (" . $item['productUnit'] . ")";
                $available = str_pad($item['available'], 4, " ", STR_PAD_LEFT);
                $this->printService->addLine($productName, $available, " ");
            }

            $this->printService->addLine("", "", "-");
        }
        return $this->printService->generatePrintData($device, $provider);
    }

    public function getPurchasingSuggestion(People $company)
    {
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

    public function printProductLabelFromPayload(array $payload): Spool
    {
        return $this->productLabelPrintData(
            $this->requireCompanyReference($payload['people'] ?? null),
            $this->requireDeviceReference($payload['device'] ?? null),
            $payload
        );
    }

    public function printProductLabelFromContent(?string $content): Spool
    {
        return $this->printProductLabelFromPayload(
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

        $this->printService->addLine("", "", "-");
        $this->printService->addLine("SUGESTAO DE COMPRA", "", " ");
        $this->printService->addLine("", "", "-");

        foreach ($groupedByCompany as $companyName => $items) {
            $this->printService->addLine($companyName, "", " ");
            $this->printService->addLine("", "", "-");
            $this->printService->addLine("Produto", "Necessario", " ");
            $this->printService->addLine("", "", "-");

            foreach ($items as $item) {
                $productName = substr($item['product_name'], 0, 20);
                if (!empty($item['description'])) {
                    $productName .= " " . substr($item['description'], 0, 10);
                }
                if (!empty($item['unity'])) {
                    $productName .= " (" . $item['unity'] . ")";
                }
                $needed = str_pad($item['needed'], 4, " ", STR_PAD_LEFT);
                $this->printService->addLine($productName, $needed, " ");
            }

            $this->printService->addLine("", "", "-");
        }

        return $this->printService->generatePrintData($device, $provider);
    }

    public function productLabelPrintData(People $provider, Device $device, array $label): Spool
    {
        $lines = $this->resolveProductLabelLines($label);

        if (empty($lines)) {
            throw new \InvalidArgumentException('Conteúdo da etiqueta não informado.');
        }

        $this->printService->addLine("", "", "-");
        foreach ($lines as $line) {
            foreach ($this->wrapPrintLine($line) as $wrappedLine) {
                $this->printService->addLine($wrappedLine, "", " ");
            }
        }
        $this->printService->addLine("", "", "-");

        return $this->printService->generatePrintData($device, $provider, [
            'label' => 'product-label',
        ]);
    }

    public function importFromCSV(array $row, ?People $company): void
    {
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

    private function resolveProductLabelLines(array $label): array
    {
        $labelText = trim((string) ($label['labelText'] ?? ''));

        if ($labelText !== '') {
            return $this->normalizePrintLines(explode("\n", $labelText));
        }

        $productName = trim((string) ($label['productName'] ?? ''));
        $handlingDate = trim((string) ($label['handlingDate'] ?? ''));
        $expirationDate = trim((string) ($label['expirationDate'] ?? ''));
        $freeText = trim((string) ($label['freeText'] ?? ''));

        $lines = [];
        if ($productName !== '') {
            $lines[] = function_exists('mb_strtoupper')
                ? mb_strtoupper($productName, 'UTF-8')
                : strtoupper($productName);
        }
        if ($handlingDate !== '') {
            $lines[] = 'MANEJO: ' . $handlingDate;
        }
        if ($expirationDate !== '') {
            $lines[] = 'VALIDADE: ' . $expirationDate;
        }
        if ($freeText !== '') {
            $lines[] = '';
            $lines[] = $freeText;
        }

        return $this->normalizePrintLines($lines);
    }

    private function normalizePrintLines(array $lines): array
    {
        return array_values(array_filter(
            array_map(
                fn($line) => trim((string) $line),
                $lines
            ),
            fn($line) => $line !== ''
        ));
    }

    private function wrapPrintLine(string $line): array
    {
        $wrapped = wordwrap($line, 40, "\n", true);
        $lines = explode("\n", $wrapped);

        return $this->normalizePrintLines($lines);
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
}
