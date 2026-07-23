<?php
// Run migration to add loyalty program settings to shops table
require_once 'backend/config/db.php';

try {
    echo "Starting migration to add loyalty program settings...\n\n";
    
    // Check if columns already exist
    $checkSql = "SHOW COLUMNS FROM shops LIKE 'loyalty_enabled'";
    $stmt = DB::query($checkSql);
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "Column 'loyalty_enabled' already exists in shops table.\n";
        echo "Migration may have already been run. Skipping...\n";
        exit(0);
    }
    
    // Add loyalty settings columns
    $sql = "ALTER TABLE `shops`
            ADD COLUMN `loyalty_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether loyalty program is enabled (0=disabled, 1=enabled)',
            ADD COLUMN `loyalty_point_earn_rate` DECIMAL(10,2) NOT NULL DEFAULT 100.00 COMMENT 'Amount of purchase required to earn 1 loyalty point',
            ADD COLUMN `loyalty_point_value` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Monetary value of 1 loyalty point'";
    
    $stmt = DB::query($sql);
    
    echo "Successfully added loyalty program settings columns to shops table:\n";
    echo "  - loyalty_enabled (TINYINT, default: 0)\n";
    echo "  - loyalty_point_earn_rate (DECIMAL, default: 100.00)\n";
    echo "  - loyalty_point_value (DECIMAL, default: 1.00)\n\n";
    
    // Verify the columns were added
    $verifySql = "SHOW COLUMNS FROM shops WHERE Field IN ('loyalty_enabled', 'loyalty_point_earn_rate', 'loyalty_point_value')";
    $stmt = DB::query($verifySql);
    $results = $stmt->fetchAll();
    
    echo "Verification Results:\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-25s %-20s %-10s\n", "Column", "Type", "Default");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($results as $row) {
        printf("%-25s %-20s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Default']
        );
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
