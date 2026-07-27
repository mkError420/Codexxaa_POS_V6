<?php
/**
 * Other Controller (Wastage, Customer Returns, Adjustments, Other Costs, Shops, Users/Staff)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

class OtherController {

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

    public static function updateWastage($id, $requestData) {
        Auth::authenticate();
        Auth::enforceTenant();
        Auth::authorize(['shop_admin']);

        $wastageId = (int)$id;
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

            // Get the original wastage record
            $stmt = DB::query('SELECT * FROM wastages WHERE id = ? AND shop_id = ? FOR UPDATE', [$wastageId, $shopId]);
            $originalWastage = $stmt->fetch();

            if (!$originalWastage) {
                DB::rollBack();
                Auth::jsonError('Wastage record not found.', 404);
            }

            // Revert the original stock change
            DB::query('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND shop_id = ?', [(int)$originalWastage['quantity'], (int)$originalWastage['product_id'], $shopId]);

            // Apply the new stock change
            $stmt = DB::query('SELECT cost_price FROM products WHERE id = ? AND shop_id = ? FOR UPDATE', [$productId, $shopId]);
            $product = $stmt->fetch();
            if (!$product) {
                DB::rollBack();
                Auth::jsonError('Product not found.', 404);
            }
            $costLoss = $quantity * (float)$product['cost_price'];

            DB::query('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND shop_id = ?', [$quantity, $productId, $shopId]);

            // Update the wastage record
            DB::query(
                'UPDATE wastages SET product_id = ?, quantity = ?, cost_loss = ?, reason = ?, notes = ?, adjusted_at = ? WHERE id = ? AND shop_id = ?',
                [$productId, $quantity, $costLoss, $reason, $notes, $adjustedAt, $wastageId, $shopId]
            );

            DB::commit();
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Wastage record updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Update wastage error: ' . $e->getMessage());
            Auth::jsonError('Server error updating wastage record.', 500);
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

    // ==========================================
    // SITE SETTINGS
    // ==========================================

    public static function getSiteSettings() {
        // No authentication required for public site settings (home page)
        try {
            $stmt = DB::query('SELECT setting_key, setting_value FROM site_settings');
            $settings = $stmt->fetchAll();

            $settingsArray = [];
            foreach ($settings as $setting) {
                $settingsArray[$setting['setting_key']] = $setting['setting_value'];
            }

            // Dynamically calculate active shop count
            $shopStmt = DB::query('SELECT COUNT(*) as count FROM shops WHERE status = ?', ['active']);
            $shopCount = $shopStmt->fetch();
            $settingsArray['active_shop_count'] = (int)$shopCount['count'];

            // Ensure all expected fields exist with defaults
            $settingsArray['site_name'] = $settingsArray['site_name'] ?? 'CodexaaPOS++';
            $settingsArray['site_description'] = $settingsArray['site_description'] ?? 'Modern Point of Sale For Your Business';
            $settingsArray['hero_content'] = $settingsArray['hero_content'] ?? 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.';
            $settingsArray['site_logo'] = $settingsArray['site_logo'] ?? '';

            header('Content-Type: application/json');
            echo json_encode($settingsArray);

        } catch (\Exception $e) {
            error_log('Fetch site settings error: ' . $e->getMessage());
            // Return defaults on error
            header('Content-Type: application/json');
            echo json_encode([
                'site_name' => 'CodexaaPOS++',
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

        if (empty($requestData)) {
            Auth::jsonError('No settings data provided.', 400);
        }

        try {
            DB::beginTransaction();

            foreach ($requestData as $key => $value) {
                // Validate key format (alphanumeric and underscores only)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                    DB::rollBack();
                    Auth::jsonError("Invalid setting key format: $key", 400);
                }

                // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both new and existing keys
                DB::query(
                    'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?',
                    [$key, $value, $value]
                );
            }

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
        // No authentication required for public pricing plans (home page)
        try {
            $stmt = DB::query('SELECT * FROM pricing_plans ORDER BY sort_order ASC, id ASC');
            $plans = $stmt->fetchAll();

            foreach ($plans as &$plan) {
                $plan['id'] = (int)$plan['id'];
                $plan['price'] = (float)$plan['price'];
                $plan['sort_order'] = (int)$plan['sort_order'];
                $plan['is_popular'] = (int)$plan['is_popular'] === 1;
                $plan['is_custom'] = (int)$plan['is_custom'] === 1;
                // Decode features JSON array
                if (is_string($plan['features'])) {
                    $plan['features'] = json_decode($plan['features'], true);
                    if (!is_array($plan['features'])) {
                        $plan['features'] = [];
                    }
                }
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

        $name = $requestData['name'] ?? '';
        $description = $requestData['description'] ?? '';
        $price = (float)($requestData['price'] ?? 0);
        $period = $requestData['period'] ?? 'month';
        $features = $requestData['features'] ?? [];
        $isPopular = isset($requestData['is_popular']) ? (int)$requestData['is_popular'] : 0;
        $isCustom = isset($requestData['is_custom']) ? (int)$requestData['is_custom'] : 0;
        $buttonText = $requestData['button_text'] ?? 'Get Started';
        $sortOrder = (int)($requestData['sort_order'] ?? 0);

        if (empty($name)) {
            Auth::jsonError('Plan name is required.', 400);
        }

        // Price validation: only required if not a custom plan
        if (!$isCustom && $price <= 0) {
            Auth::jsonError('Valid price is required for non-custom plans.', 400);
        }

        if (!in_array($period, ['month', 'year', 'week'])) {
            Auth::jsonError('Invalid period. Must be month, year, or week.', 400);
        }

        try {
            // Encode features as JSON array
            $featuresJson = is_array($features) ? json_encode($features) : '[]';

            // Try INSERT with is_custom column first (for updated databases)
            try {
                DB::query(
                    'INSERT INTO pricing_plans (name, description, price, period, features, is_popular, is_custom, button_text, sort_order) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$name, $description, $price, $period, $featuresJson, $isPopular, $isCustom, $buttonText, $sortOrder]
                );
            } catch (\Exception $e) {
                // If is_custom column doesn't exist, try without it (for older databases)
                if (strpos($e->getMessage(), 'is_custom') !== false) {
                    DB::query(
                        'INSERT INTO pricing_plans (name, description, price, period, features, is_popular, button_text, sort_order) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        [$name, $description, $price, $period, $featuresJson, $isPopular, $buttonText, $sortOrder]
                    );
                } else {
                    throw $e;
                }
            }
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
        $name = $requestData['name'] ?? '';
        $description = $requestData['description'] ?? '';
        $price = (float)($requestData['price'] ?? 0);
        $period = $requestData['period'] ?? 'month';
        $features = $requestData['features'] ?? [];
        $isPopular = isset($requestData['is_popular']) ? (int)$requestData['is_popular'] : 0;
        $isCustom = isset($requestData['is_custom']) ? (int)$requestData['is_custom'] : 0;
        $buttonText = $requestData['button_text'] ?? 'Get Started';
        $sortOrder = (int)($requestData['sort_order'] ?? 0);

        if (empty($name)) {
            Auth::jsonError('Plan name is required.', 400);
        }

        // Price validation: only required if not a custom plan
        if (!$isCustom && $price <= 0) {
            Auth::jsonError('Valid price is required for non-custom plans.', 400);
        }

        if (!in_array($period, ['month', 'year', 'week'])) {
            Auth::jsonError('Invalid period. Must be month, year, or week.', 400);
        }

        try {
            // Check if plan exists
            $stmt = DB::query('SELECT id FROM pricing_plans WHERE id = ?', [$planId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Pricing plan not found.', 404);
            }

            // Encode features as JSON array
            $featuresJson = is_array($features) ? json_encode($features) : '[]';

            // Try UPDATE with is_custom column first (for updated databases)
            try {
                DB::query(
                    'UPDATE pricing_plans SET name = ?, description = ?, price = ?, period = ?, features = ?, is_popular = ?, is_custom = ?, button_text = ?, sort_order = ? WHERE id = ?',
                    [$name, $description, $price, $period, $featuresJson, $isPopular, $isCustom, $buttonText, $sortOrder, $planId]
                );
            } catch (\Exception $e) {
                // If is_custom column doesn't exist, try without it (for older databases)
                if (strpos($e->getMessage(), 'is_custom') !== false) {
                    DB::query(
                        'UPDATE pricing_plans SET name = ?, description = ?, price = ?, period = ?, features = ?, is_popular = ?, button_text = ?, sort_order = ? WHERE id = ?',
                        [$name, $description, $price, $period, $featuresJson, $isPopular, $buttonText, $sortOrder, $planId]
                    );
                } else {
                    throw $e;
                }
            }

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
            // Check if plan exists
            $stmt = DB::query('SELECT id FROM pricing_plans WHERE id = ?', [$planId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Pricing plan not found.', 404);
            }

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
            $stmt = DB::query(
                'SELECT pp.*, p.name as plan_name, p.price as plan_price, p.period as plan_period,
                        pm.name as payment_method_name
                 FROM plan_purchases pp 
                 LEFT JOIN pricing_plans p ON pp.plan_id = p.id 
                 LEFT JOIN payment_methods pm ON pp.payment_method_id = pm.id
                 ORDER BY pp.created_at DESC'
            );
            $purchases = $stmt->fetchAll();

            foreach ($purchases as &$purchase) {
                $purchase['id'] = (int)$purchase['id'];
                $purchase['plan_id'] = (int)$purchase['plan_id'];
                $purchase['plan_price'] = (float)$purchase['plan_price'];
                $purchase['shop_id'] = $purchase['shop_id'] ? (int)$purchase['shop_id'] : null;
                // Use payment_method_name if available, otherwise fall back to payment_method
                // But preserve 'custom_request' value for custom plan requests
                if (!empty($purchase['payment_method_name']) && $purchase['payment_method'] !== 'custom_request') {
                    $purchase['payment_method'] = $purchase['payment_method_name'];
                }
                // Include shop registration fields if they exist
                $purchase['shop_name'] = $purchase['shop_name'] ?? null;
                $purchase['shop_address'] = $purchase['shop_address'] ?? null;
                $purchase['shop_phone'] = $purchase['shop_phone'] ?? null;
                $purchase['shop_city'] = $purchase['shop_city'] ?? null;
                $purchase['shop_country'] = $purchase['shop_country'] ?? null;
                // Include custom request fields if they exist
                $purchase['company'] = $purchase['company'] ?? null;
                $purchase['custom_message'] = $purchase['custom_message'] ?? null;
            }

            header('Content-Type: application/json');
            echo json_encode($purchases);

        } catch (\Exception $e) {
            error_log('Fetch plan purchases error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving plan purchases.', 500);
        }
    }

    public static function createPlanPurchase($requestData) {
        // No authentication required for public plan purchase (home page)
        $planId = $requestData['plan_id'] ?? null;
        $userName = $requestData['user_name'] ?? '';
        $userEmail = $requestData['user_email'] ?? '';
        $userPhone = $requestData['user_phone'] ?? '';
        $paymentMethod = $requestData['payment_method'] ?? '';
        $paymentMethodId = $requestData['payment_method_id'] ?? null;
        $notes = $requestData['notes'] ?? '';
        $transactionId = $requestData['transaction_id'] ?? '';
        $bankName = $requestData['bank_name'] ?? '';
        $accountNumber = $requestData['account_number'] ?? '';
        $cardLastFour = $requestData['card_last_four'] ?? '';
        $paymentProof = $requestData['payment_proof'] ?? '';
        $paymentDate = $requestData['payment_date'] ?? null;
        
        // Shop registration fields
        $shopName = $requestData['shop_name'] ?? '';
        $shopAddress = $requestData['shop_address'] ?? '';
        $shopPhone = $requestData['shop_phone'] ?? '';
        $shopCity = $requestData['shop_city'] ?? '';
        $shopCountry = $requestData['shop_country'] ?? '';

        if (empty($planId) || empty($userName) || empty($userEmail)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Plan ID, user name, and user email are required.']);
            return;
        }

        try {
            DB::query(
                'INSERT INTO plan_purchases (plan_id, user_name, user_email, user_phone, payment_method, payment_method_id, notes, transaction_id, bank_name, account_number, card_last_four, payment_proof, payment_date, shop_name, shop_address, shop_phone, shop_city, shop_country) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$planId, $userName, $userEmail, $userPhone, $paymentMethod, $paymentMethodId, $notes, $transactionId, $bankName, $accountNumber, $cardLastFour, $paymentProof, $paymentDate, $shopName, $shopAddress, $shopPhone, $shopCity, $shopCountry]
            );
            $newId = DB::lastInsertId();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Plan purchase submitted successfully.',
                'purchaseId' => (int)$newId
            ]);

        } catch (\Exception $e) {
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
        $status = $requestData['status'] ?? 'pending';
        $shopId = isset($requestData['shop_id']) ? $requestData['shop_id'] : null;
        $notes = $requestData['notes'] ?? null;

        if (!in_array($status, ['pending', 'approved', 'rejected'])) {
            Auth::jsonError('Invalid status value.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM plan_purchases WHERE id = ?', [$purchaseId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Plan purchase not found.', 404);
            }

            $updateFields = ['status = ?'];
            $params = [$status];

            if ($shopId !== null) {
                $updateFields[] = 'shop_id = ?';
                $params[] = $shopId;
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
            $stmt = DB::query('SELECT id FROM plan_purchases WHERE id = ?', [$purchaseId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Plan purchase not found.', 404);
            }

            DB::query('DELETE FROM plan_purchases WHERE id = ?', [$purchaseId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Plan purchase deleted successfully.']);

        } catch (\Exception $e) {
            error_log('Delete plan purchase error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting plan purchase.', 500);
        }
    }

    public static function createCustomPlanRequest($requestData) {
        // No authentication required for public custom plan requests (home page)
        $planId = $requestData['plan_id'] ?? null;
        $planName = $requestData['plan_name'] ?? '';
        $name = $requestData['name'] ?? '';
        $email = $requestData['email'] ?? '';
        $phone = $requestData['phone'] ?? '';
        $company = $requestData['company'] ?? '';
        $message = $requestData['message'] ?? '';

        if (empty($name) || empty($email) || empty($message)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Name, email, and message are required.']);
            return;
        }

        try {
            // Try INSERT with new columns first (for updated databases)
            try {
                DB::query(
                    'INSERT INTO plan_purchases (plan_id, user_name, user_email, user_phone, company, custom_message, notes, status, payment_method) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$planId, $name, $email, $phone, $company, $message, $company . ' - ' . $message, 'pending', 'custom_request']
                );
                error_log('Custom plan request created with new columns');
            } catch (\Exception $e) {
                // If new columns don't exist, use old method (for older databases)
                error_log('New columns not available, falling back to old method: ' . $e->getMessage());
                DB::query(
                    'INSERT INTO plan_purchases (plan_id, user_name, user_email, user_phone, notes, status, payment_method) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$planId, $name, $email, $phone, $company . ' - ' . $message, 'pending', 'custom_request']
                );
                error_log('Custom plan request created with fallback method');
            }
            $newId = DB::lastInsertId();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Custom plan request submitted successfully.',
                'requestId' => (int)$newId
            ]);

        } catch (\Exception $e) {
            error_log('Create custom plan request error: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Server error processing custom plan request: ' . $e->getMessage()]);
        }
    }

    // ==========================================
    // PAYMENT METHODS
    // ==========================================

    public static function listPaymentMethods() {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        try {
            $stmt = DB::query('SELECT * FROM payment_methods ORDER BY name ASC');
            $methods = $stmt->fetchAll();

            foreach ($methods as &$method) {
                $method['id'] = (int)$method['id'];
                $method['is_active'] = (int)$method['is_active'] === 1;
            }

            header('Content-Type: application/json');
            echo json_encode($methods);

        } catch (\Exception $e) {
            error_log('Fetch payment methods error: ' . $e->getMessage());
            Auth::jsonError('Server error retrieving payment methods.', 500);
        }
    }

    public static function getActivePaymentMethods() {
        // No authentication required for public active payment methods (home page)
        try {
            $stmt = DB::query('SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY name ASC');
            $methods = $stmt->fetchAll();

            foreach ($methods as &$method) {
                $method['id'] = (int)$method['id'];
                $method['is_active'] = (int)$method['is_active'] === 1;
            }

            header('Content-Type: application/json');
            echo json_encode($methods);

        } catch (\Exception $e) {
            error_log('Fetch active payment methods error: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([]);
        }
    }

    public static function createPaymentMethod($requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $name = $requestData['name'] ?? '';
        $type = $requestData['type'] ?? '';
        $description = $requestData['description'] ?? '';
        $instructions = $requestData['instructions'] ?? '';
        $isActive = isset($requestData['is_active']) ? (int)$requestData['is_active'] : 1;

        if (empty($name) || empty($type)) {
            Auth::jsonError('Name and type are required.', 400);
        }

        try {
            DB::query(
                'INSERT INTO payment_methods (name, type, description, instructions, is_active) 
                 VALUES (?, ?, ?, ?, ?)',
                [$name, $type, $description, $instructions, $isActive]
            );
            $newId = DB::lastInsertId();

            header('Content-Type: application/json');
            http_response_code(201);
            echo json_encode([
                'message' => 'Payment method created successfully.',
                'methodId' => (int)$newId
            ]);

        } catch (\Exception $e) {
            error_log('Create payment method error: ' . $e->getMessage());
            Auth::jsonError('Server error creating payment method.', 500);
        }
    }

    public static function updatePaymentMethod($id, $requestData) {
        Auth::authenticate();
        Auth::authorize(['super_admin']);

        $methodId = (int)$id;
        $name = $requestData['name'] ?? '';
        $type = $requestData['type'] ?? '';
        $description = $requestData['description'] ?? '';
        $instructions = $requestData['instructions'] ?? '';
        $isActive = isset($requestData['is_active']) ? (int)$requestData['is_active'] : 1;

        if (empty($name) || empty($type)) {
            Auth::jsonError('Name and type are required.', 400);
        }

        try {
            $stmt = DB::query('SELECT id FROM payment_methods WHERE id = ?', [$methodId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Payment method not found.', 404);
            }

            DB::query(
                'UPDATE payment_methods SET name = ?, type = ?, description = ?, instructions = ?, is_active = ? WHERE id = ?',
                [$name, $type, $description, $instructions, $isActive, $methodId]
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
            $stmt = DB::query('SELECT id FROM payment_methods WHERE id = ?', [$methodId]);
            if (!$stmt->fetch()) {
                Auth::jsonError('Payment method not found.', 404);
            }

            DB::query('DELETE FROM payment_methods WHERE id = ?', [$methodId]);

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Payment method deleted successfully.']);

        } catch (\Exception $e) {
            error_log('Delete payment method error: ' . $e->getMessage());
            Auth::jsonError('Server error deleting payment method.', 500);
        }
    }

    // ==========================================
    // FILE UPLOADS
    // ==========================================

    public static function uploadPaymentProof() {
        // No authentication required for public file upload (plan purchase)
        try {
            if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => 'No file uploaded or upload error.']);
                return;
            }

            $file = $_FILES['payment_proof'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
            if (!in_array($file['type'], $allowedTypes)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, GIF, or WebP images are allowed.']);
                return;
            }

            // Validate file size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => 'File too large. Maximum size is 5MB.']);
                return;
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'payment_proof_' . time() . '_' . uniqid() . '.' . $extension;
            $uploadDir = __DIR__ . '/../uploads/payment_proofs/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploadPath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save uploaded file.']);
                return;
            }

            // Return the URL path (relative to backend)
            $url = '/uploads/payment_proofs/' . $filename;

            header('Content-Type: application/json');
            echo json_encode(['url' => $url]);

        } catch (\Exception $e) {
            error_log('Upload payment proof error: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Server error uploading file.']);
        }
    }
}
