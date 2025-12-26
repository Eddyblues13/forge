<?php
require_once '../config/config.php';

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
        
        .admin-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.75em;
            font-weight: 600;
            margin-left: 10px;
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
            font-size: 2.5em;
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
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.95em;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.85em;
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
            max-width: 600px;
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
        .form-group textarea:focus {
            outline: none;
            border-color: #47763b;
            box-shadow: 0 0 0 3px rgba(71, 118, 59, 0.1);
        }
        
        .id-preview {
            max-width: 100%;
            margin-top: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }
        
        #idPreview {
            text-align: center;
            margin: 20px 0;
            min-height: 300px;
        }
        
        #idPreview iframe {
            width: 100%;
            min-height: 500px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }
        
        #idPreview img {
            max-width: 100%;
            height: auto;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }
        
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stat-card {
                padding: 25px;
            }
            
            .stat-card .amount {
                font-size: 2em;
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
            
            .admin-badge {
                font-size: 0.7em;
                padding: 3px 10px;
            }
            
            .stat-card .amount {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Admin Dashboard<span class="admin-badge">ADMIN</span></h1>
            </div>
            <div class="menu-toggle" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="nav-links" id="navLinks">
                <a href="dashboard.php">Dashboard</a>
                <a href="users.php">Users</a>
                <a href="../logout.php">Logout</a>
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
                <p style="text-align: center; color: #666; padding: 40px;">No pending ID verifications.</p>
            <?php else: ?>
                <div class="table-wrapper">
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
                                    <button class="btn btn-sm btn-primary" onclick="viewID(<?php echo $id_ver['id']; ?>, '<?php echo htmlspecialchars('../view_file.php?file=' . urlencode($id_ver['id_file_path']), ENT_QUOTES); ?>')">View</button>
                                    <button class="btn btn-sm btn-success" onclick="verifyID(<?php echo $id_ver['id']; ?>, 'approved', '<?php echo htmlspecialchars('../view_file.php?file=' . urlencode($id_ver['id_file_path']), ENT_QUOTES); ?>')">Approve</button>
                                    <button class="btn btn-sm btn-danger" onclick="verifyID(<?php echo $id_ver['id']; ?>, 'rejected', '<?php echo htmlspecialchars('../view_file.php?file=' . urlencode($id_ver['id_file_path']), ENT_QUOTES); ?>')">Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Pending Withdrawal Requests</h2>
            </div>
            <?php if (empty($pending_withdrawal_requests)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">No pending withdrawal requests.</p>
            <?php else: ?>
                <div class="table-wrapper">
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
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Recent Users</h2>
            </div>
            <?php if (empty($recent_users)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">No users yet.</p>
            <?php else: ?>
                <div class="table-wrapper">
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
                                    <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ID View Modal -->
    <div id="idModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">ID Verification Document</h2>
            <div id="idPreview" style="min-height: 300px; display: flex; align-items: center; justify-content: center;">
                <p style="color: #666;">Click "View" button to load document</p>
            </div>
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
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            const menuToggle = document.querySelector('.menu-toggle');
            navLinks.classList.toggle('active');
            menuToggle.classList.toggle('active');
        }
        
        function viewID(id, filePath) {
            document.getElementById('verify_id').value = id;
            const fileExt = filePath.split('.').pop().toLowerCase();
            let previewHTML = '';
            
            if (fileExt === 'pdf') {
                previewHTML = '<iframe src="' + filePath + '" style="width: 100%; height: 500px; border: 2px solid #e0e0e0; border-radius: 10px;" type="application/pdf"></iframe>';
            } else {
                previewHTML = '<img src="' + filePath + '" alt="ID Document" class="id-preview" style="max-width: 100%; height: auto; border: 2px solid #e0e0e0; border-radius: 10px;">';
            }
            
            document.getElementById('idPreview').innerHTML = previewHTML;
            document.getElementById('idModal').style.display = 'flex';
        }
        
        function closeIDModal() {
            document.getElementById('idModal').style.display = 'none';
        }
        
        function verifyID(id, status, filePath) {
            document.getElementById('verify_id').value = id;
            document.getElementById('verify_status').value = status;
            
            // Show the document preview
            if (filePath) {
                const fileExt = filePath.split('.').pop().toLowerCase();
                let previewHTML = '';
                
                if (fileExt === 'pdf') {
                    previewHTML = '<iframe src="' + filePath + '" style="width: 100%; height: 500px; border: 2px solid #e0e0e0; border-radius: 10px;" type="application/pdf"></iframe>';
                } else {
                    previewHTML = '<img src="' + filePath + '" alt="ID Document" style="max-width: 100%; height: auto; border: 2px solid #e0e0e0; border-radius: 10px;">';
                }
                
                document.getElementById('idPreview').innerHTML = previewHTML;
            } else {
                document.getElementById('idPreview').innerHTML = '<p style="color: #666; padding: 40px;">Document preview unavailable.</p>';
            }
            
            document.getElementById('idModal').style.display = 'flex';
        }
        
        document.getElementById('verifyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'verify_id');
            
            fetch('actions.php', {
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
            
            fetch('actions.php', {
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
            
            fetch('actions.php', {
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
            window.location.href = 'withdrawal_details.php?id=' + id;
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
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navLinks = document.getElementById('navLinks');
            const menuToggle = document.querySelector('.menu-toggle');
            const headerContent = document.querySelector('.header-content');
            
            if (!headerContent.contains(event.target) && navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });
    </script>
</body>
</html>
