<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file was uploaded
    if (!isset($_FILES['proof_file'])) {
        $_SESSION['error'] = 'No file selected. Please select a file to upload.';
        redirect('dashboard.php');
    }
    
    $file = $_FILES['proof_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit.',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
        ];
        
        $error_msg = $error_messages[$file['error']] ?? 'Unknown upload error occurred.';
        $_SESSION['error'] = 'Upload failed: ' . $error_msg;
        redirect('dashboard.php');
    }
    
    // Check if file was actually uploaded
    if (!is_uploaded_file($file['tmp_name'])) {
        $_SESSION['error'] = 'Invalid file upload. Please try again.';
        redirect('dashboard.php');
    }
    
    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = 'File size too large. Maximum size is 5MB.';
        redirect('dashboard.php');
    }
    
    if ($file['size'] == 0) {
        $_SESSION['error'] = 'File is empty. Please select a valid file.';
        redirect('dashboard.php');
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['error'] = 'Invalid file type. Only JPG, PNG, GIF, and PDF files are allowed.';
        redirect('dashboard.php');
    }
    
    // Get MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $file_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error'] = 'Invalid file type. Only JPG, PNG, GIF, and PDF files are allowed.';
        redirect('dashboard.php');
    }
    
    // Ensure upload directory exists
    if (!file_exists(PAYMENT_PROOF_UPLOAD_DIR)) {
        if (!mkdir(PAYMENT_PROOF_UPLOAD_DIR, 0755, true)) {
            $_SESSION['error'] = 'Upload directory does not exist and could not be created.';
            redirect('dashboard.php');
        }
    }
    
    // Check if directory is writable
    if (!is_writable(PAYMENT_PROOF_UPLOAD_DIR)) {
        $_SESSION['error'] = 'Upload directory is not writable. Please contact administrator.';
        redirect('dashboard.php');
    }
    
    // Generate unique filename
    $unique_filename = uniqid('proof_', true) . '_' . time() . '.' . $file_ext;
    $destination = PAYMENT_PROOF_UPLOAD_DIR . $unique_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $_SESSION['error'] = 'Failed to save uploaded file. Please try again.';
        redirect('dashboard.php');
    }
    
    // Store relative path for database
    $relative_path = 'uploads/payment_proofs/' . $unique_filename;
    
    try {
        $pdo->beginTransaction();
        
        // Insert payment proof record
        $stmt = $pdo->prepare("
            INSERT INTO payment_proofs (user_id, proof_file_path, verification_status)
            VALUES (?, ?, 'pending')
        ");
        $stmt->execute([$user_id, $relative_path]);
        
        $pdo->commit();
        $_SESSION['success'] = 'Payment proof uploaded successfully. Please wait for admin approval.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Delete uploaded file if database insert fails
        if (file_exists($destination)) {
            unlink($destination);
        }
        $_SESSION['error'] = 'Failed to save payment proof to database. Please try again.';
        error_log('Payment proof upload error: ' . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        // Delete uploaded file if database insert fails
        if (file_exists($destination)) {
            unlink($destination);
        }
        $_SESSION['error'] = 'An error occurred. Please try again.';
        error_log('Payment proof upload error: ' . $e->getMessage());
    }
} else {
    // Only show error if it's not a GET request (GET requests are normal for page loads)
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $_SESSION['error'] = 'Invalid request method. Please use the upload form.';
    }
}

redirect('dashboard.php');

