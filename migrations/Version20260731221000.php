<?php

declare(strict_types=1);

namespace DoctrineMigrations\Products;

use ControleOnline\Migration\TenantAwareMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260731221000 extends TenantAwareMigration
{
    private const TABLES = [
        'product_category',
        'product_group_product',
        'product_showcase_item',
    ];

    public function getDescription(): string
    {
        return 'Add canonical order to product placements in categories, groups and showcases.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $tableName) {
            if ($this->tableExists($tableName) && !$this->columnExists($tableName, 'sort_order')) {
                $this->addSql(sprintf('ALTER TABLE `%s` ADD `sort_order` INT DEFAULT NULL', $tableName));
            }
        }
    }

    public function down(Schema $schema): void
    {
        return;
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        );
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$tableName, $columnName]
        );
    }
}
