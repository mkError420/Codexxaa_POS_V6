<?php
/**
 * Migration Runner for Pricing Plans Functionality
 * This script creates the necessary tables for pricing plans, plan purchases, and payment methods
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
echo "Pricing Plans Migration Runner\n";
echo "========================================\n\n";

$pdo = getDBConnection();

// Read and execute migration files
$migrationFiles = [
    __DIR__ . '/database/migration_add_plan_purchases.sql',
    __DIR__ . '/database/migration_add_payment_methods.sql'
];

foreach ($migrationFiles as $file) {
    if (!file_exists($file)) {
        echo "Migration file not found: $file\n";
        continue;
    }
    
    echo "Executing migration: $file\n";
    
    $sql = file_get_contents($file);
    
    // Remove USE statement if存在
    $sql = preg_replace('/USE\s+`[^`]+`;\s*/i', '', $sql);
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // Ignore duplicate table errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "Error executing statement: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "Migration completed: $file\n\n";
}

// Insert sample pricing plans if none exist
echo "Checking for sample pricing plans...\n";
$stmt = $pdo->query("SELECT COUNT(*) as count FROM pricing_plans");
$count = $stmt->fetch()['count'];

if ($count == 0) {
    echo "No pricing plans found. Inserting sample data...\n";
    
    $samplePlans = [
        [
            'name' => 'Starter',
            'description' => 'Perfect for small businesses just getting started',
            'price' => 500.00,
            'period' => 'month',
            'features' => json_encode(['1 Shop Location', 'Up to 100 Products', '1 Staff Account', 'Basic Analytics']),
            'is_popular' => 0,
            'button_text' => 'Get Started',
            'sort_order' => 1
        ],
        [
            'name' => 'Professional',
            'description' => 'Ideal for growing businesses with multiple locations',
            'price' => 1500.00,
            'period' => 'month',
            'features' => json_encode(['3 Shop Locations', 'Up to 500 Products', '5 Staff Accounts', 'Advanced Analytics', 'Priority Support']),
            'is_popular' => 1,
            'button_text' => 'Start Free Trial',
            'sort_order' => 2
        ],
        [
            'name' => 'Enterprise',
            'description' => 'For large organizations with complex needs',
            'price' => 5000.00,
            'period' => 'month',
            'features' => json_encode(['Unlimited Shop Locations', 'Unlimited Products', 'Unlimited Staff Accounts', 'Custom Integrations', 'Dedicated Account Manager', 'White-label Solution']),
            'is_popular' => 0,
            'button_text' => 'Contact Sales',
            'sort_order' => 3
        ]
    ];
    
    foreach ($samplePlans as $plan) {
        $stmt = $pdo->prepare(
            "INSERT INTO pricing_plans (name, description, price, period, features, is_popular, button_text, sort_order) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $plan['name'],
            $plan['description'],
            $plan['price'],
            $plan['period'],
            $plan['features'],
            $plan['is_popular'],
            $plan['button_text'],
            $plan['sort_order']
        ]);
    }
    
    echo "Sample pricing plans inserted successfully.\n";
} else {
    echo "Found $count existing pricing plans.\n";
}

echo "\n========================================\n";
echo "Migration completed successfully!\n";
echo "========================================\n";
