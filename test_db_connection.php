<?php
// Test database connection to remote MySQL server
$host = 'sql309.infinityfree.com';
$user = 'if0_42451104';
$pass = 'I8Kw8aZkldJO';
$dbname = 'if0_42451104_codexaa';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "✓ Database connection successful!\n";
    echo "Connected to: $host\n";
    echo "Database: $dbname\n";
    
    // Test query to check if tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "\n⚠ No tables found in database. Schema will be auto-created on first run.\n";
    } else {
        echo "\n✓ Found " . count($tables) . " tables:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
