-- Default ERP login users (custom role passwords may vary)
USE `tyre_erp`;

INSERT INTO users(full_name, email, username, password_hash, role, status, must_change_password) VALUES
    ('Super Admin', 'superadmin@ralson.local', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'active', 0),
    ('HR Manager', 'hr@ralson.local', 'hrmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Manager', 'active', 0),
    ('Production Manager', 'production@ralson.local', 'prodmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Production Manager', 'active', 0),
    ('Inventory Manager', 'inventory@ralson.local', 'invmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Manager', 'active', 0),
    ('Dispatch Manager', 'dispatch@ralson.local', 'dispatchmgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dispatch Manager', 'active', 0),
    ('Quality Manager', 'quality@ralson.local', 'qualitymgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quality Manager', 'active', 0),
    ('Accounts Manager', 'accounts@ralson.local', 'accounts_manager', '$2y$10$RfhImGZW.tr40l3aqw4P9ujsfJoyXlWFuEuwJ.qCVsxrcCY/yUUDS', 'Accounts Manager', 'active', 0),
    ('Employee User', 'employee@ralson.local', 'employeeuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 'active', 0)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    username = VALUES(username),
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    status = 'active';
