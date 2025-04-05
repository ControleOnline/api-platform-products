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


    public function updateProductInventory(): void
    {

        $sql = "INSERT INTO `product_inventory` (
                `inventory_id`, 
                `product_id`, 
                `product_unity_id`, 
                `available`, 
                `ordered`, 
                `transit`, 
                `minimum`, 
                `maximum`, 
                `sales`
            )
            SELECT 
                op.`inventory_id`,
                op.`product_id`,
                p.`product_unity_id`,
                (SUM(CASE WHEN o.`order_type` = 'purchasing' AND o.`status_id` IN (:purchasing_status) THEN op.`quantity` ELSE 0 END) - 
                 SUM(CASE WHEN o.`order_type` = 'sale' AND o.`status_id` IN (:sales_status) THEN op.`quantity` ELSE 0 END)) AS `available`,
                SUM(CASE WHEN o.`order_type` = 'purchasing' AND o.`status_id` IN (:ordered_status) THEN op.`quantity` ELSE 0 END) AS `ordered`,
                SUM(CASE WHEN o.`order_type` = 'purchasing' AND o.`status_id` IN (:transit_status) THEN op.`quantity` ELSE 0 END) AS `transit`,
                0 AS `minimum`,
                0 AS `maximum`,
                SUM(CASE WHEN o.`order_type` = 'sale' AND o.`status_id` IN (:sales_status) THEN op.`quantity` ELSE 0 END) AS `sales`
            FROM `orders` o
            JOIN `order_product` op ON o.`id` = op.`order_id`
            JOIN `product` p ON op.`product_id` = p.`id`
            WHERE o.`order_type` IN ('purchasing', 'sale')
              AND o.`status_id` IN (:all_status)
              AND p.`type` IN ('product', 'feedstock')
              AND (
                  (o.`order_type` = 'sale' AND o.`provider_id` IN (:provider_id))
                  OR
                  (o.`order_type` = 'purchasing' AND o.`client_id` IN (:client_id))
              )
            GROUP BY op.`inventory_id`, op.`product_id`, p.`product_unity_id`
            ON DUPLICATE KEY UPDATE
                `available` = VALUES(`available`),
                `ordered` = VALUES(`ordered`),
                `transit` = VALUES(`transit`),
                `sales` = VALUES(`sales`)
        ";


        $purchasingStatus = [7];
        $orderedStatus = [5];
        $transitStatus = [6];
        $salesStatus = [6];
        $companies = [1, 2, 3];
        $allStatus = array_unique(array_merge($purchasingStatus, $orderedStatus, $transitStatus, $salesStatus));

        try {
            $stmt = $this->manager->getConnection()->prepare($sql);

            $stmt->bindValue('purchasing_status', implode(',', $purchasingStatus), \PDO::PARAM_STR);
            $stmt->bindValue('sales_status', implode(',', $salesStatus), \PDO::PARAM_STR);
            $stmt->bindValue('ordered_status', implode(',', $orderedStatus), \PDO::PARAM_STR);
            $stmt->bindValue('transit_status', implode(',', $transitStatus), \PDO::PARAM_STR);
            $stmt->bindValue('all_status', implode(',', $allStatus), \PDO::PARAM_STR);
            $stmt->bindValue('provider_id', implode(',', $companies), \PDO::PARAM_STR);
            $stmt->bindValue('client_id', implode(',', $companies), \PDO::PARAM_STR);

            $stmt->executeQuery();
        } catch (\Exception $e) {
            throw new \Exception("Erro ao atualizar o estoque: " . $e->getMessage());
        }
    }
}
