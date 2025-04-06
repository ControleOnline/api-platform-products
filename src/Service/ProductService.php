<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
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
        $qb = $this->manager->createQueryBuilder();

        $qb->select([
            'p.id as product_id',
            'p.product as product_name',
            'p.description',
            'pu.unity',
            'pi.available',
            'pi.minimum',
            'pi.maximum',
            'c.name as company_name',
            'i.inventory as inventory_name'
        ])
            ->from('ControleOnline\Entity\Product', 'p')
            ->join('p.productUnit', 'pu')
            ->join('p.company', 'c')
            ->join('ControleOnline\Entity\ProductInventory', 'pi', 'WITH', 'pi.product = p.id')
            ->join('pi.inventory', 'i');

        if ($company !== null) {
            $qb->andWhere('p.company = :company')
                ->setParameter('company', $company);
        }

        return $qb->getQuery()->getArrayResult();
    }

    public function productsInventoryPrintData(?People $provider, string $printType, string $deviceType)
    {
        $products = $this->getProductsInventory($provider);

        $groupedByCompany = [];
        foreach ($products as $product) {
            $companyName = $product['company_name'];
            if (!isset($groupedByCompany[$companyName])) {
                $groupedByCompany[$companyName] = [];
            }
            $groupedByCompany[$companyName][] = $product;
        }

        $this->printService->addLine("", "", "-");
        $this->printService->addLine("INVENTARIO DE PRODUTOS", "", " ");
        $this->printService->addLine("", "", "-");

        foreach ($groupedByCompany as $companyName => $items) {
            $this->printService->addLine($companyName, "", " ");
            $this->printService->addLine("", "", "-");
            $this->printService->addLine("Produto", "Disponivel", " ");
            $this->printService->addLine("", "", "-");

            foreach ($items as $item) {
                $productName = substr($item['product_name'], 0, 20);
                if (!empty($item['description'])) {
                    $productName .= " " . substr($item['description'], 0, 10);
                }
                if (!empty($item['unity'])) {
                    $productName .= " (" . $item['unity'] . ")";
                }
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
            $companyName = $product['company_name'] ?? 'Empresa Desconhecida';
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
