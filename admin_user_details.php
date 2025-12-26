<?php
require_once 'config.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$user_id = intval($_GET['id'] ?? 0);

if (!$user_id) {
    redirect('admin_users.php');
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('admin_users.php');
}

// Get user transactions
$stmt = $pdo->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Get withdrawal requests
$stmt = $pdo->prepare("
    SELECT * FROM withdrawal_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$withdrawals = $stmt->fetchAll();

// Get ID verifications
$stmt = $pdo->prepare("
    SELECT * FROM id_verifications 
    WHERE user_id = ? 
    ORDER BY submitted_at DESC
");
$stmt->execute([$user_id]);
$id_verifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Admin Dashboard</title>
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
            max-width: 1400px;
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .card-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-header h2 {
            color: #333;
            font-size: 1.5em;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-item label {
            display: block;
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-item .value {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9em;
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
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>User Details</h1>
            </div>
            <div class="nav-links">
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_users.php">Users</a>
                <a href="logout.php">Logout</a>
                <a href="login.php" style="background: rgba(255,255,255,0.2); border-radius: 5px;">Login</a>
                <a href="signup.php" style="background: rgba(255,255,255,0.2); border-radius: 5px;">Sign Up</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>User Information</h2>
                <button class="btn btn-primary" onclick="fundAccount(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>', <?php echo $user['account_balance']; ?>)">Fund Account</button>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <label>Name</label>
                    <div class="value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <div class="value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="info-item">
                    <label>Username</label>
                    <div class="value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                <div class="info-item">
                    <label>Phone</label>
                    <div class="value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>Account Balance</label>
                    <div class="value"><?php echo formatCurrency($user['account_balance']); ?></div>
                </div>
                <div class="info-item">
                    <label>Status</label>
                    <div class="value">
                        <span class="status <?php echo $user['is_active'] ? 'approved' : 'rejected'; ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <label>Address</label>
                    <div class="value"><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>City, State, Zip</label>
                    <div class="value">
                        <?php 
                        $location = [];
                        if ($user['city']) $location[] = $user['city'];
                        if ($user['state']) $location[] = $user['state'];
                        if ($user['zip_code']) $location[] = $user['zip_code'];
                        echo htmlspecialchars(implode(', ', $location) ?: 'N/A');
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Joined</label>
                    <div class="value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>ID Verifications</h2>
            </div>
            <?php if (empty($id_verifications)): ?>
                <p>No ID verifications submitted.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Type</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Verified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($id_verifications as $id_ver): ?>
                            <tr>
                                <td><?php echo ucfirst(str_replace('_', ' ', $id_ver['id_type'])); ?></td>
                                <td><span class="status <?php echo $id_ver['verification_status']; ?>"><?php echo ucfirst($id_ver['verification_status']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($id_ver['submitted_at'])); ?></td>
                                <td><?php echo $id_ver['verified_at'] ? date('M d, Y', strtotime($id_ver['verified_at'])) : 'N/A'; ?></td>
                                <td><a href="<?php echo htmlspecialchars($id_ver['id_file_path']); ?>" target="_blank" class="btn btn-primary btn-sm">View ID</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Transactions</h2>
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
                            <th>Balance Before</th>
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
                                <td><?php echo formatCurrency($transaction['balance_before']); ?></td>
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
                            <th>Bank Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $withdrawal): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($withdrawal['created_at'])); ?></td>
                                <td><?php echo formatCurrency($withdrawal['amount']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $withdrawal['withdrawal_method'])); ?></td>
                                <td><span class="status <?php echo $withdrawal['status']; ?>"><?php echo ucfirst($withdrawal['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($withdrawal['bank_name'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Fund Account Modal -->
    <div id="fundModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 15px; max-width: 600px; width: 90%;">
            <h2 style="margin-bottom: 20px;">Fund User Account</h2>
            <form id="fundForm">
                <input type="hidden" id="fund_user_id" name="user_id">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">User: <span id="fund_user_name"></span></label>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Current Balance: <span id="fund_current_balance"></span></label>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Amount to Add</label>
                    <input type="number" id="fund_amount" name="amount" step="0.01" min="0.01" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Description</label>
                    <textarea id="fund_description" name="description" rows="3" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Fund Account</button>
                    <button type="button" class="btn btn-danger" onclick="closeFundModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function fundAccount(userId, userName, currentBalance) {
            document.getElementById('fund_user_id').value = userId;
            document.getElementById('fund_user_name').textContent = userName;
            document.getElementById('fund_current_balance').textContent = '$' + parseFloat(currentBalance).toFixed(2);
            document.getElementById('fund_amount').value = '';
            document.getElementById('fund_description').value = '';
            document.getElementById('fundModal').style.display = 'flex';
        }
        
        function closeFundModal() {
            document.getElementById('fundModal').style.display = 'none';
        }
        
        document.getElementById('fundForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'fund_account');
            
            fetch('admin_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error funding account');
                }
            });
        });
        
        window.onclick = function(event) {
            const fundModal = document.getElementById('fundModal');
            if (event.target == fundModal) {
                closeFundModal();
            }
        }
    </script>
</body>
</html>
