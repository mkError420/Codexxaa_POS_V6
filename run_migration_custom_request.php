<?php
// Migration script to add custom request columns to plan_purchases table

// Load environment variables
$envFile = __DIR__ . '/backend/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'codexaa_pos';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Adding custom request columns to plan_purchases table...\n";

    // Check if company column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM plan_purchases LIKE 'company'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `company` VARCHAR(200) NULL AFTER `user_phone`");
        echo "✓ Added 'company' column\n";
    } else {
        echo "✓ 'company' column already exists\n";
    }

    // Check if custom_message column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM plan_purchases LIKE 'custom_message'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `plan_purchases` ADD COLUMN `custom_message` TEXT NULL AFTER `company`");
        echo "✓ Added 'custom_message' column\n";
    } else {
        echo "✓ 'custom_message' column already exists\n";
    }

    echo "\nMigration completed successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
