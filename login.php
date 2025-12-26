<?php
require_once 'config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'user/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'user';
            $_SESSION['username'] = $user['username'] ?? $user['email'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Update last login
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            
            redirect('user/dashboard.php');
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Forge Fund</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #47763b 0%, #3a5f2f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            animation: slideUp 0.5s ease-out;
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
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #47763b;
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 0.9em;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
            background: #f9f9f9;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #47763b;
            background: white;
            box-shadow: 0 0 0 3px rgba(71, 118, 59, 0.1);
        }
        
        .user-type-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: #f0f0f0;
            padding: 5px;
            border-radius: 10px;
        }
        
        .user-type-toggle label {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0;
        }
        
        .user-type-toggle input[type="radio"] {
            display: none;
        }
        
        .user-type-toggle input[type="radio"]:checked + label {
            background: #47763b;
            color: white;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #47763b 0%, #3a5f2f 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(71, 118, 59, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        
        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        .links a {
            color: #47763b;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .links a:hover {
            color: #3a5f2f;
        }
        
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-logo {
            font-size: 1.5em;
            font-weight: 700;
            color: #16a34a;
            text-decoration: none;
        }
        
        .navbar-buttons {
            display: flex;
            gap: 10px;
        }
        
        .navbar-btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .navbar-btn-login {
            background: #47763b;
            color: white;
        }
        
        .navbar-btn-login:hover {
            background: #3a5f2f;
            transform: translateY(-2px);
        }
        
        .navbar-btn-signup {
            background: white;
            color: #47763b;
            border: 2px solid #47763b;
        }
        
        .navbar-btn-signup:hover {
            background: #47763b;
            color: white;
        }
        
        .navbar-logo {
            color: #47763b;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.html" class="navbar-logo">Forge Fund</a>
            <div class="navbar-buttons">
                <a href="login.php" class="navbar-btn navbar-btn-login">Login</a>
                <a href="signup.php" class="navbar-btn navbar-btn-signup">Sign Up</a>
            </div>
        </div>
    </nav>
    <div class="login-container" style="margin-top: 80px;">
        <div class="logo">
            <h1>Forge</h1>
            <p>Community Loan Fund</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="links">
            <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
            <p style="margin-top: 10px;">Are you an admin? <a href="admin/login.php">Admin Login</a></p>
        </div>
    </div>
</body>
</html>
