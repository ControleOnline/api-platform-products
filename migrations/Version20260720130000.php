<?php

declare(strict_types=1);

namespace DoctrineMigrations\Products;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product showcases and showcase items for channel-specific catalog pricing without tenant data backfill.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_showcase` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `integration_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_store_code` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_showcase_company_integration_name` (`company_id`,`integration_key`,`name`),
  KEY `product_showcase_company_integration_active` (`company_id`,`integration_key`,`active`),
  CONSTRAINT `product_showcase_company_fk` FOREIGN KEY (`company_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE IF NOT EXISTS `product_showcase_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `showcase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `out_inventory_id` int(11) DEFAULT NULL,
  `external_code` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `published` tinyint(1) NOT NULL DEFAULT 0,
  `sync_hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_synced_at` datetime DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_showcase_item_showcase_product` (`showcase_id`,`product_id`),
  KEY `product_showcase_item_product_active` (`product_id`,`active`),
  KEY `product_showcase_item_inventory` (`out_inventory_id`),
  CONSTRAINT `product_showcase_item_showcase_fk` FOREIGN KEY (`showcase_id`) REFERENCES `product_showcase` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_showcase_item_product_fk` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_showcase_item_out_inventory_fk` FOREIGN KEY (`out_inventory_id`) REFERENCES `inventory` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    }

    public function down(Schema $schema): void
    {
        return;
    }
}
