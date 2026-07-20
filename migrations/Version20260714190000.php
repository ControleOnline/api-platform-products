<?php

declare(strict_types=1);

namespace DoctrineMigrations\Products;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Baseline schema for products module from s.controleonline.com";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('CREATE TABLE IF NOT EXISTS `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory` varchar(50) CHARACTER SET utf8 NOT NULL,
  `type` enum(\'sales\',\'internal\',\'consignment\',\'damaged\') CHARACTER SET utf8 NOT NULL DEFAULT \'internal\',
  `people_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `people_id` (`people_id`),
  CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`people_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `product` varchar(255) CHARACTER SET utf8 NOT NULL,
  `queue_id` int(11) DEFAULT NULL,
  `description` text CHARACTER SET utf8 NOT NULL,
  `sku` varchar(32) CHARACTER SET utf8 DEFAULT NULL,
  `type` enum(\'manufactured\',\'custom\',\'product\',\'service\',\'component\',\'feedstock\',\'package\',\'preparation\') CHARACTER SET utf8 NOT NULL DEFAULT \'product\',
  `price` float NOT NULL DEFAULT \'0\',
  `product_unity_id` int(11) NOT NULL,
  `product_condition` enum(\'new\',\'used\',\'recondicioned\') CHARACTER SET utf8 NOT NULL DEFAULT \'new\',
  `featured` tinyint(1) NOT NULL DEFAULT \'0\',
  `active` tinyint(1) NOT NULL DEFAULT \'1\',
  `default_out_inventory_id` int(11) DEFAULT NULL,
  `default_in_inventory_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`sku`),
  KEY `product_ unit_id` (`product_unity_id`),
  KEY `queue_id` (`queue_id`),
  KEY `out_inventory_id` (`default_out_inventory_id`) USING BTREE,
  KEY `in_inventory_id` (`default_in_inventory_id`) USING BTREE,
  CONSTRAINT `product_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_ibfk_2` FOREIGN KEY (`product_unity_id`) REFERENCES `product_unity` (`id`),
  CONSTRAINT `product_ibfk_3` FOREIGN KEY (`queue_id`) REFERENCES `queue` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `product_ibfk_4` FOREIGN KEY (`default_out_inventory_id`) REFERENCES `inventory` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `product_ibfk_5` FOREIGN KEY (`default_in_inventory_id`) REFERENCES `inventory` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`,`category_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `product_category_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_category_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1667 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_file` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`,`file_id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `product_file_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_file_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1902 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_group` varchar(255) CHARACTER SET utf8 NOT NULL,
  `price_calculation` enum(\'sum\',\'average\',\'biggest\',\'free\') CHARACTER SET utf8 NOT NULL DEFAULT \'sum\',
  `required` tinyint(1) NOT NULL DEFAULT \'0\',
  `minimum` float NOT NULL,
  `maximum` float NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT \'1\',
  `show_in_display` tinyint(1) NOT NULL DEFAULT \'0\',
  `group_order` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_group_company_id` (`company_id`),
  CONSTRAINT `product_group_company_id_fk` FOREIGN KEY (`company_id`) REFERENCES `people` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_group_parent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_group_id` int(11) NOT NULL,
  `parent_product_id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_group_parent_unique` (`product_group_id`,`parent_product_id`),
  KEY `product_group_parent_product_id` (`parent_product_id`),
  KEY `IDX_1BB5D390198093C5` (`product_group_id`),
  CONSTRAINT `FK_1BB5D390198093C5` FOREIGN KEY (`product_group_id`) REFERENCES `product_group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_1BB5D390727ACA70` FOREIGN KEY (`parent_product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=412 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_group_product` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `product_group_id` int(11) DEFAULT NULL,
  `product_type` enum(\'feedstock\',\'component\',\'package\') CHARACTER SET utf8 NOT NULL,
  `product_child_id` int(11) NOT NULL,
  `quantity` decimal(10,3) DEFAULT NULL,
  `price` float NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT \'1\',
  `show_in_parent_queue` tinyint(1) NOT NULL DEFAULT \'1\',
  `shared_scope_group_id` int(11) NOT NULL DEFAULT \'0\',
  `feedstock_scope_product_id` int(11) NOT NULL DEFAULT \'0\',
  `quantity_scope` decimal(10,3) NOT NULL DEFAULT \'0.000\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_group_product_identity_unique` (`shared_scope_group_id`,`feedstock_scope_product_id`,`product_type`,`product_child_id`,`quantity_scope`),
  KEY `product_id` (`product_child_id`),
  KEY `product_id_2` (`product_id`),
  KEY `product_group_product_group_lookup` (`product_group_id`,`product_type`,`product_child_id`,`quantity`),
  KEY `product_group_product_feedstock_lookup` (`product_id`,`product_type`,`product_child_id`,`quantity`),
  CONSTRAINT `product_group_product_ibfk_2` FOREIGN KEY (`product_child_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_group_product_ibfk_4` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `product_group_product_ibfk_5` FOREIGN KEY (`product_group_id`) REFERENCES `product_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1860 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `available` float NOT NULL,
  `sales` float NOT NULL,
  `purchases` float NOT NULL,
  `transit` float NOT NULL,
  `minimum` float NOT NULL,
  `maximum` float NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_id` (`inventory_id`,`product_id`) USING BTREE,
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_inventory_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_inventory_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1604 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_material` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material` varchar(500) CHARACTER SET utf8 NOT NULL,
  `revised` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material` (`material`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_people` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `product_id` int(10) NOT NULL,
  `people_id` int(10) NOT NULL,
  `role` enum(\'supplier\',\'manufacturer\',\'distributor\') NOT NULL DEFAULT \'supplier\',
  `cost_price` decimal(10,2) DEFAULT NULL,
  `lead_time_days` int(11) DEFAULT NULL,
  `supplier_sku` varchar(100) DEFAULT NULL,
  `priority` int(11) DEFAULT \'1\',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`,`people_id`,`supplier_sku`) USING BTREE,
  KEY `product_people_ibfk_2` (`people_id`),
  CONSTRAINT `product_people_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_people_ibfk_2` FOREIGN KEY (`people_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=198 DEFAULT CHARSET=utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS `product_unity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_unit` varchar(3) CHARACTER SET utf8 NOT NULL,
  `unit_type` enum(\'I\',\'F\') CHARACTER SET utf8 NOT NULL DEFAULT \'I\' COMMENT \'Integer, Fractioned\',
  `description` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_product_unit_id` tinyint(3) unsigned DEFAULT NULL,
  `proportion` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_unit` (`product_unit`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        return;
    }
}
