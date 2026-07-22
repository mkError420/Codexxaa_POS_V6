-- Migration: Add site_settings table for global site configuration
-- This table stores global site-wide settings like site name and description

USE `if0_42451104_codexaa`;

-- Create site_settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default site settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'CodexaaPOS++'),
('site_description', 'Modern Point of Sale For Your Business')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
