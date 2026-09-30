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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PrintService $printService,
        private PeopleService $PeopleService,
        private ProductCatalogAccessService $catalogAccess,
        private ProductCatalogImportService $catalogImport
    ) {}

    /** Compatibility entry point retained for ProductPeople and older callers. */
    public function assertCanManageProduct(Product $product): void
    {
        $this->catalogAccess->assertManageCompany($product->getCompany());
    }

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        $this->catalogAccess->filter($queryBuilder, Product::class, $rootAlias);
    }

    public function getProductsInventory(People $company): array
    {
        $this->catalogAccess->assertReadCompany($company);
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
        $this->catalogAccess->assertReadCompany($company);
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

        $this->catalogAccess->assertReadCompany($company);

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
        $this->catalogAccess->assertReadCompany($provider);
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
        $this->catalogImport->importFromCSV($row, $company);
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


}
