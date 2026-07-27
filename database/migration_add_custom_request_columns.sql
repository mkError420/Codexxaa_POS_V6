-- Add columns for custom plan requests to plan_purchases table
ALTER TABLE `plan_purchases` ADD COLUMN `company` VARCHAR(200) NULL AFTER `user_phone`;
ALTER TABLE `plan_purchases` ADD COLUMN `custom_message` TEXT NULL AFTER `company`;
