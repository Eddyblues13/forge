-- Forge Database Schema
-- PHP 8.3 Compatible
-- Run this SQL to create all necessary tables

CREATE DATABASE IF NOT EXISTS forge_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE forge_db;

-- Admin table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'USA',
    date_of_birth DATE,
    account_balance DECIMAL(15, 2) DEFAULT 0.00,
    is_verified TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_is_verified (is_verified),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ID Verification table
CREATE TABLE IF NOT EXISTS id_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    id_type ENUM('drivers_license', 'passport', 'state_id', 'other') NOT NULL,
    id_number VARCHAR(100),
    id_file_path VARCHAR(500) NOT NULL,
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verified_by INT NULL,
    verification_notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_verification_status (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'transfer', 'fee', 'refund') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    balance_before DECIMAL(15, 2) NOT NULL,
    balance_after DECIMAL(15, 2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    processed_by INT NULL,
    reference_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment proofs table
CREATE TABLE IF NOT EXISTS payment_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    proof_file_path VARCHAR(500) NOT NULL,
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verified_by INT NULL,
    verification_notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_verification_status (verification_status),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Withdrawal requests table
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    bank_name VARCHAR(255),
    account_number VARCHAR(100),
    routing_number VARCHAR(50),
    account_holder_name VARCHAR(255),
    withdrawal_method ENUM('bank_transfer', 'check', 'wire', 'other') DEFAULT 'bank_transfer',
    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    processed_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin account (password: admin123)
-- Change this password immediately after first login!
INSERT INTO admins (username, email, password_hash, full_name) 
VALUES ('admin', 'admin@forgefund.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator')
ON DUPLICATE KEY UPDATE username=username;

-- Note: Default admin password is 'admin123' - CHANGE IT IMMEDIATELY!
