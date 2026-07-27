-- Add is_custom column to pricing_plans table
ALTER TABLE `pricing_plans` ADD COLUMN `is_custom` TINYINT(1) DEFAULT 0 AFTER `is_popular`;
