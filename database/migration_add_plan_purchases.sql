-- Migration for Plan Purchases functionality
-- This table will track when users purchase pricing plans

-- Create plan_purchases table
CREATE TABLE IF NOT EXISTS `plan_purchases` (
  `id` INT AUTO_INCREMENT,
  `plan_id` INT NOT NULL,
  `shop_id` INT NULL,
  `user_name` VARCHAR(100) NOT NULL,
  `user_email` VARCHAR(100) NOT NULL,
  `user_phone` VARCHAR(20) NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'BDT',
  `payment_method` ENUM('cash', 'card', 'mobile_pay', 'bank_transfer', 'other') NOT NULL DEFAULT 'other',
  `status` ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `transaction_id` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `purchase_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_plan_purchases_plan` (`plan_id`),
  INDEX `idx_plan_purchases_shop` (`shop_id`),
  INDEX `idx_plan_purchases_status` (`status`),
  INDEX `idx_plan_purchases_date` (`purchase_date`),
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

-- Add pricing_plans table if it doesn't exist (for completeness)
CREATE TABLE IF NOT EXISTS `pricing_plans` (
  `id` INT AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `period` VARCHAR(20) NOT NULL DEFAULT 'month',
  `features` JSON NULL,
  `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
  `button_text` VARCHAR(50) NOT NULL DEFAULT 'Get Started',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pricing_plans_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
