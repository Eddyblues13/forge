<?php
require_once 'config.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// Get admin data
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    redirect('login.php');
}

// Get statistics
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$pending_verifications = $pdo->query("SELECT COUNT(*) FROM id_verifications WHERE verification_status = 'pending'")->fetchColumn();
$pending_withdrawals = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();
$total_balance = $pdo->query("SELECT SUM(account_balance) FROM users")->fetchColumn() ?? 0;

// Get recent users
$recent_users = $pdo->query("
    SELECT u.*, 
           (SELECT verification_status FROM id_verifications WHERE user_id = u.id ORDER BY submitted_at DESC LIMIT 1) as verification_status
    FROM users u 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// Get pending ID verifications
$pending_ids = $pdo->query("
    SELECT iv.*, u.first_name, u.last_name, u.email
    FROM id_verifications iv
    JOIN users u ON iv.user_id = u.id
    WHERE iv.verification_status = 'pending'
    ORDER BY iv.submitted_at ASC
")->fetchAll();

// Get pending withdrawal requests
$pending_withdrawal_requests = $pdo->query("
    SELECT wr.*, u.first_name, u.last_name, u.email, u.account_balance
    FROM withdrawal_requests wr
    JOIN users u ON wr.user_id = u.id
    WHERE wr.status = 'pending'
    ORDER BY wr.created_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Forge Fund</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            font-size: 2.2em;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
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
            max-width: 600px;
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
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #22c55e;
        }
        
        .id-preview {
            max-width: 100%;
            margin-top: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.85em;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Admin Dashboard</h1>
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
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="amount"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Users</h3>
                <div class="amount"><?php echo $active_users; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Verifications</h3>
                <div class="amount"><?php echo $pending_verifications; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Withdrawals</h3>
                <div class="amount"><?php echo $pending_withdrawals; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Balance</h3>
                <div class="amount"><?php echo formatCurrency($total_balance); ?></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Pending ID Verifications</h2>
            </div>
            <?php if (empty($pending_ids)): ?>
                <p>No pending ID verifications.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>ID Type</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_ids as $id_ver): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($id_ver['first_name'] . ' ' . $id_ver['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($id_ver['email']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $id_ver['id_type'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($id_ver['submitted_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="viewID(<?php echo $id_ver['id']; ?>, '<?php echo htmlspecialchars($id_ver['id_file_path'], ENT_QUOTES); ?>')">View</button>
                                    <button class="btn btn-sm btn-success" onclick="verifyID(<?php echo $id_ver['id']; ?>, 'approved')">Approve</button>
                                    <button class="btn btn-sm btn-danger" onclick="verifyID(<?php echo $id_ver['id']; ?>, 'rejected')">Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Pending Withdrawal Requests</h2>
            </div>
            <?php if (empty($pending_withdrawal_requests)): ?>
                <p>No pending withdrawal requests.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_withdrawal_requests as $withdrawal): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($withdrawal['first_name'] . ' ' . $withdrawal['last_name']); ?></td>
                                <td><?php echo formatCurrency($withdrawal['amount']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $withdrawal['withdrawal_method'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($withdrawal['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="viewWithdrawal(<?php echo $withdrawal['id']; ?>)">View</button>
                                    <button class="btn btn-sm btn-success" onclick="processWithdrawal(<?php echo $withdrawal['id']; ?>, 'approved')">Approve</button>
                                    <button class="btn btn-sm btn-danger" onclick="processWithdrawal(<?php echo $withdrawal['id']; ?>, 'rejected')">Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Recent Users</h2>
            </div>
            <?php if (empty($recent_users)): ?>
                <p>No users yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Balance</th>
                            <th>Verification</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo formatCurrency($user['account_balance']); ?></td>
                                <td>
                                    <?php if ($user['verification_status']): ?>
                                        <span class="status <?php echo $user['verification_status']; ?>"><?php echo ucfirst($user['verification_status']); ?></span>
                                    <?php else: ?>
                                        <span class="status pending">Not Submitted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status <?php echo $user['is_active'] ? 'approved' : 'rejected'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="fundAccount(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>', <?php echo $user['account_balance']; ?>)">Fund</button>
                                    <a href="admin_user_details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ID View Modal -->
    <div id="idModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">ID Verification</h2>
            <div id="idPreview"></div>
            <form id="verifyForm" style="margin-top: 20px;">
                <input type="hidden" id="verify_id" name="id">
                <input type="hidden" id="verify_status" name="status">
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea id="verify_notes" name="notes" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Submit</button>
                    <button type="button" class="btn btn-danger" onclick="closeIDModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Fund Account Modal -->
    <div id="fundModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Fund User Account</h2>
            <form id="fundForm">
                <input type="hidden" id="fund_user_id" name="user_id">
                <div class="form-group">
                    <label>User: <span id="fund_user_name"></span></label>
                </div>
                <div class="form-group">
                    <label>Current Balance: <span id="fund_current_balance"></span></label>
                </div>
                <div class="form-group">
                    <label>Amount to Add</label>
                    <input type="number" id="fund_amount" name="amount" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="fund_description" name="description" rows="3" required></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Fund Account</button>
                    <button type="button" class="btn btn-danger" onclick="closeFundModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function viewID(id, filePath) {
            document.getElementById('verify_id').value = id;
            document.getElementById('idPreview').innerHTML = '<img src="' + filePath + '" alt="ID Document" class="id-preview">';
            document.getElementById('idModal').style.display = 'flex';
        }
        
        function closeIDModal() {
            document.getElementById('idModal').style.display = 'none';
        }
        
        function verifyID(id, status) {
            document.getElementById('verify_id').value = id;
            document.getElementById('verify_status').value = status;
            document.getElementById('idPreview').innerHTML = '';
            document.getElementById('idModal').style.display = 'flex';
        }
        
        document.getElementById('verifyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'verify_id');
            
            fetch('admin_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error processing verification');
                }
            });
        });
        
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
                    location.reload();
                } else {
                    alert(data.message || 'Error processing withdrawal');
                }
            });
        }
        
        function viewWithdrawal(id) {
            window.location.href = 'admin_withdrawal_details.php?id=' + id;
        }
        
        window.onclick = function(event) {
            const idModal = document.getElementById('idModal');
            const fundModal = document.getElementById('fundModal');
            if (event.target == idModal) {
                closeIDModal();
            }
            if (event.target == fundModal) {
                closeFundModal();
            }
        }
    </script>
</body>
</html>
