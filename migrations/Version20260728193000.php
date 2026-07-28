<?php

declare(strict_types=1);

namespace DoctrineMigrations\Products;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link product showcases to shop domains for multi-shop catalogs.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('product_showcase') || !$this->tableExists('people_domain')) {
            return;
        }

        if (!$this->columnExists('product_showcase', 'people_domain_id')) {
            $this->addSql('ALTER TABLE `product_showcase` ADD `people_domain_id` int(11) DEFAULT NULL AFTER `company_id`');
        }

        if (!$this->indexExists('product_showcase', 'product_showcase_people_domain')) {
            $this->addSql('ALTER TABLE `product_showcase` ADD KEY `product_showcase_people_domain` (`people_domain_id`)');
        }

        if (!$this->foreignKeyExists('product_showcase', 'product_showcase_people_domain_fk')) {
            $this->addSql('ALTER TABLE `product_showcase` ADD CONSTRAINT `product_showcase_people_domain_fk` FOREIGN KEY (`people_domain_id`) REFERENCES `people_domain` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
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

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        );
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$tableName, $constraintName, 'FOREIGN KEY']
        );
    }
}
