<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('../login.php');
}

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Get pending withdrawal requests
$stmt = $pdo->prepare("
    SELECT * FROM withdrawal_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$withdrawals = $stmt->fetchAll();

// Get ID verification status
$stmt = $pdo->prepare("
    SELECT * FROM id_verifications 
    WHERE user_id = ? 
    ORDER BY submitted_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$id_verification = $stmt->fetch();

// Get payment proof status
$stmt = $pdo->prepare("
    SELECT * FROM payment_proofs 
    WHERE user_id = ? 
    ORDER BY submitted_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$payment_proof = $stmt->fetch();

// Check if user can withdraw (payment proof must be approved)
$can_withdraw = $payment_proof && $payment_proof['verification_status'] === 'approved';

// Get success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Forge Fund</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #47763b 0%, #3a5f2f 100%);
            color: white;
            padding: 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 70px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo h1 {
            font-size: 1.5em;
            font-weight: 700;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-welcome {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2em;
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 0.95em;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
            padding: 8px;
        }
        
        .menu-toggle span {
            width: 25px;
            height: 3px;
            background: white;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .menu-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }
        
        .menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }
        
        .menu-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #47763b;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(71, 118, 59, 0.1) 0%, rgba(58, 95, 47, 0.05) 100%);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.85em;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
        }
        
        .stat-card .amount {
            font-size: 2.8em;
            font-weight: 700;
            color: #47763b;
            position: relative;
            z-index: 1;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 30px;
            transition: box-shadow 0.3s;
        }
        
        .card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-header h2 {
            color: #333;
            font-size: 1.6em;
            font-weight: 700;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #47763b 0%, #3a5f2f 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(71, 118, 59, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }
        
        .status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status.approved, .status.completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status.rejected, .status.failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .verification-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .verification-badge.verified {
            background: #d4edda;
            color: #155724;
        }
        
        .verification-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .verification-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #47763b;
            box-shadow: 0 0 0 3px rgba(71, 118, 59, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 8px;
            color: #666;
            font-size: 0.85em;
        }
        
        .error, .success {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }
        
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-wrap: wrap;
                padding: 15px 20px;
            }
            
            .menu-toggle {
                display: flex;
            }
            
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 0;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid rgba(255,255,255,0.2);
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .nav-links a {
                width: 100%;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 5px;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .user-welcome {
                font-size: 0.9em;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stat-card {
                padding: 25px;
            }
            
            .stat-card .amount {
                font-size: 2.2em;
            }
            
            .card {
                padding: 20px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-wrapper {
                margin: 0 -20px;
                padding: 0 20px;
            }
            
            table {
                font-size: 0.9em;
            }
            
            th, td {
                padding: 12px 8px;
            }
            
            .modal-content {
                padding: 25px;
                width: 95%;
            }
        }
        
        @media (max-width: 480px) {
            .logo h1 {
                font-size: 1.2em;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 1em;
            }
            
            .stat-card .amount {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Forge Fund</h1>
            </div>
            <div class="user-info">
                <div class="user-welcome">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1)); ?></div>
                    <span>Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</span>
                </div>
                <div class="menu-toggle" onclick="toggleMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="nav-links" id="navLinks">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="settings.php">Settings</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="error" style="background: #fee; color: #c33; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c33;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success" style="background: #efe; color: #3c3; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3c3;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Account Balance</h3>
                <div class="amount"><?php echo formatCurrency($user['account_balance']); ?></div>
            </div>
            <div class="stat-card">
                <h3>ID Verification</h3>
                <?php if ($id_verification): ?>
                    <span class="verification-badge <?php echo $id_verification['verification_status']; ?>">
                        <?php echo ucfirst($id_verification['verification_status']); ?>
                    </span>
                <?php else: ?>
                    <span class="verification-badge pending">Not Submitted</span>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <h3>Account Status</h3>
                <span class="verification-badge <?php echo $user['is_active'] ? 'verified' : 'rejected'; ?>">
                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="stat-card">
                <h3>Payment Proof</h3>
                <?php if ($payment_proof): ?>
                    <span class="verification-badge <?php echo $payment_proof['verification_status']; ?>">
                        <?php echo ucfirst($payment_proof['verification_status']); ?>
                    </span>
                <?php else: ?>
                    <span class="verification-badge pending">Not Submitted</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Payment Address</h2>
            </div>
            <div style="margin-bottom: 20px;">
                <p style="margin-bottom: 15px; color: #666; font-size: 0.95em;">Send your payment to the Bitcoin wallet address below:</p>
                <div style="background: #f5f7fa; padding: 20px; border-radius: 10px; border: 2px solid #e0e0e0; margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
                        <code style="font-size: 1.1em; font-weight: 600; color: #47763b; word-break: break-all; flex: 1; min-width: 200px;"><?php echo defined('BITCOIN_WALLET') ? BITCOIN_WALLET : 'bc1q4ry2xj5l9stya2mdcqdk368u00h23s6fr550xv'; ?></code>
                        <button onclick="copyAddress()" style="background: #47763b; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.3s;" onmouseover="this.style.background='#3a5f2f'" onmouseout="this.style.background='#47763b'">Copy Address</button>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="openPaymentProofModal()" style="width: 100%;">Upload Proof of Payment</button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Withdrawal Request</h2>
            </div>
            <?php if ($can_withdraw): ?>
                <button class="btn btn-primary" onclick="openWithdrawalModal()">Request Withdrawal</button>
            <?php else: ?>
                <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                    <p style="margin: 0; color: #856404; font-weight: 500;">
                        <?php if (!$payment_proof): ?>
                            ⚠️ Please upload proof of payment first to enable withdrawals.
                        <?php elseif ($payment_proof['verification_status'] === 'pending'): ?>
                            ⏳ Your payment proof is pending approval. Withdrawals will be enabled once approved.
                        <?php elseif ($payment_proof['verification_status'] === 'rejected'): ?>
                            ❌ Your payment proof was rejected. Please upload a new proof of payment.
                        <?php endif; ?>
                    </p>
                </div>
                <button class="btn btn-primary" onclick="openWithdrawalModal()" disabled style="opacity: 0.6; cursor: not-allowed;">Request Withdrawal</button>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Recent Transactions</h2>
            </div>
            <?php if (empty($transactions)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">No transactions yet.</p>
            <?php else: ?>
                <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance After</th>
                            <th>Status</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                <td><?php echo ucfirst($transaction['transaction_type']); ?></td>
                                <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                <td><?php echo formatCurrency($transaction['balance_after']); ?></td>
                                <td><span class="status <?php echo $transaction['status']; ?>"><?php echo ucfirst($transaction['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($transaction['description'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Withdrawal Requests</h2>
            </div>
            <?php if (empty($withdrawals)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">No withdrawal requests yet.</p>
            <?php else: ?>
                <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $withdrawal): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($withdrawal['created_at'])); ?></td>
                                <td><?php echo formatCurrency($withdrawal['amount']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $withdrawal['withdrawal_method'])); ?></td>
                                <td><span class="status <?php echo $withdrawal['status']; ?>"><?php echo ucfirst($withdrawal['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Withdrawal Modal -->
    <div id="withdrawalModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Request Withdrawal</h2>
            <form method="POST" action="process_withdrawal.php">
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" name="amount" step="0.01" min="1" max="<?php echo $user['account_balance']; ?>" required>
                    <small>Available balance: <?php echo formatCurrency($user['account_balance']); ?></small>
                </div>
                <div class="form-group">
                    <label>Withdrawal Method</label>
                    <select name="withdrawal_method" required>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                        <option value="wire">Wire Transfer</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" required>
                </div>
                <div class="form-group">
                    <label>Account Holder Name</label>
                    <input type="text" name="account_holder_name" required>
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" required>
                </div>
                <div class="form-group">
                    <label>Routing Number</label>
                    <input type="text" name="routing_number" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Submit Request</button>
                    <button type="button" class="btn btn-secondary" onclick="closeWithdrawalModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Payment Proof Modal -->
    <div id="paymentProofModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Upload Proof of Payment</h2>
            <form id="paymentProofForm" method="POST" action="process_payment_proof" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Proof of Payment File</label>
                    <input type="file" name="proof_file" id="proof_file_input" accept="image/jpeg,image/jpg,image/png,image/gif,application/pdf" required>
                    <small>Upload a screenshot or photo of your payment transaction. Accepted formats: JPG, PNG, GIF, PDF (Max 5MB)</small>
                </div>
                <div id="paymentProofError" style="display: none; background: #fee; color: #c33; padding: 10px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #c33;"></div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" id="paymentProofSubmitBtn" style="flex: 1;">Upload Proof</button>
                    <button type="button" class="btn btn-secondary" onclick="closePaymentProofModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            const menuToggle = document.querySelector('.menu-toggle');
            navLinks.classList.toggle('active');
            menuToggle.classList.toggle('active');
        }
        
        function copyAddress() {
            const address = '<?php echo defined('BITCOIN_WALLET') ? BITCOIN_WALLET : 'bc1q4ry2xj5l9stya2mdcqdk368u00h23s6fr550xv'; ?>';
            navigator.clipboard.writeText(address).then(function() {
                alert('Bitcoin address copied to clipboard!');
            }, function(err) {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = address;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Bitcoin address copied to clipboard!');
            });
        }
        
        function openPaymentProofModal() {
            document.getElementById('paymentProofModal').style.display = 'flex';
            document.getElementById('paymentProofForm').reset();
            document.getElementById('paymentProofError').style.display = 'none';
            document.getElementById('paymentProofError').textContent = '';
        }
        
        function closePaymentProofModal() {
            document.getElementById('paymentProofModal').style.display = 'none';
            document.getElementById('paymentProofForm').reset();
            document.getElementById('paymentProofError').style.display = 'none';
            document.getElementById('paymentProofError').textContent = '';
        }
        
        // Handle payment proof form submission (wait for DOM to be ready)
        const paymentProofForm = document.getElementById('paymentProofForm');
        if (paymentProofForm) {
            paymentProofForm.addEventListener('submit', function(e) {
                const fileInput = document.getElementById('proof_file_input');
                const errorDiv = document.getElementById('paymentProofError');
                const submitBtn = document.getElementById('paymentProofSubmitBtn');
                
                // Reset error display
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
                
                // Validate file is selected
                if (!fileInput.files || fileInput.files.length === 0) {
                    e.preventDefault();
                    errorDiv.textContent = 'Please select a file to upload.';
                    errorDiv.style.display = 'block';
                    return false;
                }
                
                const file = fileInput.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                // Validate file size
                if (file.size > maxSize) {
                    e.preventDefault();
                    errorDiv.textContent = 'File size too large. Maximum size is 5MB.';
                    errorDiv.style.display = 'block';
                    return false;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    errorDiv.textContent = 'Invalid file type. Only JPG, PNG, GIF, and PDF files are allowed.';
                    errorDiv.style.display = 'block';
                    return false;
                }
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
                
                // Allow form to submit normally if validation passes
                // Don't prevent default - let the form submit
            });
        }
        
        function openWithdrawalModal() {
            document.getElementById('withdrawalModal').style.display = 'flex';
        }
        
        function closeWithdrawalModal() {
            document.getElementById('withdrawalModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const withdrawalModal = document.getElementById('withdrawalModal');
            const paymentProofModal = document.getElementById('paymentProofModal');
            if (event.target == withdrawalModal) {
                closeWithdrawalModal();
            }
            if (event.target == paymentProofModal) {
                closePaymentProofModal();
            }
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navLinks = document.getElementById('navLinks');
            const menuToggle = document.querySelector('.menu-toggle');
            const userInfo = document.querySelector('.user-info');
            
            if (!userInfo.contains(event.target) && navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });
    </script>
</body>
</html>
