<?php
// Migration script to add is_custom column to pricing_plans table

// Load database configuration
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
} else {
    $env = [];
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? 'codexaa_pos';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `pricing_plans` LIKE 'is_custom'");
    if ($stmt->fetch()) {
        echo "Column 'is_custom' already exists in pricing_plans table. No migration needed.\n";
        exit(0);
    }

    // Add the column
    $pdo->exec("ALTER TABLE `pricing_plans` ADD COLUMN `is_custom` TINYINT(1) DEFAULT 0 AFTER `is_popular`");
    echo "Successfully added 'is_custom' column to pricing_plans table.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
