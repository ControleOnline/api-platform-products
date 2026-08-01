<?php

declare(strict_types=1);

namespace DoctrineMigrations\Products;

use ControleOnline\Migration\TenantAwareMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260801120000 extends TenantAwareMigration
{
    public function getDescription(): string
    {
        return 'Add compact operational presentation settings to product groups.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('product_group')) {
            return;
        }

        if (!$this->columnExists('product_group', 'customization_type')) {
            $this->addSql("ALTER TABLE `product_group` ADD `customization_type` VARCHAR(16) NOT NULL DEFAULT 'neutral'");
        }
        if (!$this->columnExists('product_group', 'show_in_print')) {
            $this->addSql('ALTER TABLE `product_group` ADD `show_in_print` TINYINT(1) DEFAULT NULL');
        }
        if (!$this->columnExists('product_group', 'show_unit_quantity')) {
            $this->addSql('ALTER TABLE `product_group` ADD `show_unit_quantity` TINYINT(1) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        return;
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $column]);
    }
}
