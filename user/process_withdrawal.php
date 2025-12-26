<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $withdrawal_method = sanitize($_POST['withdrawal_method'] ?? '');
    $bank_name = sanitize($_POST['bank_name'] ?? '');
    $account_holder_name = sanitize($_POST['account_holder_name'] ?? '');
    $account_number = sanitize($_POST['account_number'] ?? '');
    $routing_number = sanitize($_POST['routing_number'] ?? '');
    
    // Get user current balance
    $stmt = $pdo->prepare("SELECT account_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        redirect('dashboard.php');
    }
    
    if ($amount <= 0) {
        $_SESSION['error'] = 'Invalid amount';
        redirect('dashboard.php');
    }
    
    if ($amount > $user['account_balance']) {
        $_SESSION['error'] = 'Insufficient balance';
        redirect('dashboard.php');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create withdrawal request
        $stmt = $pdo->prepare("
            INSERT INTO withdrawal_requests 
            (user_id, amount, withdrawal_method, bank_name, account_number, routing_number, account_holder_name, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $user_id, $amount, $withdrawal_method, $bank_name, 
            $account_number, $routing_number, $account_holder_name
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = 'Withdrawal request submitted successfully';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Failed to submit withdrawal request';
    }
}

redirect('dashboard.php');
