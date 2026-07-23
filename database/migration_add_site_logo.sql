-- Migration: Add logo field to site_settings table
-- This adds support for site logo upload

USE `if0_42451104_codexaa`;

-- Insert logo setting into site_settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_logo', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
