<?php
/**
 * Migration Runner for Shop Registration Fields
 * This script adds shop registration fields to the plan_purchases table
 */

// Load environment variables
function loadEnv() {
    $paths = [
        __DIR__ . '/.env',
        __DIR__ . '/backend/.env',
        __DIR__ . '/.env.example'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                        $value = $matches[1];
                    }
                    putenv("$name=$value");
                    $_ENV[$name] = $value;
                }
            }
            break;
        }
    }
}
loadEnv();

// Database connection
function getDBConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'if0_42451104_codexaa';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage() . "\n");
    }
}

echo "========================================\n";
echo "Shop Registration Fields Migration\n";
echo "========================================\n\n";

$pdo = getDBConnection();

// Read and execute migration file
$migrationFile = __DIR__ . '/database/migration_add_shop_registration_fields.sql';

if (!file_exists($migrationFile)) {
    die("Migration file not found: $migrationFile\n");
}

echo "Executing migration: $migrationFile\n";

$sql = file_get_contents($migrationFile);

// Remove USE statement if exists
$sql = preg_replace('/USE\s+`[^`]+`;\s*/i', '', $sql);

// Split by semicolon and execute each statement
$statements = explode(';', $sql);

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement)) continue;
    
    try {
        $pdo->exec($statement);
        echo "Statement executed successfully.\n";
    } catch (PDOException $e) {
        // Ignore duplicate column errors
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            echo "Error executing statement: " . $e->getMessage() . "\n";
        } else {
            echo "Column already exists, skipping...\n";
        }
    }
}

echo "\n========================================\n";
echo "Migration completed successfully!\n";
echo "========================================\n";
