# Forge Fund - PHP 8.3 Application

A complete banking/financial management system with user registration, ID verification, account management, and admin dashboard.

## Features

### User Features
- **Registration**: Sign up with personal details and ID verification upload
- **Login**: Secure authentication system
- **Dashboard**: View account balance, transactions, and withdrawal requests
- **Withdrawal Requests**: Submit withdrawal requests with bank details
- **Settings**: Update profile information and change password
- **ID Verification**: Upload ID documents (Driver's License, Passport, State ID)

### Admin Features
- **Dashboard**: Overview of all users, pending verifications, and withdrawals
- **User Management**: View and manage all users
- **ID Verification**: Approve or reject user ID verifications
- **Fund Accounts**: Add funds to user accounts
- **Withdrawal Processing**: Approve or reject withdrawal requests
- **Transaction History**: View all user transactions

## Installation

### 1. Database Setup

1. Open phpMyAdmin or your MySQL client
2. Import the `database.sql` file to create all necessary tables
3. The default admin account will be created:
   - **Username**: admin
   - **Email**: admin@forgefund.org
   - **Password**: admin123
   - **⚠️ IMPORTANT**: Change this password immediately after first login!

### 2. Configuration

Edit `config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'forge_db');
```

Also update the base URL if needed:

```php
define('BASE_URL', 'http://localhost/forge/');
```

### 3. File Permissions

Ensure the upload directories are writable:

```bash
chmod 755 uploads/
chmod 755 uploads/ids/
```

Or on Windows, make sure the folders have write permissions.

### 4. Web Server Setup

- Place the files in your web server directory (e.g., `htdocs/forge/`)
- Ensure PHP 8.3 or higher is installed
- Make sure mod_rewrite is enabled (if using Apache)

## File Structure

```
forge/
├── config.php                 # Database configuration and helpers
├── database.sql               # SQL schema file
├── index.php                  # Entry point (redirects to login)
├── login.php                  # User/Admin login page
├── signup.php                 # User registration page
├── dashboard.php              # User dashboard
├── settings.php               # User settings page
├── process_withdrawal.php     # Process withdrawal requests
├── logout.php                 # Logout handler
├── admin_dashboard.php        # Admin dashboard
├── admin_users.php            # User management page
├── admin_user_details.php     # Individual user details
├── admin_actions.php          # Admin API endpoints
├── uploads/                   # Upload directory
│   └── ids/                   # ID verification documents
└── README.md                  # This file
```

## Database Schema

The system uses the following tables:

- **admins**: Admin user accounts
- **users**: Regular user accounts
- **id_verifications**: ID document uploads and verification status
- **transactions**: All financial transactions
- **withdrawal_requests**: User withdrawal requests

## Security Features

- Password hashing using PHP's `password_hash()` function
- Prepared statements to prevent SQL injection
- Input sanitization
- Session management
- File upload validation
- Role-based access control (Admin vs User)

## Usage

### For Users

1. Visit the signup page and create an account
2. Upload a valid ID document (JPG, PNG, or PDF)
3. Wait for admin verification
4. Once verified, you can access your dashboard
5. Request withdrawals from your dashboard
6. View transaction history

### For Admins

1. Login with admin credentials
2. View pending ID verifications and approve/reject them
3. View pending withdrawal requests and process them
4. Fund user accounts as needed
5. Manage all users from the Users page

## Default Admin Account

**⚠️ SECURITY WARNING**: Change the default admin password immediately!

- Username: `admin`
- Email: `admin@forgefund.org`
- Password: `admin123`

## Requirements

- PHP 8.3 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx)
- PDO MySQL extension enabled

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Notes

- All file uploads are stored in the `uploads/ids/` directory
- Maximum file upload size depends on PHP configuration (php.ini)
- The system uses responsive design and works on mobile devices
- All monetary values are stored as DECIMAL(15,2) for precision

## Troubleshooting

### Database Connection Error
- Check database credentials in `config.php`
- Ensure MySQL service is running
- Verify database name exists

### File Upload Issues
- Check folder permissions (755 or 777)
- Verify PHP upload_max_filesize and post_max_size settings
- Check that uploads/ids/ directory exists

### Session Issues
- Ensure sessions are enabled in PHP
- Check session.save_path is writable

## Support

For issues or questions, please check:
1. PHP error logs
2. Web server error logs
3. Database connection settings
4. File permissions

## License

This is a custom application for Forge Fund.
