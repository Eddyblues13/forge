# Project Structure

The project has been reorganized into a clean folder structure:

## Folder Structure

```
forge/
├── admin/                    # Admin-only files
│   ├── dashboard.php         # Admin dashboard
│   ├── users.php             # User management
│   ├── user_details.php      # Individual user details
│   ├── withdrawal_details.php # Withdrawal request details
│   └── actions.php           # Admin API endpoints
│
├── user/                     # User-only files
│   ├── dashboard.php         # User dashboard
│   ├── settings.php          # User settings
│   └── process_withdrawal.php # Withdrawal processing
│
├── config/                   # Configuration files
│   └── config.php            # Database connection & helpers
│
├── uploads/                  # Upload directory
│   └── ids/                  # ID verification documents
│
├── login.php                 # Login page (shared)
├── signup.php                # Signup page (shared)
├── logout.php                # Logout handler (shared)
├── index.php                 # Entry point
├── index.html                # Homepage
├── database.sql              # Database schema
├── config.php                # Backward compatibility (includes config/config.php)
└── README.md                 # Documentation
```

## URL Structure

### Admin URLs
- Admin Dashboard: `/admin/dashboard.php`
- User Management: `/admin/users.php`
- User Details: `/admin/user_details.php?id=X`
- Withdrawal Details: `/admin/withdrawal_details.php?id=X`
- Admin Actions API: `/admin/actions.php`

### User URLs
- User Dashboard: `/user/dashboard.php`
- User Settings: `/user/settings.php`
- Process Withdrawal: `/user/process_withdrawal.php`

### Shared URLs
- Login: `/login.php`
- Signup: `/signup.php`
- Logout: `/logout.php`
- Homepage: `/index.html` or `/index.php`

## Path Updates

All files now use relative paths:
- Admin files use: `../config/config.php`
- User files use: `../config/config.php`
- Shared files use: `config/config.php`

## Migration Notes

The old files in the root directory have been moved:
- `admin_dashboard.php` → `admin/dashboard.php`
- `admin_users.php` → `admin/users.php`
- `admin_user_details.php` → `admin/user_details.php`
- `admin_withdrawal_details.php` → `admin/withdrawal_details.php`
- `admin_actions.php` → `admin/actions.php`
- `dashboard.php` → `user/dashboard.php`
- `settings.php` → `user/settings.php`
- `process_withdrawal.php` → `user/process_withdrawal.php`
- `config.php` → `config/config.php` (with backward compatibility wrapper)

All redirects and links have been updated to reflect the new structure.
