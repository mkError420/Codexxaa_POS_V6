<?php
// Run migration to add site_settings table
require_once 'backend/config/db.php';

try {
    echo "Starting migration to add site_settings table...\n\n";
    
    // Create site_settings table
    $sql = "CREATE TABLE IF NOT EXISTS `site_settings` (
      `setting_key` VARCHAR(50) NOT NULL,
      `setting_value` TEXT NOT NULL,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $stmt = DB::query($sql);
    echo "Created/verified site_settings table\n";
    
    // Insert default site settings
    $sql = "INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
    ('site_name', 'CodexaaPOS++'),
    ('site_description', 'Modern Point of Sale For Your Business')
    ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)";
    
    $stmt = DB::query($sql);
    echo "Inserted default site settings\n";
    
    // Verify the settings
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = DB::query($sql);
    $results = $stmt->fetchAll();
    
    echo "\nCurrent site_settings:\n";
    echo str_repeat("-", 50) . "\n";
    foreach ($results as $row) {
        printf("%-20s %s\n", $row['setting_key'] . ':', $row['setting_value']);
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
