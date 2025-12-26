<?php
require_once 'config.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$withdrawal_id = intval($_GET['id'] ?? 0);

if (!$withdrawal_id) {
    redirect('admin_dashboard.php');
}

// Get withdrawal request
$stmt = $pdo->prepare("
    SELECT wr.*, u.first_name, u.last_name, u.email, u.account_balance
    FROM withdrawal_requests wr
    JOIN users u ON wr.user_id = u.id
    WHERE wr.id = ?
");
$stmt->execute([$withdrawal_id]);
$withdrawal = $stmt->fetch();

if (!$withdrawal) {
    redirect('admin_dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Details - Admin Dashboard</title>
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
            max-width: 800px;
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
        
        .info-item {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
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
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
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
        
        .status.approved, .status.completed, .status.processed {
            background: #d4edda;
            color: #155724;
        }
        
        .status.rejected {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Withdrawal Details</h1>
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
                <h2>Withdrawal Request Information</h2>
                <?php if ($withdrawal['status'] === 'pending'): ?>
                    <div style="margin-top: 15px;">
                        <button class="btn btn-success" onclick="processWithdrawal(<?php echo $withdrawal['id']; ?>, 'approved')">Approve</button>
                        <button class="btn btn-danger" onclick="processWithdrawal(<?php echo $withdrawal['id']; ?>, 'rejected')">Reject</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="info-item">
                <label>Status</label>
                <div class="value">
                    <span class="status <?php echo $withdrawal['status']; ?>"><?php echo ucfirst($withdrawal['status']); ?></span>
                </div>
            </div>
            
            <div class="info-item">
                <label>User</label>
                <div class="value"><?php echo htmlspecialchars($withdrawal['first_name'] . ' ' . $withdrawal['last_name']); ?></div>
            </div>
            
            <div class="info-item">
                <label>Email</label>
                <div class="value"><?php echo htmlspecialchars($withdrawal['email']); ?></div>
            </div>
            
            <div class="info-item">
                <label>Amount</label>
                <div class="value"><?php echo formatCurrency($withdrawal['amount']); ?></div>
            </div>
            
            <div class="info-item">
                <label>User Current Balance</label>
                <div class="value"><?php echo formatCurrency($withdrawal['account_balance']); ?></div>
            </div>
            
            <div class="info-item">
                <label>Withdrawal Method</label>
                <div class="value"><?php echo ucfirst(str_replace('_', ' ', $withdrawal['withdrawal_method'])); ?></div>
            </div>
            
            <div class="info-item">
                <label>Bank Name</label>
                <div class="value"><?php echo htmlspecialchars($withdrawal['bank_name'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="info-item">
                <label>Account Holder Name</label>
                <div class="value"><?php echo htmlspecialchars($withdrawal['account_holder_name'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="info-item">
                <label>Account Number</label>
                <div class="value"><?php echo htmlspecialchars($withdrawal['account_number'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="info-item">
                <label>Routing Number</label>
                <div class="value"><?php echo htmlspecialchars($withdrawal['routing_number'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="info-item">
                <label>Requested Date</label>
                <div class="value"><?php echo date('M d, Y H:i', strtotime($withdrawal['created_at'])); ?></div>
            </div>
            
            <?php if ($withdrawal['notes']): ?>
                <div class="info-item">
                    <label>Notes</label>
                    <div class="value"><?php echo htmlspecialchars($withdrawal['notes']); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function processWithdrawal(id, status) {
            if (!confirm('Are you sure you want to ' + status + ' this withdrawal request?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'process_withdrawal');
            formData.append('id', id);
            formData.append('status', status);
            
            fetch('admin_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Withdrawal ' + status + ' successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Error processing withdrawal');
                }
            });
        }
    </script>
</body>
</html>
