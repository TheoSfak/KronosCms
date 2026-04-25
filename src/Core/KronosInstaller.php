<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * KronosInstaller — Database schema bootstrapper.
 * Creates all required tables on first install and runs migrations on update.
 * Uses CREATE TABLE IF NOT EXISTS + ALTER TABLE for safe upgrades (dbDelta equivalent).
 */
class KronosInstaller
{
    private KronosDB $db;

    public function __construct(KronosDB $db)
    {
        $this->db = $db;
    }

    /**
     * Run full installation: create all tables.
     * Safe to call multiple times (IF NOT EXISTS guards).
     */
    public function install(): void
    {
        $statements = $this->getSchemaStatements();
        $results = $this->db->runSchema($statements);

        foreach ($results as $sql => $result) {
            if ($result !== 'ok') {
                error_log("[KronosInstaller] Schema error on: " . substr($sql, 0, 80) . " — {$result}");
            }
        }
    }

    /**
     * Run migrations for updates (called by SelfUpdater after file replacement).
     * Add new ALTER TABLE statements here as the schema evolves.
     */
    public function migrate(): void
    {
        // Future schema migrations go here.
        // Example: $this->db->runSchema(["ALTER TABLE `kronos_posts` ADD COLUMN `meta` JSON NULL"]);
    }

    /**
     * @return string[]
     */
    private function getSchemaStatements(): array
    {
        return [
            // ---------------------------------------------------------------
            // Options store
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_options` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `option_key`   VARCHAR(191) NOT NULL,
                `option_value` LONGTEXT NOT NULL,
                `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_option_key` (`option_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // Users
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_users` (
                `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `username`        VARCHAR(60) NOT NULL,
                `email`           VARCHAR(191) NOT NULL,
                `password_hash`   VARCHAR(255) NOT NULL,
                `role`            ENUM('app_manager','app_editor','app_user') NOT NULL DEFAULT 'app_user',
                `display_name`    VARCHAR(120) NOT NULL DEFAULT '',
                `avatar_url`      VARCHAR(500) NOT NULL DEFAULT '',
                `last_login_at`   DATETIME NULL,
                `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_username` (`username`),
                UNIQUE KEY `uq_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // Builder layouts (JSON AST)
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_builder_layouts` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `layout_name`  VARCHAR(191) NOT NULL,
                `layout_type`  VARCHAR(60) NOT NULL DEFAULT 'page',
                `json_data`    LONGTEXT NOT NULL,
                `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_layout_type` (`layout_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // AI chat logs
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_ai_logs` (
                `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`    INT UNSIGNED NOT NULL,
                `session_id` VARCHAR(64) NOT NULL,
                `role`       ENUM('user','assistant','system') NOT NULL DEFAULT 'user',
                `content`    LONGTEXT NOT NULL,
                `model`      VARCHAR(60) NOT NULL DEFAULT '',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_session_id` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // Analytics events
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_analytics` (
                `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_type` VARCHAR(60) NOT NULL,
                `entity_id`  INT UNSIGNED NULL,
                `user_id`    INT UNSIGNED NULL,
                `meta`       JSON NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_event_type` (`event_type`),
                KEY `idx_entity_id` (`entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // CMS Posts
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_posts` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title`       VARCHAR(500) NOT NULL DEFAULT '',
                `slug`        VARCHAR(191) NOT NULL,
                `content`     LONGTEXT NOT NULL,
                `post_type`   VARCHAR(60) NOT NULL DEFAULT 'page',
                `status`      ENUM('draft','published','scheduled','private','archived') NOT NULL DEFAULT 'draft',
                `author_id`   INT UNSIGNED NULL,
                `layout_id`   INT UNSIGNED NULL COMMENT 'FK to kronos_builder_layouts',
                `meta`        JSON NULL,
                `published_at` DATETIME NULL,
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_post_slug_type` (`slug`, `post_type`),
                KEY `idx_post_type_status` (`post_type`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // Taxonomy terms
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_terms` (
                `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`      VARCHAR(191) NOT NULL,
                `slug`      VARCHAR(191) NOT NULL,
                `taxonomy`  VARCHAR(60) NOT NULL DEFAULT 'category',
                `parent_id` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_term_slug_taxonomy` (`slug`, `taxonomy`),
                KEY `idx_taxonomy` (`taxonomy`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // Term relationships (post <-> term)
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_term_relationships` (
                `post_id` INT UNSIGNED NOT NULL,
                `term_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`post_id`, `term_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // Revisions
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_post_revisions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `post_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `title` VARCHAR(500) NOT NULL DEFAULT '',
                `content` LONGTEXT NOT NULL,
                `meta` JSON NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_revision_post` (`post_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // Navigation menus
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_menus` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(191) NOT NULL,
                `slug` VARCHAR(191) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_menu_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `kronos_menu_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `menu_id` INT UNSIGNED NOT NULL,
                `parent_id` INT UNSIGNED NULL,
                `title` VARCHAR(191) NOT NULL,
                `url` VARCHAR(500) NOT NULL DEFAULT '',
                `item_type` VARCHAR(40) NOT NULL DEFAULT 'custom',
                `object_type` VARCHAR(60) NOT NULL DEFAULT '',
                `object_id` INT UNSIGNED NULL,
                `target` VARCHAR(20) NOT NULL DEFAULT '_self',
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_menu_sort` (`menu_id`, `sort_order`),
                KEY `idx_menu_object` (`object_type`, `object_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // Media library
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_media` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `file_name` VARCHAR(255) NOT NULL,
                `file_path` VARCHAR(500) NOT NULL,
                `file_url` VARCHAR(500) NOT NULL,
                `mime_type` VARCHAR(120) NOT NULL DEFAULT '',
                `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
                `width` INT UNSIGNED NULL,
                `height` INT UNSIGNED NULL,
                `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
                `caption` TEXT NULL,
                `uploaded_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_media_file_url` (`file_url`),
                KEY `idx_media_mime` (`mime_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // E-Commerce: Products
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_products` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`        VARCHAR(500) NOT NULL DEFAULT '',
                `slug`        VARCHAR(191) NOT NULL,
                `description` LONGTEXT NOT NULL,
                `short_desc`  TEXT NOT NULL DEFAULT '',
                `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `sale_price`  DECIMAL(10,2) NULL,
                `sku`         VARCHAR(100) NOT NULL DEFAULT '',
                `stock`       INT NOT NULL DEFAULT 0,
                `manage_stock` TINYINT(1) NOT NULL DEFAULT 1,
                `status`      ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
                `images`      JSON NULL,
                `meta`        JSON NULL,
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_product_slug` (`slug`),
                KEY `idx_product_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // E-Commerce: Customers
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_customers` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`    INT UNSIGNED NULL,
                `first_name` VARCHAR(100) NOT NULL DEFAULT '',
                `last_name`  VARCHAR(100) NOT NULL DEFAULT '',
                `email`      VARCHAR(191) NOT NULL,
                `phone`      VARCHAR(30) NOT NULL DEFAULT '',
                `meta`       JSON NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_customer_user_id` (`user_id`),
                KEY `idx_customer_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // E-Commerce: Orders (HPOS-inspired)
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_orders` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_number`   VARCHAR(60) NOT NULL,
                `customer_id`    INT UNSIGNED NULL,
                `status`         ENUM('pending','processing','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
                `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `tax`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `shipping`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `payment_method` VARCHAR(60) NOT NULL DEFAULT '',
                `payment_intent` VARCHAR(255) NOT NULL DEFAULT '',
                `currency`       CHAR(3) NOT NULL DEFAULT 'USD',
                `meta`           JSON NULL,
                `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_order_number` (`order_number`),
                KEY `idx_order_customer` (`customer_id`),
                KEY `idx_order_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // E-Commerce: Order Items
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_order_items` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id`    INT UNSIGNED NOT NULL,
                `product_id`  INT UNSIGNED NOT NULL,
                `product_name` VARCHAR(500) NOT NULL DEFAULT '',
                `qty`         INT NOT NULL DEFAULT 1,
                `unit_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `meta`        JSON NULL,
                PRIMARY KEY (`id`),
                KEY `idx_order_items_order` (`order_id`),
                KEY `idx_order_items_product` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------------------------------------------------------------
            // E-Commerce: Order Addresses
            // ---------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS `kronos_order_addresses` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id`     INT UNSIGNED NOT NULL,
                `address_type` ENUM('billing','shipping') NOT NULL DEFAULT 'billing',
                `first_name`   VARCHAR(100) NOT NULL DEFAULT '',
                `last_name`    VARCHAR(100) NOT NULL DEFAULT '',
                `company`      VARCHAR(200) NOT NULL DEFAULT '',
                `address_1`    VARCHAR(255) NOT NULL DEFAULT '',
                `address_2`    VARCHAR(255) NOT NULL DEFAULT '',
                `city`         VARCHAR(100) NOT NULL DEFAULT '',
                `state`        VARCHAR(100) NOT NULL DEFAULT '',
                `postcode`     VARCHAR(20) NOT NULL DEFAULT '',
                `country`      CHAR(2) NOT NULL DEFAULT '',
                `phone`        VARCHAR(30) NOT NULL DEFAULT '',
                `email`        VARCHAR(191) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `idx_addr_order_id` (`order_id`),
                KEY `idx_addr_type` (`address_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }
}
