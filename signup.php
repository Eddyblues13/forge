<?php
require_once 'config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('user/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $state = sanitize($_POST['state'] ?? '');
    $zip_code = sanitize($_POST['zip_code'] ?? '');
    $country = sanitize($_POST['country'] ?? 'USA');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    
    // Validate required fields
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            // Handle ID file upload
            $id_file_path = '';
            if (isset($_FILES['id_file']) && $_FILES['id_file']['error'] === UPLOAD_ERR_OK) {
                $id_type = sanitize($_POST['id_type'] ?? 'drivers_license');
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                $file_type = $_FILES['id_file']['type'];
                
                if (!in_array($file_type, $allowed_types)) {
                    $error = 'Invalid file type. Please upload JPG, PNG, or PDF files only.';
                } else {
                    $file_ext = pathinfo($_FILES['id_file']['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid('id_') . '_' . time() . '.' . $file_ext;
                    $upload_path = ID_UPLOAD_DIR . $file_name;
                    
                    if (move_uploaded_file($_FILES['id_file']['tmp_name'], $upload_path)) {
                        $id_file_path = 'uploads/ids/' . $file_name;
                    } else {
                        $error = 'Failed to upload ID file. Please try again.';
                    }
                }
            } else {
                $error = 'Please upload a valid ID document';
            }
            
            if (empty($error)) {
                // Create user account
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, first_name, last_name, phone, address, city, state, zip_code, country, date_of_birth)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $username, $email, $password_hash, $first_name, $last_name,
                        $phone, $address, $city, $state, $zip_code, $country, $date_of_birth ?: null
                    ]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Save ID verification
                    if ($id_file_path) {
                        $stmt = $pdo->prepare("
                            INSERT INTO id_verifications (user_id, id_type, id_file_path, verification_status)
                            VALUES (?, ?, ?, 'pending')
                        ");
                        $stmt->execute([$user_id, $id_type, $id_file_path]);
                    }
                    
                    $pdo->commit();
                    $success = 'Account created successfully! Your ID is under verification. You can login once verified.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Forge Fund</title>
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
            padding: 40px 20px;
        }
        
        .signup-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        label .required {
            color: #e74c3c;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
            background: #f9f9f9;
            font-family: inherit;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #47763b;
            background: white;
            box-shadow: 0 0 0 3px rgba(71, 118, 59, 0.1);
        }
        
        input[type="file"] {
            padding: 10px;
            cursor: pointer;
        }
        
        .file-info {
            margin-top: 8px;
            font-size: 0.85em;
            color: #666;
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
        
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3c3;
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
            color: #47763b;
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
            color: #47763b;
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
            background: #22c55e;
            color: white;
        }
        
        .navbar-btn-login:hover {
            background: #16a34a;
            transform: translateY(-2px);
        }
        
        .navbar-btn-signup {
            background: white;
            color: #47763b;
            border: 2px solid #22c55e;
        }
        
        .navbar-btn-signup:hover {
            background: #22c55e;
            color: white;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .signup-container {
                padding: 30px 20px;
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
    <div class="signup-container" style="margin-top: 80px;">
        <div class="logo">
            <h1>Create Account</h1>
            <p>Join Forge Community Loan Fund</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <div class="links">
                <p><a href="login.php">Go to Login</a></p>
            </div>
        <?php else: ?>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city">
                    </div>
                    
                    <div class="form-group">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="zip_code">Zip Code</label>
                        <input type="text" id="zip_code" name="zip_code">
                    </div>
                    
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" value="USA">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="id_type">ID Type <span class="required">*</span></label>
                    <select id="id_type" name="id_type" required>
                        <option value="drivers_license">Driver's License</option>
                        <option value="passport">Passport</option>
                        <option value="state_id">State ID</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="id_file">Upload ID Document <span class="required">*</span></label>
                    <input type="file" id="id_file" name="id_file" accept="image/jpeg,image/jpg,image/png,application/pdf" required>
                    <div class="file-info">Accepted formats: JPG, PNG, PDF (Max 5MB)</div>
                </div>
                
                <button type="submit" class="btn">Create Account</button>
            </form>
            
            <div class="links">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
