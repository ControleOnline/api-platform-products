<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
 AS Security;
use Doctrine\ORM\QueryBuilder;

class ProductService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private PrintService $printService,
        private PeopleService $PeopleService
    ) {}

    public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
    {
        // $this->PeopleService->checkCompany('company', $queryBuilder, $resourceClass, $applyTo, $rootAlias);
    }

    public function getProductsInventory(?People $company): array
    {
        return $this->manager->getRepository(Product::class)->getProductsInventory($company);
    }

    public function productsInventoryPrintData(?People $provider, string $printType, string $deviceType)
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
            $companyName = $items[0]['company_name'] ;
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

        return $this->printService->generatePrintData($printType, $deviceType);
    }

    public function getPurchasingSuggestion(People $company)
    {
        return $this->manager->getRepository(Product::class)->getPurchasingSuggestion($company);
    }

    public function purchasingSuggestionPrintData(?People $provider, string $printType, string $deviceType)
    {
        $products = $this->getPurchasingSuggestion($provider);

        $groupedByCompany = [];
        foreach ($products as $product) {
            $companyName = $product['company_name'] ;
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

        return $this->printService->generatePrintData($printType, $deviceType);
    }
}
