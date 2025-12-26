<?php
require_once 'config.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Get search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$status_filter = sanitize($_GET['status'] ?? 'all');
$verification_filter = sanitize($_GET['verification'] ?? 'all');

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter !== 'all') {
    $where[] = "u.is_active = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get users
$sql = "
    SELECT u.*, 
           (SELECT verification_status FROM id_verifications WHERE user_id = u.id ORDER BY submitted_at DESC LIMIT 1) as verification_status
    FROM users u 
    $where_clause
    ORDER BY u.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin Dashboard</title>
    <link rel="stylesheet" href="admin_dashboard.php">
    <style>
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filters input,
        .filters select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .filters input {
            flex: 1;
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>User Management</h1>
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
                <h2>All Users</h2>
            </div>
            
            <div class="filters">
                <form method="GET" style="display: flex; gap: 15px; width: 100%; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search by name, email, username..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="admin_users.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            
            <?php if (empty($users)): ?>
                <p>No users found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Balance</th>
                            <th>Verification</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
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
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
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
    
    <!-- Fund Account Modal (same as dashboard) -->
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
