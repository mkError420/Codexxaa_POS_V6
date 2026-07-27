-- Migration: Add hero_content and active_shop_count to site_settings
-- This adds support for home page hero content and dynamic shop count

USE `if0_42451104_codexaa`;

-- Insert hero_content setting
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('hero_content', 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Insert active_shop_count setting (will be updated dynamically by backend)
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('active_shop_count', '0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
