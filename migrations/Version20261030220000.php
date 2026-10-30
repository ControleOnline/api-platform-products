<?php

declare(strict_types=1);

namespace DoctrineMigrations\Products;

use ControleOnline\Migration\TenantAwareMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261030220000 extends TenantAwareMigration
{
    public function getDescription(): string
    {
        return 'product_showcase_inventory: link showcases (marketplaces) to warehouses';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('product_showcase_inventory')) {
            return;
        }
        if (!$this->tableExists('product_showcase') || !$this->tableExists('inventory')) {
            return;
        }

        $this->addSql('CREATE TABLE product_showcase_inventory (
            product_showcase_id INT NOT NULL,
            inventory_id INT NOT NULL,
            PRIMARY KEY (product_showcase_id, inventory_id),
            INDEX idx_psi_inventory (inventory_id),
            CONSTRAINT fk_psi_showcase FOREIGN KEY (product_showcase_id) REFERENCES product_showcase (id) ON DELETE CASCADE,
            CONSTRAINT fk_psi_inventory FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('product_showcase_inventory')) {
            $this->addSql('DROP TABLE product_showcase_inventory');
        }
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
    }
}
