-- Migration: Add payment_methods table for pricing plan purchases
-- This table stores available payment methods for plan purchases

CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` INT AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `instructions` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_payment_methods_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default payment methods
INSERT INTO `payment_methods` (`name`, `type`, `description`, `instructions`, `is_active`) VALUES
('Bank Transfer', 'bank_transfer', 'Direct bank transfer to our account', 'Please transfer the amount to the following bank account and provide your transaction ID.', 1),
('Mobile Payment', 'mobile_pay', 'Payment via mobile banking services', 'Use your mobile banking app to send payment and share the transaction reference.', 1),
('Cash', 'cash', 'Cash payment at our office', 'Visit our office to make cash payment.', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
