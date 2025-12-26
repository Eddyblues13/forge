<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn() || isAdmin()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('login.php');
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
        }
        
        .header {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            font-size: 1.8em;
            font-weight: 700;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card .amount {
            font-size: 2.5em;
            font-weight: 700;
            color: #22c55e;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-header h2 {
            color: #333;
            font-size: 1.5em;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
        }
        
        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
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
            padding: 8px 16px;
            border-radius: 20px;
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
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #22c55e;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Forge Fund Dashboard</h1>
            </div>
            <div class="user-info">
                <div class="nav-links">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                    <a href="login.php" style="background: rgba(255,255,255,0.2); border-radius: 5px;">Login</a>
                    <a href="signup.php" style="background: rgba(255,255,255,0.2); border-radius: 5px;">Sign Up</a>
                </div>
                <div>
                    Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!
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
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Withdrawal Request</h2>
            </div>
            <button class="btn btn-primary" onclick="openWithdrawalModal()">Request Withdrawal</button>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Recent Transactions</h2>
            </div>
            <?php if (empty($transactions)): ?>
                <p>No transactions yet.</p>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Withdrawal Requests</h2>
            </div>
            <?php if (empty($withdrawals)): ?>
                <p>No withdrawal requests yet.</p>
            <?php else: ?>
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
    
    <script>
        function openWithdrawalModal() {
            document.getElementById('withdrawalModal').style.display = 'flex';
        }
        
        function closeWithdrawalModal() {
            document.getElementById('withdrawalModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('withdrawalModal');
            if (event.target == modal) {
                closeWithdrawalModal();
            }
        }
    </script>
</body>
</html>
