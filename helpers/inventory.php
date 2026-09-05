<?php
// ============================================================
// FILE: helpers/inventory.php
// PURPOSE: Handles stock adjustments and inventory logs safely.
// ============================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

function adjust_inventory(
    int $productId,
    int $quantityChange,
    string $changeType,
    string $reason,
    ?string $referenceId = null,
    ?PDO $customDb = null
): bool {
    // Use the provided database connection or get the default one
    $db = $customDb ?? get_db();

    // Step 1: Check if a database transaction is already running
    // If the caller already started a transaction, do not start another one
    $startedTransaction = false;
    if (!$db->inTransaction()) {
        $db->beginTransaction();
        $startedTransaction = true;
    }

    try {
        // Step 2: Fetch current product stock with row lock
        $stmt = $db->prepare("SELECT id, vendor_id, stock_quantity, name FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception("Product not found for inventory adjustment.");
        }

        $prevQty = (int)$product['stock_quantity'];
        $newQty  = $prevQty + $quantityChange;

        if ($newQty < 0) {
            throw new Exception("Insufficient stock for plant: " . $product['name']);
        }

        // Step 3: Update stock in products table
        $updateStmt = $db->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newQty, $productId]);

        // Step 4: Record history in inventory_transactions table
        $logStmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (product_id, vendor_id, change_type, quantity_change, previous_quantity, new_quantity, reason, reference_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $productId,
            $product['vendor_id'],
            $changeType,
            $quantityChange,
            $prevQty,
            $newQty,
            $reason,
            $referenceId
        ]);

        // Step 5: Commit changes only if this function started the transaction
        if ($startedTransaction && $db->inTransaction()) {
            $db->commit();
        }
        return true;

    } catch (Exception $e) {
        // Only rollback if this function started the transaction; otherwise let caller handle rollback
        if ($startedTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Inventory error: " . $e->getMessage());
        throw $e;
    }
}

