<?php

/**
 * Other Controller (Wastage, Customer Returns, Adjustments, Other Costs, Shops, Users/Staff)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

class OtherController {

    // ==========================================
    // SITE SETTINGS
    // ==========================================

    public static function getSiteSettings() {
        // This is a public endpoint, no authentication needed
        try {
            $stmt = DB::query('SELECT setting_key, setting_value FROM site_settings');
            $settingsRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Get active shop count
            $shopStmt = DB::query("SELECT COUNT(*) FROM shops WHERE status = 'active'");
            $activeShopCount = $shopStmt->fetchColumn();
            
            $settings = [
                'site_name' => $settingsRaw['site_name'] ?? 'CodexaaPos++',
                'site_description' => $settingsRaw['site_description'] ?? 'Modern Point of Sale For Your Business',
                'hero_content' => $settingsRaw['hero_content'] ?? 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.',
                'site_logo' => $settingsRaw['site_logo'] ?? '',
                'active_shop_count' => (int)$activeShopCount
            ];

            header('Content-Type: application/json');
            echo json_encode($settings);

        } catch (\Exception $e) {
            error_log('Fetch site settings error: ' . $e->getMessage());
            // Fallback for when table doesn't exist yet
            header('Content-Type: application/json');
            echo json_encode([
                'site_name' => 'CodexaaPos++',
                'site_description' => 'Modern Point of Sale For Your Business',
                'hero_content' => 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.',
                'site_logo' => '',
                'active_shop_count' => 0
            ]);
        }
    }

    public static function updateSiteSettings($requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $siteName = $requestData['site_name'] ?? null;
        $siteDescription = $requestData['site_description'] ?? null;
        $heroContent = $requestData['hero_content'] ?? null;
        $siteLogo = $requestData['site_logo'] ?? null;

        if ($siteName === null || $siteDescription === null) {
            Auth::jsonError('site_name and site_description are required.', 400);
        }

        try {
            DB::beginTransaction();

            // Use INSERT ... ON DUPLICATE KEY UPDATE for an atomic upsert
            DB::query(
                'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?), (?, ?), (?, ?), (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                ['site_name', $siteName, 'site_description', $siteDescription, 'hero_content', $heroContent ?? '', 'site_logo', $siteLogo ?? '']
            );

            DB::commit();

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Site settings updated successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Update site settings error: ' . $e->getMessage());
            Auth::jsonError('Server error updating site settings.', 500);
        }
    }

    // ==========================================
    // PRICING PLANS
    // ==========================================

    public static function listPricingPlans() {
        // This is a public endpoint, no authentication needed
        try {
            $stmt = DB::query('SELECT * FROM pricing_plans ORDER BY sort_order ASC');
            $plans = $stmt->fetchAll();

            foreach ($plans as &$plan) {
                $plan['id'] = (int)$plan['id'];
                $plan['price'] = (float)$plan['price'];
                $plan['is_popular'] = (bool)$plan['is_popular'];
                $plan['sort_order'] = (int)$plan['sort_order'];
                $plan['features'] = json_decode($plan['features'], true) ?? [];
            }

            header('Content-Type: application/json');
            echo json_encode($plans);

        } catch (\Exception $e) {
            error_log('Fetch pricing plans error: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([]);
        }
    }

    public static function createPricingPlan($requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $name = $requestData['name'] ?? null;
        $description = $requestData['description'] ?? null;
        $price = $requestData['price'] ?? null;
        $period = $requestData['period'] ?? 'month';
        $features = $requestData['features'] ?? [];
        $isPopular = isset($requestData['is_popular']) ? ($requestData['is_popular'] ? 1 : 0) : 0;
        $buttonText = $requestData['button_text'] ?? 'Get Started';
        $sortOrder = $requestData['sort_order'] ?? 0;

        if (empty($name) || $price === null) {
            Auth::jsonError('name and price are required.', 400);
        }

        try {
            DB::query(
                'INSERT INTO pricing_plans (name, description, price, period, features, is_popular, button_text, sort_order) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$name, $description, $price, $period, json_encode($features), $isPopular, $buttonText, $sortOrder]
            );
            $newId = DB::lastInsertId();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Pricing plan created successfully.',
                'planId' => (int)$newId
            ]);

        } catch (\Exception $e) {
            error_log('Create pricing plan error: ' . $e->getMessage());
            Auth::jsonError('Server error creating pricing plan.', 500);
        }
    }

    public static function updatePricingPlan($id, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $planId = (int)$id;
        $name = $requestData['name'] ?? null;
        $description = $requestData['description'] ?? null;
        $price = $requestData['price'] ?? null;
        $period = $requestData['period'] ?? null;
        $features = $requestData['features'] ?? null;
        $isPopular = isset($requestData['is_popular']) ? ($requestData['is_popular'] ? 1 : 0) : null;
        $buttonText = $requestData['button_text'] ?? null;
        $sortOrder = $requestData['sort_order'] ?? null;

        if (empty($name) || $price === null) {
            Auth::jsonError('name and price are required.', 400);
        }

        try {
            $updateFields = [];
            $params = [];

            if ($name !== null) {
                $updateFields[] = 'name = ?';
                $params[] = $name;
            }
            if ($description !== null) {
                $updateFields[] = 'description = ?';
                $params[] = $description;
            }
            if ($price !== null) {
                $updateFields[] = 'price = ?';
                $params[] = $price;
            }
            if ($period !== null) {
                $updateFields[] = 'period = ?';
                $params[] = $period;
            }
            if ($features !== null) {
                $updateFields[] = 'features = ?';
                $params[] = json_encode($features);
            }
            if ($isPopular !== null) {
                $updateFields[] = 'is_popular = ?';
                $params[] = $isPopular;
            }
            if ($buttonText !== null) {
                $updateFields[] = 'button_text = ?';
                $params[] = $buttonText;
            }
            if ($sortOrder !== null) {
                $updateFields[] = 'sort_order = ?';
                $params[] = $sortOrder;
            }

            $params[] = $planId;

            DB::query('UPDATE pricing_plans SET ' . implode(', ', $updateFields) . ' WHERE id = ?', $params);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Pricing plan updated successfully.']);

        } catch (\Exception $e) {
            error_log('Update pricing plan error: ' . $e->getMessage());
            Auth::jsonError('Server error updating pricing plan.', 500);
        }
    }

    public static function deletePricingPlan($id) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $planId = (int)$id;

        try {
            DB::query('DELETE FROM pricing_plans WHERE id = ?', [$planId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Pricing plan deleted successfully.']);

        } catch (\Exception $e) {
            error_log('Delete pricing plan error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting pricing plan.', 500);
        }
    }

    // ==========================================
    // PLAN PURCHASES
    // ==========================================

    public static function listPlanPurchases() {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        try {
            $sql = 'SELECT pur.*, pl.name AS plan_name, pl.price AS plan_price, pl.period AS plan_period,
                    s.name AS shop_name, u.name AS user_name
                    FROM plan_purchases pur
                    LEFT JOIN pricing_plans pl ON pur.plan_id = pl.id
                    LEFT JOIN shops s ON pur.shop_id = s.id
                    LEFT JOIN users u ON pur.shop_id = u.shop_id AND u.role = "shop_admin"
                    ORDER BY pur.purchase_date DESC';
            
            $stmt = DB::query($sql);
            $purchases = $stmt->fetchAll();

            foreach ($purchases as &$purchase) {
                $purchase['id'] = (int)$purchase['id'];
                $purchase['plan_id'] = (int)$purchase['plan_id'];
                $purchase['shop_id'] = $purchase['shop_id'] !== null ? (int)$purchase['shop_id'] : null;
                $purchase['amount_paid'] = (float)$purchase['amount_paid'];
            }

            header('Content-Type: application/json');
            echo json_encode($purchases);

        } catch (\Exception $e) {
            error_log('Fetch plan purchases error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving plan purchases.', 500);
        }
    }

    public static function createPlanPurchase($requestData) {
        // Public endpoint - no authentication required for purchasing plans
        $planId = $requestData['plan_id'] ?? null;
        $userName = $requestData['user_name'] ?? null;
        $userEmail = $requestData['user_email'] ?? null;
        $userPhone = $requestData['user_phone'] ?? null;
        $paymentMethod = $requestData['payment_method'] ?? 'other';
        $paymentMethodId = $requestData['payment_method_id'] ?? null;
        $notes = $requestData['notes'] ?? null;
        $shopId = $requestData['shop_id'] ?? null;
        $userTransactionId = $requestData['transaction_id'] ?? null;
        $bankName = $requestData['bank_name'] ?? null;
        $accountNumber = $requestData['account_number'] ?? null;
        $cardLastFour = $requestData['card_last_four'] ?? null;
        $paymentProof = $requestData['payment_proof'] ?? null;

        if (empty($planId) || empty($userName) || empty($userEmail)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'plan_id, user_name, and user_email are required.']);
            return;
        }

        try {
            DB::beginTransaction();

            // Get plan details
            $stmt = DB::query('SELECT * FROM pricing_plans WHERE id = ?', [(int)$planId]);
            $plan = $stmt->fetch();

            if (!$plan) {
                DB::rollBack();
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(['error' => 'Pricing plan not found.']);
                return;
            }

            // Calculate expiry date based on plan period
            $expiryDate = null;
            if ($plan['period'] === 'month') {
                $expiryDate = date('Y-m-d H:i:s', strtotime('+1 month'));
            } elseif ($plan['period'] === 'year') {
                $expiryDate = date('Y-m-d H:i:s', strtotime('+1 year'));
            }

            // Use user-provided transaction ID or generate one
            $transactionId = $userTransactionId ?: 'TXN-' . strtoupper(uniqid());

            // Insert purchase record
            DB::query(
                'INSERT INTO plan_purchases (plan_id, shop_id, user_name, user_email, user_phone, amount_paid, currency, payment_method, payment_method_id, status, transaction_id, bank_name, account_number, card_last_four, payment_proof, notes, expiry_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int)$planId,
                    $shopId ? (int)$shopId : null,
                    $userName,
                    $userEmail,
                    $userPhone,
                    (float)$plan['price'],
                    'BDT',
                    $paymentMethod,
                    $paymentMethodId ? (int)$paymentMethodId : null,
                    'pending',
                    $transactionId,
                    $bankName,
                    $accountNumber,
                    $cardLastFour,
                    $paymentProof,
                    $notes,
                    $expiryDate
                ]
            );

            $purchaseId = DB::lastInsertId();
            DB::commit();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Plan purchase submitted successfully. Your purchase is pending review.',
                'purchase_id' => (int)$purchaseId,
                'transaction_id' => $transactionId,
                'expiry_date' => $expiryDate
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Create plan purchase error: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Server error processing plan purchase.']);
        }
    }

    public static function updatePlanPurchase($id, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $purchaseId = (int)$id;
        $status = $requestData['status'] ?? null;
        $notes = $requestData['notes'] ?? null;

        if (empty($status)) {
            Auth::jsonError('status is required.', 400);
        }

        try {
            $updateFields = [];
            $params = [];

            if ($status !== null) {
                $updateFields[] = 'status = ?';
                $params[] = $status;
            }
            if ($notes !== null) {
                $updateFields[] = 'notes = ?';
                $params[] = $notes;
            }

            $params[] = $purchaseId;

            DB::query('UPDATE plan_purchases SET ' . implode(', ', $updateFields) . ' WHERE id = ?', $params);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Plan purchase updated successfully.']);

        } catch (\Exception $e) {
            error_log('Update plan purchase error: ' . $e->getMessage());
            Auth::jsonError('Server error updating plan purchase.', 500);
        }
    }

    public static function deletePlanPurchase($id) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $purchaseId = (int)$id;

        try {
            DB::query('DELETE FROM plan_purchases WHERE id = ?', [$purchaseId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Plan purchase deleted successfully.']);

        } catch (\Exception $e) {
            error_log('Delete plan purchase error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting plan purchase.', 500);
        }
    }

    // ==========================================
    // PAYMENT METHODS
    // ==========================================

    public static function listPaymentMethods() {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        try {
            // Check if table exists, if not create it
            $pdo = DB::getConnection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'payment_methods'");
            if ($stmt->rowCount() == 0) {
                // Table doesn't exist, create it
                $pdo->exec("
                    CREATE TABLE `payment_methods` (
                        `id` INT AUTO_INCREMENT,
                        `type` ENUM('mobile_payment', 'bank_transfer', 'card') NOT NULL,
                        `name` VARCHAR(100) NOT NULL,
                        `phone_number` VARCHAR(20) NULL,
                        `account_number` VARCHAR(50) NULL,
                        `account_holder` VARCHAR(100) NULL,
                        `branch_name` VARCHAR(100) NULL,
                        `routing_number` VARCHAR(20) NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `sort_order` INT NOT NULL DEFAULT 0,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        INDEX `idx_payment_methods_type` (`type`),
                        INDEX `idx_payment_methods_active` (`is_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
                
                // Insert default payment methods
                $pdo->exec("
                    INSERT INTO `payment_methods` (`type`, `name`, `phone_number`, `account_holder`, `is_active`, `sort_order`) VALUES
                    ('mobile_payment', 'bKash', '01700000000', 'CodexaaPOS++', 1, 1),
                    ('mobile_payment', 'Nagad', '01800000000', 'CodexaaPOS++', 1, 2),
                    ('mobile_payment', 'Rocket', '01900000000', 'CodexaaPOS++', 1, 3),
                    ('bank_transfer', 'Dutch-Bangla Bank (DBBL)', '1234567890', 'CodexaaPOS++', 1, 4),
                    ('bank_transfer', 'Sonali Bank', '0987654321', 'CodexaaPOS++', 1, 5),
                    ('bank_transfer', 'Brac Bank', '1122334455', 'CodexaaPOS++', 1, 6),
                    ('bank_transfer', 'City Bank', '5566778899', 'CodexaaPOS++', 1, 7)
                ");
            }

            $sql = 'SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC';
            $stmt = DB::query($sql);
            $methods = $stmt->fetchAll();

            foreach ($methods as &$method) {
                $method['id'] = (int)$method['id'];
                $method['is_active'] = (bool)$method['is_active'];
                $method['sort_order'] = (int)$method['sort_order'];
            }

            header('Content-Type: application/json');
            echo json_encode($methods);

        } catch (\Exception $e) {
            error_log('Fetch payment methods error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving payment methods: ' . $e->getMessage(), 500);
        }
    }

    public static function createPaymentMethod($requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        // Ensure table exists
        try {
            $pdo = DB::getConnection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'payment_methods'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("
                    CREATE TABLE `payment_methods` (
                        `id` INT AUTO_INCREMENT,
                        `type` ENUM('mobile_payment', 'bank_transfer', 'card') NOT NULL,
                        `name` VARCHAR(100) NOT NULL,
                        `phone_number` VARCHAR(20) NULL,
                        `account_number` VARCHAR(50) NULL,
                        `account_holder` VARCHAR(100) NULL,
                        `branch_name` VARCHAR(100) NULL,
                        `routing_number` VARCHAR(20) NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `sort_order` INT NOT NULL DEFAULT 0,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        INDEX `idx_payment_methods_type` (`type`),
                        INDEX `idx_payment_methods_active` (`is_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Insert default payment methods
         /*                            ('bank_transfer', 'Dutch-Bangla Bank (DBBL)', '1234567890', 'CodexaaPOS++', 1, 4),
                    ('bank_transfer', 'Sonali Bank', '0987654321', 'CodexaaPOS++', 1, 5),
                    ('bank_transfer', 'Brac Bank', '1122334455', 'CodexaaPOS++', 1, 6),
                    ('bank_transfer', 'City Bank', '5566778899', 'CodexaaPOS++', 1, 7) 
 */
                $pdo->exec("
                    INSERT INTO `payment_methods` (`type`, `name`, `phone_number`, `account_holder`, `is_active`, `sort_order`) VALUES
                    ('mobile_payment', 'bKash', '01854718767', 'CodexaaPOS++', 1, 1),
                    ('mobile_payment', 'Nagad', '01854718767', 'CodexaaPOS++', 1, 2),
                    ('mobile_payment', 'Rocket', '01572491828', 'CodexaaPOS++', 1, 3)
                    
                ");

            }
        } catch (\Exception $e) {
            error_log('Table creation check error: ' . $e->getMessage());
        }

        $type = $requestData['type'] ?? null;
        $name = $requestData['name'] ?? null;
        $phoneNumber = $requestData['phone_number'] ?? null;
        $accountNumber = $requestData['account_number'] ?? null;
        $accountHolder = $requestData['account_holder'] ?? null;
        $branchName = $requestData['branch_name'] ?? null;
        $routingNumber = $requestData['routing_number'] ?? null;
        $isActive = isset($requestData['is_active']) ? ($requestData['is_active'] ? 1 : 0) : 1;
        $sortOrder = $requestData['sort_order'] ?? 0;

        if (empty($type) || empty($name)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'type and name are required.']);
            return;
        }

        try {
            DB::query(
                'INSERT INTO payment_methods (type, name, phone_number, account_number, account_holder, branch_name, routing_number, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $type,
                    $name,
                    $phoneNumber,
                    $accountNumber,
                    $accountHolder,
                    $branchName,
                    $routingNumber,
                    $isActive,
                    (int)$sortOrder
                ]
            );

            $methodId = DB::lastInsertId();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Payment method created successfully.',
                'id' => (int)$methodId
            ]);

        } catch (\Exception $e) {
            error_log('Create payment method error: ' . $e->getMessage());
            Auth::jsonError('Server error creating payment method: ' . $e->getMessage(), 500);
        }
    }

    public static function updatePaymentMethod($id, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $methodId = (int)$id;

        try {
            $stmt = DB::query('SELECT * FROM payment_methods WHERE id = ?', [$methodId]);
            $method = $stmt->fetch();

            if (!$method) {
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(['error' => 'Payment method not found.']);
                return;
            }

            $type = $requestData['type'] ?? $method['type'];
            $name = $requestData['name'] ?? $method['name'];
            $phoneNumber = $requestData['phone_number'] ?? $method['phone_number'];
            $accountNumber = $requestData['account_number'] ?? $method['account_number'];
            $accountHolder = $requestData['account_holder'] ?? $method['account_holder'];
            $branchName = $requestData['branch_name'] ?? $method['branch_name'];
            $routingNumber = $requestData['routing_number'] ?? $method['routing_number'];
            $isActive = $requestData['is_active'] ?? $method['is_active'];
            $sortOrder = $requestData['sort_order'] ?? $method['sort_order'];

            DB::query(
                'UPDATE payment_methods SET type = ?, name = ?, phone_number = ?, account_number = ?, account_holder = ?, branch_name = ?, routing_number = ?, is_active = ?, sort_order = ? WHERE id = ?',
                [
                    $type,
                    $name,
                    $phoneNumber,
                    $accountNumber,
                    $accountHolder,
                    $branchName,
                    $routingNumber,
                    (int)$isActive,
                    (int)$sortOrder,
                    $methodId
                ]
            );

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Payment method updated successfully.']);

        } catch (\Exception $e) {
            error_log('Update payment method error: ' . $e->getMessage());
            Auth::jsonError('Server error updating payment method.', 500);
        }
    }

    public static function deletePaymentMethod($id) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $methodId = (int)$id;

        try {
            DB::query('DELETE FROM payment_methods WHERE id = ?', [$methodId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Payment method deleted successfully.']);

        } catch (\Exception $e) {
            error_log('Delete payment method error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting payment method.', 500);
        }
    }

    // Public endpoint to get active payment methods for purchase modal
    public static function getActivePaymentMethods() {
        try {
            $sql = 'SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC';
            $stmt = DB::query($sql);
            $methods = $stmt->fetchAll();

            foreach ($methods as &$method) {
                $method['id'] = (int)$method['id'];
                $method['is_active'] = (bool)$method['is_active'];
                $method['sort_order'] = (int)$method['sort_order'];
            }

            header('Content-Type: application/json');
            echo json_encode($methods);

        } catch (\Exception $e) {
            error_log('Fetch active payment methods error: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Server error retrieving payment methods.']);
        }
    }

    // ==========================================
    // OTHER COSTS
    // ==========================================
    
    public static function listOtherCosts() {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['super_admin', 'shop_admin']);

        $shopId = Auth::$shopId;
        $hasShop = $shopId !== null;

        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        try {
            $sql = 'SELECT o.*, s.name AS shop_name 
                    FROM other_costs o 
                    LEFT JOIN shops s ON o.shop_id = s.id 
                    WHERE ' . ($hasShop ? 'o.shop_id = ?' : '1=1');
            $params = $hasShop ? [$shopId] : [];

            if (!empty($startDate) && !empty($endDate)) {
                $sql .= ' AND o.cost_date BETWEEN ? AND ?';
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $sql .= ' ORDER BY o.cost_date DESC';

            $stmt = DB::query($sql, $params);
            $costs = $stmt->fetchAll();

            foreach ($costs as &$c) {
                $c['id'] = (int)$c['id'];
                $c['shop_id'] = (int)$c['shop_id'];
                $c['amount'] = (float)$c['amount'];
                $c['shop_name'] = $c['shop_name'] ?: 'System / Unknown';
            }

            header('Content-Type: application/json');
            echo json_encode($costs);

        } catch (\Exception $e) {
            error_log('Fetch other costs error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving cost list.', 500);
        }
    }

    public static function createOtherCost($requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $shopId = Auth::$shopId;
        $title = $requestData['title'] ?? '';
        $amount = (float)($requestData['amount'] ?? 0);
        $costDate = $requestData['cost_date'] ?? null;
        $notes = $requestData['notes'] ?? null;

        if (empty($title) || $amount <= 0 || empty($costDate)) {
            Auth::jsonError('Please provide title, positive amount, and cost date.', 400);
        }

        try {
            DB::query(
                'INSERT INTO other_costs (shop_id, title, amount, cost_date, notes) VALUES (?, ?, ?, ?, ?)',
                [$shopId, $title, $amount, $costDate, $notes]
            );
            $newId = DB::lastInsertId();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Cost entry added successfully.',
                'costId' => (int)$newId
            ]);

        } catch (\Exception $e) {
            error_log('Create other cost error: ' . $e->getMessage());
            Auth::jsonError('Server error recording cost entry.', 500);
        }
    }

    public static function updateOtherCost($id, $requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $costId = (int)$id;
        $shopId = Auth::$shopId;

        $title = $requestData['title'] ?? '';
        $amount = (float)($requestData['amount'] ?? 0);
        $costDate = $requestData['cost_date'] ?? null;
        $notes = $requestData['notes'] ?? null;

        if (empty($title) || $amount <= 0 || empty($costDate)) {
            Auth::jsonError('Please provide title, positive amount, and cost date.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM other_costs WHERE id = ? AND shop_id = ?', [$costId, $shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Cost record not found or access denied.', 404);
            }

            DB::query(
                'UPDATE other_costs SET title = ?, amount = ?, cost_date = ?, notes = ? WHERE id = ? AND shop_id = ?',
                [$title, $amount, $costDate, $notes, $costId, $shopId]
            );

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Cost entry updated successfully.']);

        } catch (\Exception $e) {
            error_log('Update other cost error: ' . $e->getMessage());
            Auth::jsonError('Server error updating cost entry.', 500);
        }
    }

    public static function deleteOtherCost($id) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $costId = (int)$id;
        $shopId = Auth::$shopId;

        try {
            $stmt = DB::query('SELECT id FROM other_costs WHERE id = ? AND shop_id = ?', [$costId, $shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Cost record not found or access denied.', 404);
            }

            DB::query('DELETE FROM other_costs WHERE id = ? AND shop_id = ?', [$costId, $shopId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Cost entry deleted successfully.']);

        } catch (\Exception $e) {
            error_log('Delete other cost error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting cost entry.', 500);
        }
    }

    // ==========================================
    // WASTAGE
    // ==========================================

    public static function listWastages() {
        Auth::authenticate();
        Auth::enforceTenant();

        $shopId = Auth::$shopId;
        $hasShop = $shopId !== null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        try {
            $sql = 'SELECT w.*, p.name AS product_name, p.sku AS product_sku, sh.name AS shop_name 
                    FROM wastages w 
                    JOIN products p ON w.product_id = p.id 
                    LEFT JOIN shops sh ON w.shop_id = sh.id
                    WHERE ' . ($hasShop ? 'w.shop_id = ?' : '1=1');

            $params = $hasShop ? [$shopId] : [];

            if (!empty($startDate)) {
                $sql .= ' AND w.adjusted_at >= ?';
                $params[] = $startDate;
            }
            if (!empty($endDate)) {
                $sql .= ' AND w.adjusted_at <= ?';
                $params[] = $endDate;
            }

            $sql .= ' ORDER BY w.adjusted_at DESC, w.id DESC';

            $stmt = DB::query($sql, $params);
            $wastages = $stmt->fetchAll();

            foreach ($wastages as &$w) {
                $w['id'] = (int)$w['id'];
                $w['shop_id'] = (int)$w['shop_id'];
                $w['product_id'] = (int)$w['product_id'];
                $w['quantity'] = (int)$w['quantity'];
                $w['cost_loss'] = (float)$w['cost_loss'];
            }

            header('Content-Type: application/json');
            echo json_encode($wastages);

        } catch (\Exception $e) {
            error_log('Fetch wastages error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving wastage logs.', 500);
        }
    }

    public static function createWastage($requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $shopId = Auth::$shopId;
        $productId = $requestData['product_id'] ?? null;
        $quantity = (int)($requestData['quantity'] ?? 0);
        $reason = $requestData['reason'] ?? '';
        $notes = $requestData['notes'] ?? null;
        $adjustedAt = $requestData['adjusted_at'] ?? null;

        if (empty($productId) || $quantity <= 0 || empty($reason) || empty($adjustedAt)) {
            Auth::jsonError('Please provide product ID, positive quantity, reason, and date.', 400);
        }

        try {
            DB::beginTransaction();

            $stmt = DB::query('SELECT price, cost_price, stock_quantity FROM products WHERE id = ? AND shop_id = ? FOR UPDATE', [$productId, $shopId]);
            $product = $stmt->fetch();

            if (!$product) {
                DB::rollBack();
                Auth::jsonError('Product not found.', 404);
            }

            if ((int)$product['stock_quantity'] < $quantity) {
                DB::rollBack();
                Auth::jsonError('Insufficient stock quantity to record this wastage.', 400);
            }

            $costLoss = $quantity * (float)$product['cost_price'];

            // Save wastage
            DB::query(
                'INSERT INTO wastages (shop_id, product_id, quantity, cost_loss, reason, notes, adjusted_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$shopId, $productId, $quantity, $costLoss, $reason, $notes, $adjustedAt]
            );
            $newId = DB::lastInsertId();

            // Deduct stock
            DB::query(
                'UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND shop_id = ?',
                [$quantity, $productId, $shopId]
            );

            DB::commit();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Wastage recorded, inventory deducted.',
                'wastageId' => (int)$newId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Create wastage error: ' . $e->getMessage());
            Auth::jsonError('Server error recording wastage.', 500);
        }
    }

    public static function deleteWastage($id) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $wastageId = (int)$id;
        $shopId = Auth::$shopId;

        try {
            DB::beginTransaction();

            $stmt = DB::query('SELECT * FROM wastages WHERE id = ? AND shop_id = ?', [$wastageId, $shopId]);
            $wastage = $stmt->fetch();

            if (!$wastage) {
                DB::rollBack();
                Auth::jsonError('Wastage record not found.', 404);
            }

            // Restore product stock
            DB::query(
                'UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND shop_id = ?',
                [(int)$wastage['quantity'], (int)$wastage['product_id'], $shopId]
            );

            // Delete wastage
            DB::query('DELETE FROM wastages WHERE id = ? AND shop_id = ?', [$wastageId, $shopId]);

            DB::commit();

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Wastage record deleted and inventory restored.']);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Delete wastage error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting wastage record.', 500);
        }
    }

    // ==========================================
    // CUSTOMER RETURNS
    // ==========================================

    public static function listReturns() {
        Auth::authenticate();
        Auth::enforceTenant();

        $shopId = Auth::$shopId;

        try {
            $stmt = DB::query(
                'SELECT cr.*, p.name AS product_name, p.sku AS product_sku, c.name AS customer_name, s.created_at AS sale_date 
                 FROM customer_returns cr 
                 JOIN products p ON cr.product_id = p.id 
                 LEFT JOIN customers c ON cr.customer_id = c.id 
                 LEFT JOIN sales s ON cr.sale_id = s.id 
                 WHERE cr.shop_id = ? 
                 ORDER BY cr.created_at DESC',
                [$shopId]
            );
            $returns = $stmt->fetchAll();

            foreach ($returns as &$r) {
                $r['id'] = (int)$r['id'];
                $r['shop_id'] = (int)$r['shop_id'];
                $r['customer_id'] = $r['customer_id'] !== null ? (int)$r['customer_id'] : null;
                $r['sale_id'] = $r['sale_id'] !== null ? (int)$r['sale_id'] : null;
                $r['product_id'] = (int)$r['product_id'];
                $r['quantity'] = (int)$r['quantity'];
                $r['refund_amount'] = (float)$r['refund_amount'];
                $r['deduct_from_due'] = (int)$r['deduct_from_due'];
                $r['amount_deducted_from_due'] = isset($r['amount_deducted_from_due']) ? (float)$r['amount_deducted_from_due'] : 0.00;
            }

            header('Content-Type: application/json');
            echo json_encode($returns);

        } catch (\Exception $e) {
            error_log('Fetch returns error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving returns directory.', 500);
        }
    }

    public static function createReturn($requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin', 'shop_staff']);

        $shopId = Auth::$shopId;
        $customerId = $requestData['customer_id'] ?? null;
        $saleId = $requestData['sale_id'] ?? null;
        $productId = $requestData['product_id'] ?? null;
        $quantity = (int)($requestData['quantity'] ?? 0);
        $refundAmount = (float)($requestData['refund_amount'] ?? 0);
        $refundMethod = $requestData['refund_method'] ?? 'cash';
        $deductFromDue = isset($requestData['deduct_from_due']) && ($requestData['deduct_from_due'] == 1 || $requestData['deduct_from_due'] === true) ? 1 : 0;
        $notes = $requestData['notes'] ?? null;

        if (empty($productId) || $quantity <= 0 || $refundAmount < 0) {
            Auth::jsonError('Product ID, quantity, and valid refund amount are required.', 400);
        }

        try {
            DB::beginTransaction();

            // Verify product exists
            $stmt = DB::query('SELECT id FROM products WHERE id = ? AND shop_id = ?', [$productId, $shopId]);
            if (!$stmt->fetch()) {
                DB::rollBack();
                Auth::jsonError('Product not found.', 404);
            }

            $deductAmount = 0.00;
            if ($customerId) {
                // Fetch customer's current due balance
                $cStmt = DB::query('SELECT due_balance FROM customers WHERE id = ? AND shop_id = ?', [$customerId, $shopId]);
                $customer = $cStmt->fetch();
                if ($customer) {
                // Check if the customer has any due balance from previous sales
                $dueBalance = (float)$customer['due_balance'];
                if ($dueBalance > 0) {
                    // If the customer has a due balance, deduct the refund from it
                    $deductAmount = min($refundAmount, $dueBalance);
                }
            }
            
            if ($customer) {
                    $dueBalance = (float)$customer['due_balance'];
                    if ($deductFromDue === 1) {
                        // User explicitly selected to deduct from due balance
                        $deductAmount = $refundAmount;
                    } elseif ($dueBalance > 0 && $refundMethod === 'cash') {
                        // Customer has due balance and wants to refund with cash, adjust due balance first
                        $deductAmount = min($refundAmount, $dueBalance);
                    }
                }
            }

            if ($deductAmount > 0) {
                DB::query(
                    'UPDATE customers SET due_balance = GREATEST(due_balance - ?, 0) WHERE id = ? AND shop_id = ?',
                    [$deductAmount, $customerId, $shopId]
                );
                $deductFromDue = 1; // Mark that it was deducted
            }

            // Restore stock quantity
            DB::query(
                'UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND shop_id = ?',
                [$quantity, $productId, $shopId]
            );

            // Construct notes
            $finalNotes = $notes;
            if ($deductAmount > 0 && $deductFromDue === 1 && $refundMethod === 'cash') {
                $cashReturned = $refundAmount - $deductAmount;
                $noteMsg = sprintf(
                    "Due balance adjusted: Deducted $%.2f from outstanding due balance. Remaining $%.2f refunded in cash.",
                    $deductAmount,
                    $cashReturned
                );
                $finalNotes = empty($notes) ? $noteMsg : $notes . " | " . $noteMsg;
            }

            // Record return log
            DB::query(
                'INSERT INTO customer_returns (shop_id, customer_id, sale_id, product_id, quantity, refund_amount, refund_method, notes, deduct_from_due, amount_deducted_from_due) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $shopId, 
                    $customerId ? (int)$customerId : null, 
                    $saleId ? (int)$saleId : null, 
                    $productId, 
                    $quantity, 
                    $refundAmount, 
                    $refundMethod, 
                    $finalNotes, 
                    $deductFromDue, 
                    $deductAmount
                ]
            );
            $newId = DB::lastInsertId();

            DB::commit();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Return logged and inventory updated.',
                'returnId' => (int)$newId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Create return error: ' . $e->getMessage());
            Auth::jsonError('Server error logging return.', 500);
        }
    }

    public static function deleteReturn($id) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $returnId = (int)$id;
        $shopId = Auth::$shopId;

        try {
            DB::beginTransaction();

            $stmt = DB::query('SELECT * FROM customer_returns WHERE id = ? AND shop_id = ?', [$returnId, $shopId]);
            $ret = $stmt->fetch();

            if (!$ret) {
                DB::rollBack();
                Auth::jsonError('Return log entry not found.', 404);
            }

            // Revert product stock
            DB::query(
                'UPDATE products SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE id = ? AND shop_id = ?',
                [(int)$ret['quantity'], (int)$ret['product_id'], $shopId]
            );

            // Revert customer due balance if deducted
            if ((int)$ret['deduct_from_due'] === 1 && $ret['customer_id']) {
                $amountToRevert = isset($ret['amount_deducted_from_due']) ? (float)$ret['amount_deducted_from_due'] : (float)$ret['refund_amount'];
                if ($amountToRevert > 0) {
                    DB::query(
                        'UPDATE customers SET due_balance = due_balance + ? WHERE id = ? AND shop_id = ?',
                        [$amountToRevert, (int)$ret['customer_id'], $shopId]
                    );
                }
            }

            // Delete return log
            DB::query('DELETE FROM customer_returns WHERE id = ? AND shop_id = ?', [$returnId, $shopId]);

            DB::commit();

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Return voided and inventory/customer balances reverted.']);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Delete return error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting return record.', 500);
        }
    }

    // ==========================================
    // INVENTORY ADJUSTMENTS
    // ==========================================

    public static function listAdjustments() {
        Auth::authenticate();
        Auth::enforceTenant();

        $shopId = Auth::$shopId;
        $productId = $_GET['product_id'] ?? null;
        $adjustmentType = $_GET['adjustment_type'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        try {
            $sql = 'SELECT ia.*, p.name AS product_name, p.sku AS product_sku, p.unit AS product_unit, u.name AS adjusted_by_name, s.name AS shop_name
                    FROM inventory_adjustments ia
                    JOIN products p ON ia.product_id = p.id
                    JOIN users u ON ia.adjusted_by = u.id
                    LEFT JOIN shops s ON ia.shop_id = s.id
                    WHERE 1=1';
            
            $params = [];

            if ($shopId !== null) {
                $sql .= ' AND ia.shop_id = ?';
                $params[] = $shopId;
            }

            if (!empty($productId)) {
                $sql .= ' AND ia.product_id = ?';
                $params[] = (int)$productId;
            }
            if (!empty($adjustmentType)) {
                $sql .= ' AND ia.adjustment_type = ?';
                $params[] = $adjustmentType;
            }
            if (!empty($startDate)) {
                $sql .= ' AND DATE(ia.created_at) >= ?';
                $params[] = $startDate;
            }
            if (!empty($endDate)) {
                $sql .= ' AND DATE(ia.created_at) <= ?';
                $params[] = $endDate;
            }

            $sql .= ' ORDER BY ia.created_at DESC';

            $stmt = DB::query($sql, $params);
            $adjustments = $stmt->fetchAll();

            foreach ($adjustments as &$ia) {
                $ia['id'] = (int)$ia['id'];
                $ia['shop_id'] = (int)$ia['shop_id'];
                $ia['product_id'] = (int)$ia['product_id'];
                $ia['previous_quantity'] = (float)$ia['previous_quantity'];
                $ia['adjusted_quantity'] = (float)$ia['adjusted_quantity'];
                $ia['difference'] = (float)$ia['difference'];
                $ia['adjusted_by'] = (int)$ia['adjusted_by'];
            }

            header('Content-Type: application/json');
            echo json_encode($adjustments);

        } catch (\Exception $e) {
            error_log('Fetch adjustments error: ' . $e->getMessage());
            Auth::jsonError('Server error fetching inventory adjustments.', 500);
        }
    }

    public static function createAdjustment($requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin', 'super_admin']);

        $shopId = Auth::$shopId;
        $userId = Auth::$user['id'];
        $productId = $requestData['product_id'] ?? null;
        $adjustedQuantity = $requestData['adjusted_quantity'] ?? null;
        $reason = $requestData['reason'] ?? '';
        $notes = $requestData['notes'] ?? null;

        if (empty($productId) || $adjustedQuantity === null || empty($reason)) {
            Auth::jsonError('Product ID, adjusted quantity, and reason are required.', 400);
        }

        $newQty = (float)$adjustedQuantity;
        if ($newQty < 0) {
            Auth::jsonError('Adjusted quantity cannot be negative.', 400);
        }

        try {
            DB::beginTransaction();

            $stmt = DB::query('SELECT id, stock_quantity, name, sku FROM products WHERE id = ? AND shop_id = ? FOR UPDATE', [$productId, $shopId]);
            $product = $stmt->fetch();

            if (!$product) {
                DB::rollBack();
                Auth::jsonError('Product not found.', 404);
            }

            $prevQty = (float)$product['stock_quantity'];
            $diff = $newQty - $prevQty;
            $type = $diff >= 0 ? 'increase' : 'decrease';

            // Update product stock
            DB::query('UPDATE products SET stock_quantity = ? WHERE id = ? AND shop_id = ?', [$newQty, $productId, $shopId]);

            // Save adjustment log
            DB::query(
                'INSERT INTO inventory_adjustments (shop_id, product_id, previous_quantity, adjusted_quantity, difference, adjustment_type, reason, notes, adjusted_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$shopId, $productId, $prevQty, $newQty, $diff, $type, $reason, $notes, $userId]
            );
            $newId = DB::lastInsertId();

            DB::commit();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Stock quantity successfully adjusted.',
                'adjustmentId' => (int)$newId,
                'newStock' => $newQty
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Create adjustment error: ' . $e->getMessage());
            Auth::jsonError('Server error processing stock adjustment.', 500);
        }
    }

    public static function updateAdjustment($adjustmentId, $requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin', 'super_admin']);

        $shopId = Auth::$shopId;
        $userId = Auth::$user['id'];
        $newAdjustedQuantity = $requestData['adjusted_quantity'] ?? null;
        $reason = $requestData['reason'] ?? '';
        $notes = $requestData['notes'] ?? null;

        if ($newAdjustedQuantity === null || empty($reason)) {
            Auth::jsonError('Adjusted quantity and reason are required.', 400);
        }

        $newQty = (float)$newAdjustedQuantity;
        if ($newQty < 0) {
            Auth::jsonError('Adjusted quantity cannot be negative.', 400);
        }

        try {
            DB::beginTransaction();

            $stmt = DB::query('SELECT * FROM inventory_adjustments WHERE id = ? AND shop_id = ? FOR UPDATE', [$adjustmentId, $shopId]);
            $adjustment = $stmt->fetch();

            if (!$adjustment) {
                DB::rollBack();
                Auth::jsonError('Adjustment not found.', 404);
            }

            $productId = $adjustment['product_id'];
            $oldDiff = (float)$adjustment['difference'];
            $prevQty = (float)$adjustment['previous_quantity'];

            $stmt = DB::query('SELECT id, stock_quantity FROM products WHERE id = ? AND shop_id = ? FOR UPDATE', [$productId, $shopId]);
            $product = $stmt->fetch();

            if (!$product) {
                DB::rollBack();
                Auth::jsonError('Product not found.', 404);
            }

            $newDiff = $newQty - $prevQty;
            $type = $newDiff >= 0 ? 'increase' : 'decrease';

            $netChange = $newDiff - $oldDiff;

            if ($netChange != 0) {
                DB::query('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND shop_id = ?', [$netChange, $productId, $shopId]);
            }

            DB::query(
                'UPDATE inventory_adjustments SET adjusted_quantity = ?, difference = ?, adjustment_type = ?, reason = ?, notes = ?, adjusted_by = ? WHERE id = ? AND shop_id = ?',
                [$newQty, $newDiff, $type, $reason, $notes, $userId, $adjustmentId, $shopId]
            );

            DB::commit();

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Adjustment successfully updated.', 'newStock' => (float)$product['stock_quantity'] + $netChange]);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Update adjustment error: ' . $e->getMessage());
            Auth::jsonError('Server error updating stock adjustment.', 500);
        }
    }

    public static function deleteAdjustment($adjustmentId) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin', 'super_admin']);

        $shopId = Auth::$shopId;

        try {
            DB::beginTransaction();

            $stmt = DB::query('SELECT * FROM inventory_adjustments WHERE id = ? AND shop_id = ? FOR UPDATE', [$adjustmentId, $shopId]);
            $adjustment = $stmt->fetch();

            if (!$adjustment) {
                DB::rollBack();
                Auth::jsonError('Adjustment not found.', 404);
            }

            $productId = $adjustment['product_id'];
            $diff = (int)$adjustment['difference'];

            if ($diff !== 0) {
                DB::query('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND shop_id = ?', [$diff, $productId, $shopId]);
            }

            DB::query('DELETE FROM inventory_adjustments WHERE id = ? AND shop_id = ?', [$adjustmentId, $shopId]);

            DB::commit();

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Adjustment successfully deleted and stock reverted.']);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Delete adjustment error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting adjustment.', 500);
        }
    }

    // ==========================================
    // SHOPS (TENANTS)
    // ==========================================

    public static function listShops() {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        try {
            $stmt = DB::query('SELECT id, name, email, phone, address, tax_rate, status, logo, created_at FROM shops ORDER BY name ASC');
            $shops = $stmt->fetchAll();

            foreach ($shops as &$sh) {
                $sh['id'] = (int)$sh['id'];
                $sh['tax_rate'] = (float)$sh['tax_rate'];
            }

            header('Content-Type: application/json');
            echo json_encode($shops);

        } catch (\Exception $e) {
            error_log('Fetch shops error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving shops list.', 500);
        }
    }

    public static function getMyShop() {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin', 'shop_staff']);

        $shopId = Auth::$shopId;

        try {
            $stmt = DB::query('SELECT * FROM shops WHERE id = ?', [$shopId]);
            $shop = $stmt->fetch();

            if (!$shop) {
                Auth::jsonError('Shop details not found.', 404);
            }

            $shop['id'] = (int)$shop['id'];
            $shop['tax_rate'] = (float)$shop['tax_rate'];

            header('Content-Type: application/json');
            echo json_encode($shop);

        } catch (\Exception $e) {
            error_log('Fetch my shop details error: ' . $e->getMessage());
            Auth::jsonError('Server error fetching shop details.', 500);
        }
    }

    public static function updateMyShop($requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $shopId = Auth::$shopId;
        $name = $requestData['name'] ?? '';
        $email = $requestData['email'] ?? '';
        $phone = $requestData['phone'] ?? null;
        $address = $requestData['address'] ?? null;
        $taxRate = isset($requestData['tax_rate']) ? (float)$requestData['tax_rate'] : null;
        $logo = isset($requestData['logo']) ? $requestData['logo'] : null;
        
        $loyaltyEnabled = isset($requestData['loyalty_enabled']) ? (int)$requestData['loyalty_enabled'] : null;
        $loyaltyPointEarnRate = isset($requestData['loyalty_point_earn_rate']) ? (float)$requestData['loyalty_point_earn_rate'] : null;
        $loyaltyPointValue = isset($requestData['loyalty_point_value']) ? (float)$requestData['loyalty_point_value'] : null;

        if (empty($name) || empty($email)) {
            Auth::jsonError('Shop name and email are required.', 400);
        }

        try {
            // Verify email uniqueness
            $stmt = DB::query('SELECT id FROM shops WHERE email = ? AND id != ?', [$email, $shopId]);
            if ($stmt->fetch()) {
                Auth::jsonError('Email is already registered by another shop.', 400);
            }

            $updateFields = ['name = ?', 'email = ?', 'phone = ?', 'address = ?'];
            $params = [$name, $email, $phone, $address];

            if ($taxRate !== null) {
                $updateFields[] = 'tax_rate = ?';
                $params[] = $taxRate;
            }

            if ($logo !== null) {
                $updateFields[] = 'logo = ?';
                $params[] = $logo;
            }

            if ($loyaltyEnabled !== null) {
                $updateFields[] = 'loyalty_enabled = ?';
                $params[] = $loyaltyEnabled;
            }

            if ($loyaltyPointEarnRate !== null) {
                $updateFields[] = 'loyalty_point_earn_rate = ?';
                $params[] = $loyaltyPointEarnRate;
            }

            if ($loyaltyPointValue !== null) {
                $updateFields[] = 'loyalty_point_value = ?';
                $params[] = $loyaltyPointValue;
            }

            $params[] = $shopId;

            DB::query('UPDATE shops SET ' . implode(', ', $updateFields) . ' WHERE id = ?', $params);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Shop details updated successfully.']);

        } catch (\Exception $e) {
            error_log('Update my shop details error: ' . $e->getMessage());
            Auth::jsonError('Server error updating shop details.', 500);
        }
    }

    public static function updateShopStatus($id, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $shopId = (int)$id;
        $status = $requestData['status'] ?? null;

        if (!in_array($status, ['active', 'inactive'])) {
            Auth::jsonError('Invalid status value.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM shops WHERE id = ?', [$shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Shop not found.', 404);
            }

            DB::query('UPDATE shops SET status = ? WHERE id = ?', [$status, $shopId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => "Shop status updated to $status."]);

        } catch (\Exception $e) {
            error_log('Update shop status error: ' . $e->getMessage());
            Auth::jsonError('Server error updating shop status.', 500);
        }
    }

    public static function updateShop($id, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $shopId = (int)$id;
        $name = $requestData['name'] ?? '';
        $email = $requestData['email'] ?? '';
        $phone = $requestData['phone'] ?? null;
        $address = $requestData['address'] ?? null;
        $taxRate = isset($requestData['tax_rate']) ? (float)$requestData['tax_rate'] : 10.00;
        $status = $requestData['status'] ?? 'active';

        if (empty($name) || empty($email)) {
            Auth::jsonError('Shop name and email are required.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM shops WHERE id = ?', [$shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Shop not found.', 404);
            }

            $stmt = DB::query('SELECT id FROM shops WHERE email = ? AND id != ?', [$email, $shopId]);
            if ($stmt->fetch()) {
                Auth::jsonError('Email already in use by another shop.', 400);
            }

            DB::query(
                'UPDATE shops SET name = ?, email = ?, phone = ?, address = ?, tax_rate = ?, status = ? WHERE id = ?',
                [$name, $email, $phone, $address, $taxRate, $status, $shopId]
            );

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Shop information updated successfully.']);

        } catch (\Exception $e) {
            error_log('Update shop details error: ' . $e->getMessage());
            Auth::jsonError('Server error updating shop details.', 500);
        }
    }

    public static function deleteShop($id) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $shopId = (int)$id;

        try {
            $stmt = DB::query('SELECT id FROM shops WHERE id = ?', [$shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Shop not found.', 404);
            }

            DB::beginTransaction();

            // Cascade delete: delete all shop records in dependency order
            DB::query('DELETE FROM sale_items WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM sales WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM held_bills WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM purchase_order_items WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM purchase_orders WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM cost_price_logs WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM wastages WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM products WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM customers WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM suppliers WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM other_costs WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM users WHERE shop_id = ?', [$shopId]);
            DB::query('DELETE FROM shops WHERE id = ?', [$shopId]);

            DB::commit();

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Tenant shop and associated users deleted successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Delete shop error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting shop tenant.', 500);
        }
    }

    public static function listShopUsers($id) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $shopId = (int)$id;

        try {
            $stmt = DB::query('SELECT id, name, email, role, status, created_at FROM users WHERE shop_id = ?', [$shopId]);
            $users = $stmt->fetchAll();

            foreach ($users as &$u) {
                $u['id'] = (int)$u['id'];
            }

            header('Content-Type: application/json');
            echo json_encode($users);

        } catch (\Exception $e) {
            error_log('Fetch shop users error: ' . $e->getMessage());
            Auth::jsonError('Server error fetching shop users.', 500);
        }
    }

    public static function resetShopUserPassword($id, $userId, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $shopId = (int)$id;
        $targetUserId = (int)$userId;
        $password = $requestData['password'] ?? '';

        if (empty($password) || strlen($password) < 6) {
            Auth::jsonError('Password must be at least 6 characters long.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM users WHERE id = ? AND shop_id = ?', [$targetUserId, $shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('User not found in this shop context.', 404);
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            DB::query('UPDATE users SET password_hash = ? WHERE id = ? AND shop_id = ?', [$passwordHash, $targetUserId, $shopId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Password reset successfully.']);

        } catch (\Exception $e) {
            error_log('Reset user password error: ' . $e->getMessage());
            Auth::jsonError('Server error resetting user password.', 500);
        }
    }

    public static function updateShopUserStatus($id, $userId, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $shopId = (int)$id;
        $targetUserId = (int)$userId;
        $status = $requestData['status'] ?? null;

        if (!in_array($status, ['active', 'inactive'])) {
            Auth::jsonError('Invalid status value.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM users WHERE id = ? AND shop_id = ?', [$targetUserId, $shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('User not found in this shop context.', 404);
            }

            DB::query('UPDATE users SET status = ? WHERE id = ? AND shop_id = ?', [$status, $targetUserId, $shopId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => "User account set to $status."]);

        } catch (\Exception $e) {
            error_log('Update user status error: ' . $e->getMessage());
            Auth::jsonError('Server error updating user status.', 500);
        }
    }

    // ==========================================
    // USERS (SUPER ADMIN SYSTEM CONTEXT)
    // ==========================================

    public static function listUsers() {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        try {
            $stmt = DB::query(
                'SELECT u.id, u.shop_id, u.name, u.email, u.role, u.status, u.created_at, s.name as shop_name 
                 FROM users u 
                 LEFT JOIN shops s ON u.shop_id = s.id 
                 ORDER BY u.created_at DESC'
            );
            $users = $stmt->fetchAll();

            foreach ($users as &$u) {
                $u['id'] = (int)$u['id'];
                $u['shop_id'] = $u['shop_id'] !== null ? (int)$u['shop_id'] : null;
            }

            header('Content-Type: application/json');
            echo json_encode($users);

        } catch (\Exception $e) {
            error_log('Fetch users error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving users list.', 500);
        }
    }

    public static function createUser($requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $shopId = $requestData['shop_id'] ?? null;
        $name = $requestData['name'] ?? '';
        $email = $requestData['email'] ?? '';
        $password = $requestData['password'] ?? '';
        $role = $requestData['role'] ?? null;

        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            Auth::jsonError('Please provide name, email, password, and role.', 400);
        }

        if (!in_array($role, ['super_admin', 'shop_admin', 'shop_staff'])) {
            Auth::jsonError('Invalid role specified.', 400);
        }

        if ($role !== 'super_admin' && empty($shopId)) {
            Auth::jsonError('Tenant shop identification is required for shop roles.', 400);
        }

        try {
            // Verify email uniqueness
            $stmt = DB::query('SELECT id FROM users WHERE email = ?', [$email]);
            if ($stmt->fetch()) {
                Auth::jsonError('Email is already registered by another account.', 400);
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            DB::query(
                'INSERT INTO users (shop_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
                [$role === 'super_admin' ? null : $shopId, $name, $email, $passwordHash, $role]
            );
            $newId = DB::lastInsertId();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'User profile created successfully.',
                'userId' => (int)$newId
            ]);

        } catch (\Exception $e) {
            error_log('Create user error: ' . $e->getMessage());
            Auth::jsonError('Server error creating user.', 500);
        }
    }

    public static function updateUser($id, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $userId = (int)$id;
        $shopId = $requestData['shop_id'] ?? null;
        $name = $requestData['name'] ?? '';
        $email = $requestData['email'] ?? '';
        $role = $requestData['role'] ?? null;
        $status = $requestData['status'] ?? 'active';

        if (empty($name) || empty($email) || empty($role)) {
            Auth::jsonError('Please provide name, email, and role.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM users WHERE id = ?', [$userId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('User not found.', 404);
            }

            $stmt = DB::query('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $userId]);
            if ($stmt->fetch()) {
                Auth::jsonError('Email already registered by another account.', 400);
            }

            DB::query(
                'UPDATE users SET shop_id = ?, name = ?, email = ?, role = ?, status = ? WHERE id = ?',
                [$role === 'super_admin' ? null : $shopId, $name, $email, $role, $status, $userId]
            );

            header('Content-Type: application/json');
            echo json_encode(['message' => 'User profile updated successfully.']);

        } catch (\Exception $e) {
            error_log('Update user error: ' . $e->getMessage());
            Auth::jsonError('Server error updating user profile.', 500);
        }
    }

    public static function deleteUser($id) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $userId = (int)$id;

        if ($userId === Auth::$user['id']) {
            Auth::jsonError('You cannot delete your own account.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM users WHERE id = ?', [$userId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('User not found.', 404);
            }

            // Check if user has associated transactions to prevent RESTRICT constraint violation
            $salesStmt = DB::query('SELECT COUNT(*) as count FROM sales WHERE user_id = ?', [$userId]);
            $salesCount = (int)$salesStmt->fetch()['count'];
            if ($salesCount > 0) {
                Auth::jsonError('Cannot delete user with existing sales transactions. Please suspend the user instead.', 400);
            }

            DB::query('DELETE FROM users WHERE id = ?', [$userId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'User profile deleted successfully.']);

        } catch (\Exception $e) {
            error_log('Delete user error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting user profile.', 500);
        }
    }

    // ==========================================
    // STAFF (SHOP ADMIN CONTEXT)
    // ==========================================

    public static function listStaff() {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $shopId = Auth::$shopId;

        try {
            $stmt = DB::query('SELECT id, name, email, role, status, allowed_sections, logo, created_at FROM users WHERE shop_id = ? ORDER BY name ASC', [$shopId]);
            $staff = $stmt->fetchAll();

            foreach ($staff as &$member) {
                $member['id'] = (int)$member['id'];
                if (is_string($member['allowed_sections'])) {
                    $member['allowed_sections'] = json_decode($member['allowed_sections'], true);
                }
            }

            header('Content-Type: application/json');
            echo json_encode($staff);

        } catch (\Exception $e) {
            error_log('Fetch staff error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving staff directory.', 500);
        }
    }

    public static function createStaff($requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $shopId = Auth::$shopId;
        $name = $requestData['name'] ?? '';
        $email = $requestData['email'] ?? '';
        $password = $requestData['password'] ?? '';
        $role = $requestData['role'] ?? 'shop_staff';
        $allowedSections = isset($requestData['allowed_sections']) ? $requestData['allowed_sections'] : null;

        if (empty($name) || empty($email) || empty($password)) {
            Auth::jsonError('Name, email, and password are required.', 400);
        }

        if (!in_array($role, ['shop_admin', 'shop_staff'])) {
            Auth::jsonError('Invalid role selection.', 400);
        }

        try {
            // Check email
            $stmt = DB::query('SELECT id FROM users WHERE email = ?', [$email]);
            if ($stmt->fetch()) {
                Auth::jsonError('Email is already registered.', 400);
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            DB::query(
                'INSERT INTO users (shop_id, name, email, password_hash, role, allowed_sections) VALUES (?, ?, ?, ?, ?, ?)',
                [$shopId, $name, $email, $passwordHash, $role, $allowedSections ? json_encode($allowedSections) : null]
            );
            $newId = DB::lastInsertId();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Staff profile added successfully.',
                'userId' => (int)$newId
            ]);

        } catch (\Exception $e) {
            error_log('Create staff error: ' . $e->getMessage());
            Auth::jsonError('Server error creating staff profile.', 500);
        }
    }

    public static function updateStaff($id, $requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $staffId = (int)$id;
        $shopId = Auth::$shopId;

        $name = $requestData['name'] ?? '';
        $email = $requestData['email'] ?? '';
        $password = $requestData['password'] ?? '';
        $role = $requestData['role'] ?? null;
        $status = $requestData['status'] ?? null;
        $allowedSections = isset($requestData['allowed_sections']) ? $requestData['allowed_sections'] : null;

        if (empty($name) || empty($email)) {
            Auth::jsonError('Name and email are required.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM users WHERE id = ? AND shop_id = ?', [$staffId, $shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Staff member not found.', 404);
            }

            $stmt = DB::query('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $staffId]);
            if ($stmt->fetch()) {
                Auth::jsonError('Email is already in use by another account.', 400);
            }

            $updateFields = ['name = ?', 'email = ?'];
            $params = [$name, $email];

            // Update password only if it's provided (non-empty string)
            if (isset($requestData['password']) && $requestData['password'] !== '') {
                if (strlen($password) < 6) {
                    Auth::jsonError('Password must be at least 6 characters long.', 400);
                }
                $updateFields[] = 'password_hash = ?';
                $params[] = password_hash($password, PASSWORD_BCRYPT);
            }

            if (!empty($role)) {
                $updateFields[] = 'role = ?';
                $params[] = $role;
            }

            if (!empty($status)) {
                $updateFields[] = 'status = ?';
                $params[] = $status;
            }

            if (array_key_exists('allowed_sections', $requestData)) {
                $updateFields[] = 'allowed_sections = ?';
                $params[] = $allowedSections ? json_encode($allowedSections) : null;
            }

            $params[] = $staffId;
            $params[] = $shopId;

            DB::query('UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ? AND shop_id = ?', $params);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Staff profile updated successfully.']);

        } catch (\Exception $e) {
            error_log('Update staff error: ' . $e->getMessage());
            Auth::jsonError('Server error updating staff profile.', 500);
        }
    }

    public static function deleteStaff($id) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $staffId = (int)$id;
        $shopId = Auth::$shopId;

        if ($staffId === Auth::$user['id']) {
            Auth::jsonError('You cannot delete your own account.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM users WHERE id = ? AND shop_id = ?', [$staffId, $shopId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Staff member not found.', 404);
            }

            DB::query('DELETE FROM users WHERE id = ? AND shop_id = ?', [$staffId, $shopId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Staff profile deleted successfully.']);

        } catch (\Exception $e) {
            error_log('Delete staff error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting staff profile.', 500);
        }
    }
}
