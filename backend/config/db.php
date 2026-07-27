<?php
/**
 * Database Connection & Migrations
 */

class DB {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            // Detect if running locally or on production server
            $isLocal = false;
            $httpHost = $_SERVER['HTTP_HOST'] ?? '';
            if (
                in_array($httpHost, ['localhost', '127.0.0.1', 'localhost:5000']) ||
                php_sapi_name() === 'cli'
            ) {
                $isLocal = true;
            }

            if ($isLocal) {
                $defaultHost = '127.0.0.1'; // Using IP instead of localhost avoids socket issues
                $defaultUser = 'root';
                $defaultPass = '';
                $defaultDb   = 'multitenant_pos';
            } else {
                $defaultHost = 'sql309.infinityfree.com';
                $defaultUser = 'if0_42451104';
                $defaultPass = 'I8Kw8aZkldJO'; // Your production DB password
                $defaultDb   = 'if0_42451104_codexxa_pos';
            }

            // Only use environment variables if a .env file actually exists in the project.
            // This prevents hosting provider defaults (e.g. DB_HOST=localhost) from overriding our values.
            $envFileExists = false;
            $envPaths = [
                dirname(__DIR__) . '/.env',
                dirname(dirname(__DIR__)) . '/.env'
            ];
            foreach ($envPaths as $envPath) {
                if (file_exists($envPath)) {
                    $envFileExists = true;
                    break;
                }
            }

            if ($envFileExists) {
                $host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : (getenv('DB_HOST') ?: $defaultHost);
                $user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : (getenv('DB_USER') ?: $defaultUser);
                $pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : $defaultPass);
                $dbName = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : (getenv('DB_NAME') ?: $defaultDb);
            } else {
                $host = $defaultHost;
                $user = $defaultUser;
                $pass = $defaultPass;
                $dbName = $defaultDb;
            }
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
 
            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
                self::$pdo->exec("SET time_zone = '+06:00';");
                self::runMigrations();
            } catch (\PDOException $e) {
                // If database does not exist, attempt to create it
                if ($e->getCode() == 1049) {
                    try {
                        $tempDsn = "mysql:host=$host;charset=$charset";
                        $tempPdo = new PDO($tempDsn, $user, $pass, $options);
                        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        
                        self::$pdo = new PDO($dsn, $user, $pass, $options);
                        self::$pdo->exec("SET time_zone = '+06:00';");
                        self::runMigrations();
                    } catch (\PDOException $ex) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Database connection/creation failed: ' . $ex->getMessage()]);
                        exit;
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
                    exit;
                }
            }
        }
        return self::$pdo;
    }

    public static function query($sql, $params = []) {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function beginTransaction() {
        return self::getConnection()->beginTransaction();
    }

    public static function commit() {
        return self::getConnection()->commit();
    }

    public static function rollBack() {
        return self::getConnection()->rollBack();
    }

    public static function lastInsertId() {
        return self::getConnection()->lastInsertId();
    }

    private static function runMigrations() {
        $pdo = self::$pdo;

        // Helper to check if column exists
        $columnExists = function($table, $column) use ($pdo) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                return $stmt->rowCount() > 0;
            } catch (\PDOException $e) {
                return false;
            }
        };

        // Helper to check if table exists
        $tableExists = function($table) use ($pdo) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                return $stmt->rowCount() > 0;
            } catch (\PDOException $e) {
                return false;
            }
        };

        try {
            // Verify core tables exist
            if (!$tableExists('shops')) {
                // Read from schema.sql if exists to initialize base schema
                $schemaFile = dirname(__DIR__, 2) . '/database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
            }

            // Check if allowed_sections column exists on users table
            if ($tableExists('users') && !$columnExists('users', 'allowed_sections')) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `allowed_sections` TEXT NULL");
            }

            // Check if unit column exists on products table
            if ($tableExists('products') && !$columnExists('products', 'unit')) {
                $pdo->exec("ALTER TABLE `products` ADD COLUMN `unit` VARCHAR(20) NOT NULL DEFAULT 'piece'");
            }

            // Check if category column exists on products table
            if ($tableExists('products') && !$columnExists('products', 'category')) {
                $pdo->exec("ALTER TABLE `products` ADD COLUMN `category` VARCHAR(100) NULL");
            }

            // Check if due_balance column exists on suppliers table
            if ($tableExists('suppliers') && !$columnExists('suppliers', 'due_balance')) {
                $pdo->exec("ALTER TABLE `suppliers` ADD COLUMN `due_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            }

            // Check if payment_basis column exists on purchase_orders table
            if ($tableExists('purchase_orders') && !$columnExists('purchase_orders', 'payment_basis')) {
                $pdo->exec("ALTER TABLE `purchase_orders` ADD COLUMN `payment_basis` ENUM('cash', 'credit') NOT NULL DEFAULT 'cash'");
            }

            // Check if expiry_date column exists on purchase_order_items table
            if ($tableExists('purchase_order_items') && !$columnExists('purchase_order_items', 'expiry_date')) {
                $pdo->exec("ALTER TABLE `purchase_order_items` ADD COLUMN `expiry_date` DATE NULL");
            }

            // Check if quantity_ordered column exists on purchase_order_items table (handle column name mismatch)
            if ($tableExists('purchase_order_items') && $columnExists('purchase_order_items', 'quantity') && !$columnExists('purchase_order_items', 'quantity_ordered')) {
                $pdo->exec("ALTER TABLE `purchase_order_items` CHANGE COLUMN `quantity` `quantity_ordered` INT NOT NULL");
            }

            // Check if quantity_received column exists on purchase_order_items table
            if ($tableExists('purchase_order_items') && !$columnExists('purchase_order_items', 'quantity_received')) {
                $pdo->exec("ALTER TABLE `purchase_order_items` ADD COLUMN `quantity_received` INT NULL");
            }

            // Check if cost_price column exists on purchase_order_items table
            if ($tableExists('purchase_order_items') && !$columnExists('purchase_order_items', 'cost_price')) {
                // If unit_price exists, rename it to cost_price
                if ($columnExists('purchase_order_items', 'unit_price')) {
                    $pdo->exec("ALTER TABLE `purchase_order_items` CHANGE COLUMN `unit_price` `cost_price` DECIMAL(10,2) NOT NULL");
                } else {
                    $pdo->exec("ALTER TABLE `purchase_order_items` ADD COLUMN `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
                }
            }

            // Check if selling_price column exists on purchase_order_items table
            if ($tableExists('purchase_order_items') && !$columnExists('purchase_order_items', 'selling_price')) {
                $pdo->exec("ALTER TABLE `purchase_order_items` ADD COLUMN `selling_price` DECIMAL(10,2) NULL");
            }

            // Check if subtotal column exists on purchase_order_items table
            if ($tableExists('purchase_order_items') && !$columnExists('purchase_order_items', 'subtotal')) {
                $pdo->exec("ALTER TABLE `purchase_order_items` ADD COLUMN `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            }

            // Check if paid_amount column exists on purchase_orders table
            if ($tableExists('purchase_orders') && !$columnExists('purchase_orders', 'paid_amount')) {
                $pdo->exec("ALTER TABLE `purchase_orders` ADD COLUMN `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            }

            // Check if due_amount column exists on purchase_orders table
            if ($tableExists('purchase_orders') && !$columnExists('purchase_orders', 'due_amount')) {
                $pdo->exec("ALTER TABLE `purchase_orders` ADD COLUMN `due_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            }

            // Check if cost_price column exists on sale_items table
            if ($tableExists('sale_items') && !$columnExists('sale_items', 'cost_price')) {
                $pdo->exec("ALTER TABLE `sale_items` ADD COLUMN `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            }

            // Create supplier_returns table if not exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `supplier_returns` (
                    `id` INT AUTO_INCREMENT,
                    `shop_id` INT NOT NULL,
                    `supplier_id` INT NOT NULL,
                    `product_id` INT NOT NULL,
                    `quantity` INT NOT NULL,
                    `action_type` ENUM('return', 'replace') NOT NULL,
                    `notes` TEXT NULL,
                    `new_expiry_date` DATE NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `fk_supplier_returns_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_supplier_returns_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_supplier_returns_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Create customer_returns table if not exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `customer_returns` (
                    `id` INT AUTO_INCREMENT,
                    `shop_id` INT NOT NULL,
                    `customer_id` INT NULL,
                    `sale_id` INT NULL,
                    `product_id` INT NOT NULL,
                    `quantity` INT NOT NULL,
                    `refund_amount` DECIMAL(10,2) NOT NULL,
                    `refund_method` VARCHAR(30) NOT NULL DEFAULT 'cash',
                    `notes` TEXT NULL,
                    `deduct_from_due` TINYINT(1) NOT NULL DEFAULT 0,
                    `amount_deducted_from_due` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `fk_customer_returns_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_customer_returns_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_customer_returns_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_customer_returns_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            if ($tableExists('customer_returns') && !$columnExists('customer_returns', 'refund_method')) {
                $pdo->exec("ALTER TABLE `customer_returns` ADD COLUMN `refund_method` VARCHAR(30) NOT NULL DEFAULT 'cash'");
            }

            if ($tableExists('customer_returns') && !$columnExists('customer_returns', 'amount_deducted_from_due')) {
                $pdo->exec("ALTER TABLE `customer_returns` ADD COLUMN `amount_deducted_from_due` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            }

            // Check if logo column exists on users table
            if ($tableExists('users') && !$columnExists('users', 'logo')) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `logo` LONGTEXT NULL");
            }

            // Check if logo column exists on shops table
            if ($tableExists('shops') && !$columnExists('shops', 'logo')) {
                $pdo->exec("ALTER TABLE `shops` ADD COLUMN `logo` LONGTEXT NULL");
            }

            // Create due_payments table if not exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `due_payments` (
                    `id` INT AUTO_INCREMENT,
                    `shop_id` INT NOT NULL,
                    `customer_id` INT NOT NULL,
                    `sale_id` INT NULL,
                    `amount` DECIMAL(10,2) NOT NULL,
                    `payment_method` ENUM('cash', 'card', 'mobile_pay', 'other') NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `fk_due_payments_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_due_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_due_payments_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            if ($tableExists('due_payments') && !$columnExists('due_payments', 'transaction_reference')) {
                $pdo->exec("ALTER TABLE `due_payments` ADD COLUMN `transaction_reference` VARCHAR(255) NULL");
                $pdo->exec("ALTER TABLE `due_payments` ADD COLUMN `note` TEXT NULL");
            }

            // Modify held_bills discount_percent column to DECIMAL(10,2) to accommodate flat discounts
            if ($tableExists('held_bills')) {
                $pdo->exec("ALTER TABLE `held_bills` MODIFY COLUMN `discount_percent` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
                if (!$columnExists('held_bills', 'discount_amount')) {
                    $pdo->exec("ALTER TABLE `held_bills` ADD COLUMN `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
                }
            }

            // Create other_sales table if not exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `other_sales` (
                    `id` INT AUTO_INCREMENT,
                    `shop_id` INT NOT NULL,
                    `title` VARCHAR(255) NULL,
                    `customer_name` VARCHAR(255) NULL,
                    `customer_phone` VARCHAR(50) NULL,
                    `items` TEXT NULL,
                    `amount` DECIMAL(10,2) NOT NULL,
                    `sale_date` DATE NOT NULL,
                    `notes` TEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `fk_other_sales_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            // Alter existing table if we just changed the schema (for backwards compatibility during development)
            if ($tableExists('other_sales') && !$columnExists('other_sales', 'items')) {
                $pdo->exec("ALTER TABLE `other_sales` ADD COLUMN `customer_name` VARCHAR(255) NULL");
                $pdo->exec("ALTER TABLE `other_sales` ADD COLUMN `customer_phone` VARCHAR(50) NULL");
                $pdo->exec("ALTER TABLE `other_sales` ADD COLUMN `items` TEXT NULL");
                $pdo->exec("ALTER TABLE `other_sales` CHANGE COLUMN `title` `title` VARCHAR(255) NULL");
            }

            // Create manual_orders table if not exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `manual_orders` (
                    `id` INT AUTO_INCREMENT,
                    `shop_id` INT NOT NULL,
                    `salesman_name` VARCHAR(255) NOT NULL,
                    `customer_id` INT NULL,
                    `customer_name` VARCHAR(255) NULL,
                    `customer_phone` VARCHAR(50) NULL,
                    `customer_address` TEXT NULL,
                    `payment_method` ENUM('cash', 'credit') NOT NULL DEFAULT 'cash',
                    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `notes` TEXT NULL,
                    `status` ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
                    `sale_id` INT NULL,
                    `created_by` INT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `fk_manual_orders_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_manual_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_manual_orders_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_manual_orders_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            if ($tableExists('manual_orders') && !$columnExists('manual_orders', 'customer_name')) {
                $pdo->exec("ALTER TABLE `manual_orders` ADD COLUMN `customer_name` VARCHAR(255) NULL");
                $pdo->exec("ALTER TABLE `manual_orders` ADD COLUMN `customer_phone` VARCHAR(50) NULL");
                $pdo->exec("ALTER TABLE `manual_orders` ADD COLUMN `customer_address` TEXT NULL");
            }

            // Create manual_order_items table if not exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `manual_order_items` (
                    `id` INT AUTO_INCREMENT,
                    `order_id` INT NOT NULL,
                    `shop_id` INT NOT NULL,
                    `product_id` INT NOT NULL,
                    `quantity` INT NOT NULL,
                    `unit_price` DECIMAL(10,2) NOT NULL,
                    `subtotal` DECIMAL(10,2) NOT NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `fk_manual_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `manual_orders` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_manual_order_items_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_manual_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Create inventory_adjustments table if not exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `inventory_adjustments` (
                    `id` INT AUTO_INCREMENT,
                    `shop_id` INT NOT NULL,
                    `product_id` INT NOT NULL,
                    `previous_quantity` INT NOT NULL,
                    `adjusted_quantity` INT NOT NULL,
                    `difference` INT NOT NULL,
                    `adjustment_type` ENUM('increase', 'decrease') NOT NULL,
                    `reason` VARCHAR(255) NOT NULL,
                    `notes` TEXT NULL,
                    `adjusted_by` INT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX `idx_inventory_adjustments_shop` (`shop_id`),
                    INDEX `idx_inventory_adjustments_product` (`product_id`),
                    INDEX `idx_inventory_adjustments_date` (`created_at`),
                    CONSTRAINT `fk_inventory_adjustments_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_inventory_adjustments_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_inventory_adjustments_user` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            // Modify quantity columns to DECIMAL(10,3) to support fractional quantities
            $tablesToAlter = [
                'manual_order_items' => 'quantity',
                'sale_items' => 'quantity',
                'purchase_order_items' => 'quantity',
                'supplier_returns' => 'quantity',
                'customer_returns' => 'quantity',
                'wastages' => 'quantity'
            ];
            foreach ($tablesToAlter as $tbl => $col) {
                if ($tableExists($tbl)) {
                    try {
                        $pdo->exec("ALTER TABLE `$tbl` MODIFY COLUMN `$col` DECIMAL(10,3) NOT NULL");
                    } catch (\Exception $e) {}
                }
            }
            if ($tableExists('products')) {
                try {
                    $pdo->exec("ALTER TABLE `products` MODIFY COLUMN `stock_quantity` DECIMAL(10,3) NOT NULL DEFAULT '0.000'");
                    $pdo->exec("ALTER TABLE `products` MODIFY COLUMN `low_stock_threshold` DECIMAL(10,3) NOT NULL DEFAULT '5.000'");
                } catch (\Exception $e) {}
            }
            if ($tableExists('inventory_adjustments')) {
                try {
                    $pdo->exec("ALTER TABLE `inventory_adjustments` MODIFY COLUMN `previous_quantity` DECIMAL(10,3) NOT NULL");
                    $pdo->exec("ALTER TABLE `inventory_adjustments` MODIFY COLUMN `adjusted_quantity` DECIMAL(10,3) NOT NULL");
                    $pdo->exec("ALTER TABLE `inventory_adjustments` MODIFY COLUMN `difference` DECIMAL(10,3) NOT NULL");
                } catch (\Exception $e) {}
            }

            // Check if notes column exists on sales table
            if ($tableExists('sales') && !$columnExists('sales', 'notes')) {
                $pdo->exec("ALTER TABLE `sales` ADD COLUMN `notes` TEXT NULL");
            }

            // Create site_settings table if not exists
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `site_settings` (
                        `setting_key` VARCHAR(50) NOT NULL,
                        `setting_value` TEXT NOT NULL,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`setting_key`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (\Exception $e) {
                error_log("Failed to create site_settings table: " . $e->getMessage());
            }

            // Create pricing_plans table if not exists
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `pricing_plans` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `name` VARCHAR(100) NOT NULL,
                        `description` TEXT,
                        `price` DECIMAL(10, 2) NOT NULL,
                        `period` VARCHAR(20) DEFAULT 'month',
                        `features` JSON,
                        `is_popular` TINYINT(1) DEFAULT 0,
                        `is_custom` TINYINT(1) DEFAULT 0,
                        `button_text` VARCHAR(50) DEFAULT 'Get Started',
                        `sort_order` INT DEFAULT 0,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (\Exception $e) {
                error_log("Failed to create pricing_plans table: " . $e->getMessage());
            }

            // Add is_custom column to pricing_plans if it doesn't exist (migration for existing tables)
            if ($tableExists('pricing_plans') && !$columnExists('pricing_plans', 'is_custom')) {
                $pdo->exec("ALTER TABLE `pricing_plans` ADD COLUMN `is_custom` TINYINT(1) DEFAULT 0 AFTER `is_popular`");
            }

            // Insert default pricing plans only if table is empty
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `pricing_plans`");
                $count = $stmt->fetchColumn();
                if ($count == 0) {
                    $pdo->exec("
                        INSERT INTO `pricing_plans` (`name`, `description`, `price`, `period`, `features`, `is_popular`, `is_custom`, `button_text`, `sort_order`) VALUES
                        ('Starter', 'Perfect for small businesses', 29.00, 'month', '[\"1 Shop Location\", \"Up to 500 Products\", \"2 Staff Accounts\", \"Basic Analytics\", \"Email Support\"]', 0, 0, 'Get Started', 1),
                        ('Professional', 'For growing businesses', 79.00, 'month', '[\"Up to 5 Shop Locations\", \"Unlimited Products\", \"10 Staff Accounts\", \"Advanced Analytics\", \"Priority Support\", \"API Access\"]', 1, 0, 'Get Started', 2),
                        ('Enterprise', 'For large organizations', 199.00, 'month', '[\"Unlimited Shop Locations\", \"Unlimited Products\", \"Unlimited Staff Accounts\", \"Custom Analytics\", \"24/7 Dedicated Support\", \"White-label Solution\", \"Custom Integrations\"]', 0, 0, 'Contact Sales', 3)
                    ");
                }
            } catch (\Exception $e) {
                error_log("Failed to insert default pricing plans: " . $e->getMessage());
            }

            // Insert default site settings only if they don't exist
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `site_settings`");
                $count = $stmt->fetchColumn();
                if ($count == 0) {
                    $pdo->exec("
                        INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
                        ('site_name', 'CodexaaPos++'),
                        ('site_description', 'Modern Point of Sale For Your Business'),
                        ('hero_content', 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.')
                    ");
                }
            } catch (\Exception $e) {
                error_log("Failed to insert default site settings: " . $e->getMessage());
            }

            // Seed Super Admin if table has no users
            $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'super_admin'");
            if ($stmt->fetchColumn() == 0) {
                $pdo->exec("
                    INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `status`)
                    VALUES ('Super Admin', 'mk.rabbani.cse@gmail.com', '$2a$10\$Jek6c.Ov3IBnEWQ45ImT5.XDEI7bmLlsqYL69nFhY.T0zgaGqfsIO', 'super_admin', 'active')
                ");
            }

            // Add loyalty program settings to shops table
            if ($tableExists('shops') && !$columnExists('shops', 'loyalty_enabled')) {
                $pdo->exec("ALTER TABLE `shops` ADD COLUMN `loyalty_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether loyalty program is enabled (0=disabled, 1=enabled)'");
            }
            if ($tableExists('shops') && !$columnExists('shops', 'loyalty_point_earn_rate')) {
                $pdo->exec("ALTER TABLE `shops` ADD COLUMN `loyalty_point_earn_rate` DECIMAL(10,2) NOT NULL DEFAULT 100.00 COMMENT 'Amount of purchase required to earn 1 loyalty point'");
            }
            if ($tableExists('shops') && !$columnExists('shops', 'loyalty_point_value')) {
                $pdo->exec("ALTER TABLE `shops` ADD COLUMN `loyalty_point_value` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Monetary value of 1 loyalty point'");
            }

            // Add loyalty_points column to customers table if not exists
            if ($tableExists('customers') && !$columnExists('customers', 'loyalty_points')) {
                $pdo->exec("ALTER TABLE `customers` ADD COLUMN `loyalty_points` INT NOT NULL DEFAULT 0 COMMENT 'Customer loyalty points balance'");
            }

            // Add loyalty columns to sales table if not exists
            if ($tableExists('sales') && !$columnExists('sales', 'points_earned')) {
                $pdo->exec("ALTER TABLE `sales` ADD COLUMN `points_earned` INT NOT NULL DEFAULT 0 COMMENT 'Loyalty points earned from this sale'");
            }
            if ($tableExists('sales') && !$columnExists('sales', 'points_redeemed')) {
                $pdo->exec("ALTER TABLE `sales` ADD COLUMN `points_redeemed` INT NOT NULL DEFAULT 0 COMMENT 'Loyalty points redeemed in this sale'");
            }
            if ($tableExists('sales') && !$columnExists('sales', 'points_redeemed_value')) {
                $pdo->exec("ALTER TABLE `sales` ADD COLUMN `points_redeemed_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monetary value of redeemed points'");
            }

            // Create plan_purchases table if not exists
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `plan_purchases` (
                        `id` INT AUTO_INCREMENT,
                        `plan_id` INT NOT NULL,
                        `shop_id` INT NULL,
                        `user_name` VARCHAR(100) NOT NULL,
                        `user_email` VARCHAR(100) NOT NULL,
                        `user_phone` VARCHAR(20) NULL,
                        `payment_method` VARCHAR(50) NULL,
                        `payment_method_id` INT NULL,
                        `notes` TEXT NULL,
                        `transaction_id` VARCHAR(100) NULL,
                        `bank_name` VARCHAR(100) NULL,
                        `account_number` VARCHAR(50) NULL,
                        `card_last_four` VARCHAR(4) NULL,
                        `payment_proof` VARCHAR(255) NULL,
                        `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        INDEX `idx_plan_purchases_plan` (`plan_id`),
                        INDEX `idx_plan_purchases_shop` (`shop_id`),
                        INDEX `idx_plan_purchases_status` (`status`),
                        INDEX `idx_plan_purchases_date` (`created_at`),
                        CONSTRAINT `fk_plan_purchases_plan`
                            FOREIGN KEY (`plan_id`)
                            REFERENCES `pricing_plans` (`id`)
                            ON DELETE RESTRICT
                            ON UPDATE CASCADE,
                        CONSTRAINT `fk_plan_purchases_shop`
                            FOREIGN KEY (`shop_id`)
                            REFERENCES `shops` (`id`)
                            ON DELETE SET NULL
                            ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (\Exception $e) {
                error_log("Failed to create plan_purchases table: " . $e->getMessage());
            }

            // Add payment detail columns to plan_purchases table if they don't exist
            if ($tableExists('plan_purchases')) {
                if (!$columnExists('plan_purchases', 'bank_name')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `bank_name` VARCHAR(100) NULL");
                }
                if (!$columnExists('plan_purchases', 'account_number')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `account_number` VARCHAR(50) NULL");
                }
                if (!$columnExists('plan_purchases', 'card_last_four')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `card_last_four` VARCHAR(4) NULL");
                }
                if (!$columnExists('plan_purchases', 'payment_proof')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `payment_proof` TEXT NULL");
                }
                if (!$columnExists('plan_purchases', 'payment_method_id')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `payment_method_id` INT NULL");
                }
                // Add shop registration fields to plan_purchases table if they don't exist
                if (!$columnExists('plan_purchases', 'shop_name')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `shop_name` VARCHAR(100) NULL");
                }
                if (!$columnExists('plan_purchases', 'shop_address')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `shop_address` VARCHAR(255) NULL");
                }
                if (!$columnExists('plan_purchases', 'shop_phone')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `shop_phone` VARCHAR(20) NULL");
                }
                if (!$columnExists('plan_purchases', 'shop_city')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `shop_city` VARCHAR(100) NULL");
                }
                if (!$columnExists('plan_purchases', 'shop_country')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `shop_country` VARCHAR(100) NULL");
                }
                // Fix status ENUM if it has wrong values (migration from old schema)
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM `plan_purchases` LIKE 'status'");
                    $columnInfo = $stmt->fetch();
                    if ($columnInfo) {
                        $type = $columnInfo['Type'];
                        // Check if it has the old enum values
                        if (strpos($type, 'completed') !== false || strpos($type, 'failed') !== false) {
                            // Update existing records to use new status values
                            $pdo->exec("UPDATE `plan_purchases` SET status = 'approved' WHERE status = 'completed'");
                            $pdo->exec("UPDATE `plan_purchases` SET status = 'rejected' WHERE status IN ('failed', 'cancelled')");
                            // Modify the column to use correct enum values
                            $pdo->exec("ALTER TABLE `plan_purchases` MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Failed to fix plan_purchases status enum: " . $e->getMessage());
                }
                // Add payment_date column if it doesn't exist
                if (!$columnExists('plan_purchases', 'payment_date')) {
                    $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `payment_date` DATE NULL AFTER `payment_proof`");
                }
                // Add company column for custom plan requests
                if (!$columnExists('plan_purchases', 'company')) {
                    try {
                        $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `company` VARCHAR(200) NULL AFTER `user_phone`");
                        error_log("Added 'company' column to plan_purchases table");
                    } catch (\Exception $e) {
                        error_log("Failed to add 'company' column: " . $e->getMessage());
                    }
                }
                // Add custom_message column for custom plan requests
                if (!$columnExists('plan_purchases', 'custom_message')) {
                    try {
                        $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `custom_message` TEXT NULL AFTER `company`");
                        error_log("Added 'custom_message' column to plan_purchases table");
                    } catch (\Exception $e) {
                        error_log("Failed to add 'custom_message' column: " . $e->getMessage());
                    }
                }
            }

            // Create payment_methods table if not exists
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `payment_methods` (
                        `id` INT AUTO_INCREMENT,
                        `type` ENUM('mobile_payment', 'bank_transfer', 'card') NOT NULL,
                        `name` VARCHAR(100) NOT NULL,
                        `phone_number` VARCHAR(20) NULL,
                        `account_number` VARCHAR(50) NULL,
                        `account_holder` VARCHAR(100) NULL,
                        `branch_name` VARCHAR(100) NULL,
                        `routing_number` VARCHAR(20) NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `sort_order` INT NOT NULL DEFAULT 0,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        INDEX `idx_payment_methods_type` (`type`),
                        INDEX `idx_payment_methods_active` (`is_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (\Exception $e) {
                error_log("Failed to create payment_methods table: " . $e->getMessage());
            }

            // Insert default Bangladeshi payment methods if table is empty
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `payment_methods`");
                $count = $stmt->fetchColumn();
                if ($count == 0) {
                    $pdo->exec("
                        INSERT INTO `payment_methods` (`type`, `name`, `phone_number`, `account_holder`, `is_active`, `sort_order`) VALUES
                        ('mobile_payment', 'bKash', '01700000000', 'CodexaaPOS++', 1, 1),
                        ('mobile_payment', 'Nagad', '01800000000', 'CodexaaPOS++', 1, 2),
                        ('mobile_payment', 'Rocket', '01900000000', 'CodexaaPOS++', 1, 3),
                        ('bank_transfer', 'Dutch-Bangla Bank (DBBL)', '1234567890', 'CodexaaPOS++', 1, 4),
                        ('bank_transfer', 'Sonali Bank', '0987654321', 'CodexaaPOS++', 1, 5),
                        ('bank_transfer', 'Brac Bank', '1122334455', 'CodexaaPOS++', 1, 6),
                        ('bank_transfer', 'City Bank', '5566778899', 'CodexaaPOS++', 1, 7)
                    ");
                }
            } catch (\Exception $e) {
                error_log("Failed to insert default payment methods: " . $e->getMessage());
            }

        } catch (\PDOException $e) {
            error_log("Migration error: " . $e->getMessage());
            file_put_contents(__DIR__ . '/migration_error.txt', "Migration error: " . $e->getMessage());
        }
    }
}
