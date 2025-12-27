<?php
/**
 * Database Configuration
 * PHP 8.3 Compatible
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'forge_db');

// Application configuration
define('BASE_URL', 'http://localhost/forge/');
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('ID_UPLOAD_DIR', dirname(__DIR__) . '/uploads/ids/');
define('PAYMENT_PROOF_UPLOAD_DIR', dirname(__DIR__) . '/uploads/payment_proofs/');

// Bitcoin wallet address
define('BITCOIN_WALLET', 'bc1q4ry2xj5l9stya2mdcqdk368u00h23s6fr550xv');

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(ID_UPLOAD_DIR)) {
    mkdir(ID_UPLOAD_DIR, 0755, true);
}
if (!file_exists(PAYMENT_PROOF_UPLOAD_DIR)) {
    mkdir(PAYMENT_PROOF_UPLOAD_DIR, 0755, true);
}

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// Helper function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Helper function to sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Helper function to format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}
