<?php
// Test script to check if custom request columns exist in plan_purchases table

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

    echo "Checking plan_purchases table structure...\n\n";

    // Get all columns in plan_purchases table
    $stmt = $pdo->query("DESCRIBE plan_purchases");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in plan_purchases table:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    
    // Check specifically for company and custom_message columns
    echo "\nChecking for custom request columns:\n";
    $hasCompany = false;
    $hasCustomMessage = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'company') {
            $hasCompany = true;
            echo "  ✓ 'company' column exists\n";
        }
        if ($column['Field'] === 'custom_message') {
            $hasCustomMessage = true;
            echo "  ✓ 'custom_message' column exists\n";
        }
    }
    
    if (!$hasCompany) {
        echo "  ✗ 'company' column MISSING\n";
    }
    if (!$hasCustomMessage) {
        echo "  ✗ 'custom_message' column MISSING\n";
    }
    
    // Check if there are any custom requests in the table
    echo "\nChecking for existing custom requests:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM plan_purchases WHERE payment_method = 'custom_request'");
    $result = $stmt->fetch();
    echo "  Custom requests count: {$result['count']}\n";
    
    // Show recent custom requests if any
    if ($result['count'] > 0) {
        echo "\nRecent custom requests:\n";
        $stmt = $pdo->query("SELECT id, user_name, user_email, company, custom_message, status, created_at FROM plan_purchases WHERE payment_method = 'custom_request' ORDER BY created_at DESC LIMIT 5");
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($requests as $req) {
            echo "  ID: {$req['id']}, Name: {$req['user_name']}, Email: {$req['user_email']}, Company: " . ($req['company'] ?? 'NULL') . ", Message: " . ($req['custom_message'] ?? 'NULL') . ", Status: {$req['status']}, Date: {$req['created_at']}\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
