-- Migration to add shop registration fields to plan_purchases table
-- This will allow users to register their shop details when purchasing a plan

-- Add shop registration fields to plan_purchases table
ALTER TABLE `plan_purchases`
ADD COLUMN `shop_name` VARCHAR(100) NULL AFTER `user_phone`,
ADD COLUMN `shop_address` VARCHAR(255) NULL AFTER `shop_name`,
ADD COLUMN `shop_phone` VARCHAR(20) NULL AFTER `shop_address`,
ADD COLUMN `shop_city` VARCHAR(100) NULL AFTER `shop_phone`,
ADD COLUMN `shop_country` VARCHAR(100) NULL AFTER `shop_city`;

-- Add indexes for better query performance
ALTER TABLE `plan_purchases`
ADD INDEX `idx_shop_name` (`shop_name`);
