<?php
require_once '../config/config.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'verify_id':
            $id = intval($_POST['id'] ?? 0);
            $status = sanitize($_POST['status'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (!in_array($status, ['approved', 'rejected'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            // Update verification status
            $stmt = $pdo->prepare("
                UPDATE id_verifications 
                SET verification_status = ?, verified_by = ?, verified_at = NOW(), verification_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $admin_id, $notes, $id]);
            
            // If approved, update user verification status
            if ($status === 'approved') {
                $stmt = $pdo->prepare("SELECT user_id FROM id_verifications WHERE id = ?");
                $stmt->execute([$id]);
                $verification = $stmt->fetch();
                
                if ($verification) {
                    $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$verification['user_id']]);
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'ID verification updated']);
            break;
            
        case 'fund_account':
            $user_id = intval($_POST['user_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $description = sanitize($_POST['description'] ?? '');
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid amount']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            // Get current balance
            $stmt = $pdo->prepare("SELECT account_balance FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }
            
            $balance_before = $user['account_balance'];
            $balance_after = $balance_before + $amount;
            
            // Update user balance
            $stmt = $pdo->prepare("UPDATE users SET account_balance = ? WHERE id = ?");
            $stmt->execute([$balance_after, $user_id]);
            
            // Create transaction record
            $stmt = $pdo->prepare("
                INSERT INTO transactions 
                (user_id, transaction_type, amount, balance_before, balance_after, description, status, processed_by)
                VALUES (?, 'deposit', ?, ?, ?, ?, 'completed', ?)
            ");
            $stmt->execute([
                $user_id, $amount, $balance_before, $balance_after, 
                $description ?: 'Account funded by admin', $admin_id
            ]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Account funded successfully']);
            break;
            
        case 'verify_payment_proof':
            $id = intval($_POST['id'] ?? 0);
            $status = sanitize($_POST['status'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (!in_array($status, ['approved', 'rejected'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            // Update payment proof verification status
            $stmt = $pdo->prepare("
                UPDATE payment_proofs 
                SET verification_status = ?, verified_by = ?, verified_at = NOW(), verification_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $admin_id, $notes, $id]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Payment proof ' . $status . ' successfully']);
            break;
            
        case 'process_withdrawal':
            $id = intval($_POST['id'] ?? 0);
            $status = sanitize($_POST['status'] ?? '');
            
            if (!in_array($status, ['approved', 'rejected'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            // Get withdrawal request
            $stmt = $pdo->prepare("
                SELECT wr.*, u.account_balance 
                FROM withdrawal_requests wr
                JOIN users u ON wr.user_id = u.id
                WHERE wr.id = ?
            ");
            $stmt->execute([$id]);
            $withdrawal = $stmt->fetch();
            
            if (!$withdrawal) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Withdrawal request not found']);
                exit;
            }
            
            if ($status === 'approved') {
                // Check if user has sufficient balance
                if ($withdrawal['amount'] > $withdrawal['account_balance']) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
                    exit;
                }
                
                $balance_before = $withdrawal['account_balance'];
                $balance_after = $balance_before - $withdrawal['amount'];
                
                // Update user balance
                $stmt = $pdo->prepare("UPDATE users SET account_balance = ? WHERE id = ?");
                $stmt->execute([$balance_after, $withdrawal['user_id']]);
                
                // Create transaction record
                $stmt = $pdo->prepare("
                    INSERT INTO transactions 
                    (user_id, transaction_type, amount, balance_before, balance_after, description, status, processed_by)
                    VALUES (?, 'withdrawal', ?, ?, ?, ?, 'completed', ?)
                ");
                $stmt->execute([
                    $withdrawal['user_id'], 
                    $withdrawal['amount'], 
                    $balance_before, 
                    $balance_after,
                    'Withdrawal processed',
                    $admin_id
                ]);
                
                // Update withdrawal status
                $stmt = $pdo->prepare("
                    UPDATE withdrawal_requests 
                    SET status = 'processed', processed_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$admin_id, $id]);
            } else {
                // Just reject the withdrawal
                $stmt = $pdo->prepare("
                    UPDATE withdrawal_requests 
                    SET status = 'rejected', processed_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$admin_id, $id]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Withdrawal processed successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
