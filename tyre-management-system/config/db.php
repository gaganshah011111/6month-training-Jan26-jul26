<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

class Database
{
    private static ?PDO $instance = null;
    private static bool $initialized = false;

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = '127.0.0.1';
        $dbName = 'tyre_erp';
        $username = 'root';
        $password = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";

        self::$instance = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::initializeSchema(self::$instance);

        return self::$instance;
    }

    private static function initializeSchema(PDO $pdo): void
    {
        if (self::$initialized) {
            return;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NULL UNIQUE,
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(150) NULL UNIQUE,
                username VARCHAR(80) NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'Employee',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                must_change_password TINYINT(1) NOT NULL DEFAULT 0,
                last_login DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS employees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL UNIQUE,
                employee_code VARCHAR(50) NOT NULL UNIQUE,
                full_name VARCHAR(150) NOT NULL,
                father_name VARCHAR(120) NULL,
                dob DATE NULL,
                aadhaar_number CHAR(12) NULL,
                email VARCHAR(150) NULL,
                password_hash VARCHAR(255) NULL,
                employee_type ENUM('Staff','Worker') NOT NULL DEFAULT 'Staff',
                department VARCHAR(100) NOT NULL,
                designation VARCHAR(120) NULL,
                role VARCHAR(100) NOT NULL DEFAULT 'Employee',
                salary_type VARCHAR(50) NOT NULL DEFAULT 'Monthly',
                shift_timing VARCHAR(80) NULL,
                shift_start TIME NULL,
                shift_end TIME NULL,
                contact_no VARCHAR(20) NULL,
                address VARCHAR(255) NULL,
                profile_image VARCHAR(255) NULL,
                joining_date DATE NOT NULL,
                basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                paid_leave_limit DECIMAL(6,2) NOT NULL DEFAULT 12,
                half_paid_leave_limit DECIMAL(6,2) NOT NULL DEFAULT 6,
                hra_percentage DECIMAL(6,2) NOT NULL DEFAULT 40,
                hra_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                pf_applicable TINYINT(1) NOT NULL DEFAULT 1,
                pf_percentage DECIMAL(6,2) NOT NULL DEFAULT 12,
                esi_applicable TINYINT(1) NOT NULL DEFAULT 1,
                esi_percentage DECIMAL(6,2) NOT NULL DEFAULT 0.75,
                esi_salary_limit DECIMAL(12,2) NOT NULL DEFAULT 21000,
                medical_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
                dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
                travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
                special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
                other_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
                metro TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=metro HRA band',
                payroll_auto_indian TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=auto split from gross',
                gratuity_monthly DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'employer accrual 4.81% of basic (not in gross)',
                gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'fixed monthly gross: sum of earnings excl OT (no gratuity)',
                overtime_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
                daily_wage DECIMAL(12,2) NOT NULL DEFAULT 0,
                hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_employees_aadhaar (aadhaar_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                attendance_date DATE NOT NULL,
                shift ENUM('Morning','Evening','Night') NOT NULL DEFAULT 'Morning',
                status VARCHAR(40) NOT NULL DEFAULT 'Present',
                remarks VARCHAR(255) NULL,
                punch_in_time DATETIME NULL,
                punch_out_time DATETIME NULL,
                total_hours DECIMAL(8,2) NULL DEFAULT NULL,
                overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
                is_late TINYINT(1) NOT NULL DEFAULT 0,
                is_early_exit TINYINT(1) NOT NULL DEFAULT 0,
                is_emergency_duty TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_attendance_employee_day (employee_id, attendance_date),
                INDEX idx_attendance_date (attendance_date),
                CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS company_holidays (
                holiday_date DATE NOT NULL PRIMARY KEY,
                label VARCHAR(120) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS hr_holidays (
                id INT AUTO_INCREMENT PRIMARY KEY,
                holiday_date DATE NOT NULL,
                holiday_name VARCHAR(160) NOT NULL,
                holiday_type VARCHAR(50) NOT NULL,
                department_scope VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'empty = all departments',
                remarks VARCHAR(255) NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_hr_holiday_scope (holiday_date, department_scope),
                INDEX idx_hr_holiday_date (holiday_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS leaves (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                from_date DATE NOT NULL,
                to_date DATE NOT NULL,
                leave_type VARCHAR(50) NOT NULL DEFAULT 'Casual',
                reason VARCHAR(255) NOT NULL,
                leave_category ENUM('Paid','Half Paid','Unpaid') NOT NULL DEFAULT 'Paid',
                is_paid TINYINT(1) NOT NULL DEFAULT 1,
                status ENUM('Applied','Approved','Rejected') NOT NULL DEFAULT 'Applied',
                approved_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_leave_dates (from_date, to_date),
                CONSTRAINT fk_leaves_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS salaries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                month_year CHAR(7) NOT NULL,
                present_days DECIMAL(5,2) NOT NULL DEFAULT 0,
                paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
                half_paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
                unpaid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
                overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
                overtime_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
                basic DECIMAL(12,2) NOT NULL DEFAULT 0,
                hra_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
                hra_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                pf_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
                pf_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                esi_employee_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
                esi_employee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                esi_employer_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
                esi_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                medical_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
                dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
                travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
                special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
                other_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
                pf_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
                gratuity_accrual DECIMAL(12,2) NOT NULL DEFAULT 0,
                leave_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
                half_day_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
                late_entry_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
                gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
                net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_salary_employee_month (employee_id, month_year),
                CONSTRAINT fk_salaries_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS salary_increments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                old_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                new_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                increment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                increment_percentage DECIMAL(8,2) NOT NULL DEFAULT 0,
                effective_date DATE NOT NULL,
                reason VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_increment_employee (employee_id),
                CONSTRAINT fk_increment_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS suppliers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                contact_person VARCHAR(100) NULL,
                phone VARCHAR(20) NULL,
                email VARCHAR(120) NULL,
                address VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS raw_materials (
                id INT AUTO_INCREMENT PRIMARY KEY,
                material_name VARCHAR(150) NOT NULL,
                unit VARCHAR(20) NOT NULL,
                stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
                reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
                supplier_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_raw_stock (stock_qty),
                CONSTRAINT fk_raw_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS machines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                machine_code VARCHAR(50) NOT NULL UNIQUE,
                machine_name VARCHAR(150) NOT NULL,
                status ENUM('Active','Under Maintenance','Inactive') NOT NULL DEFAULT 'Active',
                last_maintenance_date DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS production (
                id INT AUTO_INCREMENT PRIMARY KEY,
                production_date DATE NOT NULL,
                machine_id INT NOT NULL,
                shift ENUM('Morning','Evening','Night') NOT NULL,
                raw_material_id INT NOT NULL,
                material_used_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
                output_quantity INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_production_date (production_date),
                CONSTRAINT fk_production_machine FOREIGN KEY (machine_id) REFERENCES machines(id),
                CONSTRAINT fk_production_raw FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS quality_checks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                production_id INT NOT NULL,
                inspection_date DATE NOT NULL,
                inspector_name VARCHAR(150) NOT NULL,
                passed_qty INT NOT NULL DEFAULT 0,
                failed_qty INT NOT NULL DEFAULT 0,
                quality_status ENUM('Pass','Fail') NOT NULL DEFAULT 'Pass',
                defects VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_quality_production FOREIGN KEY (production_id) REFERENCES production(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_name VARCHAR(120) NOT NULL,
                batch_ref VARCHAR(80) NOT NULL,
                qty INT NOT NULL DEFAULT 0,
                reorder_level INT NOT NULL DEFAULT 0,
                warehouse_location VARCHAR(120) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_inventory_qty (qty)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS dispatch (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inventory_id INT NOT NULL,
                order_no VARCHAR(60) NOT NULL UNIQUE,
                customer_name VARCHAR(150) NOT NULL,
                invoice_no VARCHAR(80) NOT NULL UNIQUE,
                dispatch_date DATE NOT NULL,
                qty INT NOT NULL,
                dispatch_status ENUM('Created','In Transit','Delivered','Cancelled') NOT NULL DEFAULT 'Created',
                tracking_no VARCHAR(80) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_dispatch_date (dispatch_date),
                CONSTRAINT fk_dispatch_inventory FOREIGN KEY (inventory_id) REFERENCES inventory(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS defect_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quality_check_id INT NOT NULL,
                production_id INT NOT NULL,
                failed_qty INT NOT NULL,
                defect_notes VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_defect_quality FOREIGN KEY (quality_check_id) REFERENCES quality_checks(id) ON DELETE CASCADE,
                CONSTRAINT fk_defect_production FOREIGN KEY (production_id) REFERENCES production(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value VARCHAR(255) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_name', 'Ralson India Private Limited - Tyre ERP')",
        ];

        foreach ($queries as $query) {
            $pdo->exec($query);
        }

        // Must run before seed INSERTs: legacy DBs may already have `users` without newer columns
        // (`CREATE TABLE IF NOT EXISTS` does not alter existing tables).
        self::applyCompatibilityMigrations($pdo);

        $pdo->exec("INSERT INTO users(full_name,email,username,password_hash,role,status,must_change_password) VALUES
                ('Super Admin','superadmin@ralson.local','superadmin','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Super Admin','active',0),
                ('HR Manager','hr@ralson.local','hrmanager','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','HR Manager','active',0),
                ('Production Manager','production@ralson.local','prodmanager','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Production Manager','active',0),
                ('Inventory Manager','inventory@ralson.local','invmanager','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Inventory Manager','active',0),
                ('Dispatch Manager','dispatch@ralson.local','dispatchmgr','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Dispatch Manager','active',0),
                ('Quality Manager','quality@ralson.local','qualitymgr','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Quality Manager','active',0),
                ('Employee User','employee@ralson.local','employeeuser','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee','active',0)
            ON DUPLICATE KEY UPDATE
                full_name=VALUES(full_name),
                username=VALUES(username),
                password_hash=VALUES(password_hash),
                role=VALUES(role),
                status='active'");

        self::$initialized = true;
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
              AND column_name = :column_name");
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function applyCompatibilityMigrations(PDO $pdo): void
    {
        // users compatibility for department roles
        if (!self::hasColumn($pdo, 'users', 'full_name')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(150) NULL");
        }
        if (!self::hasColumn($pdo, 'users', 'password_hash')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL");
        }
        if (!self::hasColumn($pdo, 'users', 'status')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
        }
        $pdo->exec("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'Employee'");

        if (!self::hasColumn($pdo, 'users', 'employee_id')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN employee_id INT NULL UNIQUE AFTER id');
        }
        if (!self::hasColumn($pdo, 'users', 'username')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(80) NULL UNIQUE AFTER email');
        }
        if (!self::hasColumn($pdo, 'users', 'must_change_password')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
        }
        if (!self::hasColumn($pdo, 'users', 'last_login')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER must_change_password');
        }
        try {
            $pdo->exec('ALTER TABLE users MODIFY email VARCHAR(150) NULL');
        } catch (Throwable) {
            // ignore if already nullable or unsupported variant
        }

        if (!self::hasColumn($pdo, 'employees', 'father_name')) {
            $pdo->exec('ALTER TABLE employees ADD COLUMN father_name VARCHAR(120) NULL AFTER full_name');
        }
        if (!self::hasColumn($pdo, 'employees', 'dob')) {
            $pdo->exec('ALTER TABLE employees ADD COLUMN dob DATE NULL AFTER father_name');
        }
        if (!self::hasColumn($pdo, 'employees', 'aadhaar_number')) {
            $pdo->exec('ALTER TABLE employees ADD COLUMN aadhaar_number CHAR(12) NULL AFTER dob');
        }
        try {
            $pdo->exec('ALTER TABLE employees ADD UNIQUE INDEX uk_employees_aadhaar (aadhaar_number)');
        } catch (Throwable) {
            // index may already exist
        }

        // attendance
        if (!self::hasColumn($pdo, 'attendance', 'remarks')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN remarks VARCHAR(255) NULL AFTER status");
        }
        if (!self::hasColumn($pdo, 'attendance', 'punch_in_time')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN punch_in_time DATETIME NULL AFTER remarks");
        }
        if (!self::hasColumn($pdo, 'attendance', 'punch_out_time')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN punch_out_time DATETIME NULL AFTER punch_in_time");
        }
        if (!self::hasColumn($pdo, 'attendance', 'total_hours')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN total_hours DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER punch_out_time");
        }
        if (!self::hasColumn($pdo, 'attendance', 'overtime_hours')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER total_hours");
        }
        if (!self::hasColumn($pdo, 'attendance', 'is_late')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN is_late TINYINT(1) NOT NULL DEFAULT 0 AFTER overtime_hours");
        }
        if (!self::hasColumn($pdo, 'attendance', 'is_emergency_duty')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN is_emergency_duty TINYINT(1) NOT NULL DEFAULT 0 AFTER is_late");
        }
        if (!self::hasColumn($pdo, 'attendance', 'is_early_exit')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN is_early_exit TINYINT(1) NOT NULL DEFAULT 0 AFTER is_late");
        }

        $pdo->exec("ALTER TABLE attendance MODIFY status VARCHAR(40) NOT NULL DEFAULT 'Present'");
        $pdo->exec("ALTER TABLE attendance MODIFY total_hours DECIMAL(8,2) NULL DEFAULT NULL");
        $pdo->exec("UPDATE attendance SET status = 'Paid Leave' WHERE status = 'Leave'");
        $pdo->exec("UPDATE attendance SET is_emergency_duty = 1 WHERE status = 'Emergency Duty'");

        $pdo->exec("CREATE TABLE IF NOT EXISTS company_holidays (
            holiday_date DATE NOT NULL PRIMARY KEY,
            label VARCHAR(120) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS hr_holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL,
            holiday_name VARCHAR(160) NOT NULL,
            holiday_type VARCHAR(50) NOT NULL,
            department_scope VARCHAR(120) NOT NULL DEFAULT '',
            remarks VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_hr_holiday_scope (holiday_date, department_scope),
            INDEX idx_hr_holiday_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // leaves: support legacy and new naming
        if (!self::hasColumn($pdo, 'leaves', 'leave_type')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN leave_type VARCHAR(50) NOT NULL DEFAULT 'Casual' AFTER employee_id");
        }
        if (!self::hasColumn($pdo, 'leaves', 'from_date')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN from_date DATE NULL AFTER leave_type");
        }
        if (!self::hasColumn($pdo, 'leaves', 'to_date')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN to_date DATE NULL AFTER from_date");
        }
        if (!self::hasColumn($pdo, 'leaves', 'start_date')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN start_date DATE NULL AFTER from_date");
        }
        if (!self::hasColumn($pdo, 'leaves', 'end_date')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN end_date DATE NULL AFTER to_date");
        }
        if (!self::hasColumn($pdo, 'leaves', 'is_paid')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 1 AFTER reason");
        }
        if (!self::hasColumn($pdo, 'leaves', 'status')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN status ENUM('Applied','Approved','Rejected') NOT NULL DEFAULT 'Applied' AFTER is_paid");
        }
        if (!self::hasColumn($pdo, 'leaves', 'leave_category')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN leave_category ENUM('Paid','Half Paid','Unpaid') NOT NULL DEFAULT 'Paid' AFTER reason");
        }

        // employee salary structure compatibility
        if (!self::hasColumn($pdo, 'employees', 'email')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN email VARCHAR(150) NULL AFTER full_name");
        }
        if (!self::hasColumn($pdo, 'employees', 'password_hash')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        }
        if (!self::hasColumn($pdo, 'employees', 'designation')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN designation VARCHAR(120) NULL AFTER department");
        }
        if (!self::hasColumn($pdo, 'employees', 'role')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN role VARCHAR(100) NOT NULL DEFAULT 'Employee' AFTER designation");
        }
        if (self::hasColumn($pdo, 'employees', 'role_name')) {
            $pdo->exec("UPDATE employees SET role = COALESCE(NULLIF(role, ''), role_name)");
        }
        if (!self::hasColumn($pdo, 'employees', 'employee_type')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN employee_type ENUM('Staff','Worker') NOT NULL DEFAULT 'Staff' AFTER full_name");
        }
        if (!self::hasColumn($pdo, 'employees', 'salary_type')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN salary_type VARCHAR(50) NOT NULL DEFAULT 'Monthly' AFTER role");
        }
        if (!self::hasColumn($pdo, 'employees', 'shift_timing')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN shift_timing VARCHAR(80) NULL AFTER salary_type");
        }
        if (!self::hasColumn($pdo, 'employees', 'shift_start')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN shift_start TIME NULL AFTER shift_timing");
        }
        if (!self::hasColumn($pdo, 'employees', 'shift_end')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN shift_end TIME NULL AFTER shift_start");
        }
        if (!self::hasColumn($pdo, 'employees', 'paid_leave_limit')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN paid_leave_limit DECIMAL(6,2) NOT NULL DEFAULT 12 AFTER basic_salary");
        }
        if (!self::hasColumn($pdo, 'employees', 'half_paid_leave_limit')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN half_paid_leave_limit DECIMAL(6,2) NOT NULL DEFAULT 6 AFTER paid_leave_limit");
        }
        if (!self::hasColumn($pdo, 'employees', 'hra_percentage')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN hra_percentage DECIMAL(6,2) NOT NULL DEFAULT 40 AFTER half_paid_leave_limit");
        }
        if (!self::hasColumn($pdo, 'employees', 'hra_amount')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN hra_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER hra_percentage");
        }
        if (!self::hasColumn($pdo, 'employees', 'pf_applicable')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN pf_applicable TINYINT(1) NOT NULL DEFAULT 1 AFTER hra_amount");
        }
        if (!self::hasColumn($pdo, 'employees', 'pf_percentage')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN pf_percentage DECIMAL(6,2) NOT NULL DEFAULT 12 AFTER pf_applicable");
        }
        if (!self::hasColumn($pdo, 'employees', 'esi_applicable')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN esi_applicable TINYINT(1) NOT NULL DEFAULT 1 AFTER pf_percentage");
        }
        if (!self::hasColumn($pdo, 'employees', 'esi_percentage')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN esi_percentage DECIMAL(6,2) NOT NULL DEFAULT 0.75 AFTER esi_applicable");
        }
        if (!self::hasColumn($pdo, 'employees', 'esi_salary_limit')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN esi_salary_limit DECIMAL(12,2) NOT NULL DEFAULT 21000 AFTER esi_percentage");
        }
        if (!self::hasColumn($pdo, 'employees', 'medical_allowance')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN medical_allowance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER esi_salary_limit");
        }
        if (!self::hasColumn($pdo, 'employees', 'special_allowance')) {
            try {
                $pdo->exec('ALTER TABLE employees ADD COLUMN special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER medical_allowance');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE employees ADD COLUMN special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'employees', 'other_allowances')) {
            $after = self::hasColumn($pdo, 'employees', 'special_allowance') ? 'special_allowance' : 'medical_allowance';
            $pdo->exec("ALTER TABLE employees ADD COLUMN other_allowances DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER {$after}");
        }
        if (!self::hasColumn($pdo, 'employees', 'gross_salary')) {
            try {
                $pdo->exec('ALTER TABLE employees ADD COLUMN gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER other_allowances');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE employees ADD COLUMN gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'employees', 'overtime_rate')) {
            try {
                $pdo->exec('ALTER TABLE employees ADD COLUMN overtime_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gross_salary');
            } catch (Throwable) {
                $pdo->exec("ALTER TABLE employees ADD COLUMN overtime_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER other_allowances");
            }
        }
        if (!self::hasColumn($pdo, 'employees', 'daily_wage')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN daily_wage DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER overtime_rate");
        }
        if (!self::hasColumn($pdo, 'employees', 'hourly_rate')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER daily_wage");
        }
        if (!self::hasColumn($pdo, 'employees', 'status')) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER hourly_rate");
        }
        if (!self::hasColumn($pdo, 'employees', 'user_id')) {
            $pdo->exec('ALTER TABLE employees ADD COLUMN user_id INT NULL UNIQUE AFTER id');
        }
        if (!self::hasColumn($pdo, 'employees', 'address')) {
            $pdo->exec('ALTER TABLE employees ADD COLUMN address VARCHAR(255) NULL AFTER contact_no');
        }
        if (!self::hasColumn($pdo, 'employees', 'profile_image')) {
            $pdo->exec('ALTER TABLE employees ADD COLUMN profile_image VARCHAR(255) NULL AFTER address');
        }

        // salaries/payroll compatibility columns
        if (!self::hasColumn($pdo, 'salaries', 'present_days')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN present_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER month_year");
        }
        if (!self::hasColumn($pdo, 'salaries', 'paid_leave_days')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER present_days");
        }
        if (!self::hasColumn($pdo, 'salaries', 'half_paid_leave_days')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN half_paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER paid_leave_days");
        }
        if (!self::hasColumn($pdo, 'salaries', 'unpaid_leave_days')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN unpaid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER half_paid_leave_days");
        }
        if (!self::hasColumn($pdo, 'salaries', 'overtime_hours')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER unpaid_leave_days");
        }
        if (!self::hasColumn($pdo, 'salaries', 'overtime_amount')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN overtime_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER overtime_hours");
        }
        if (!self::hasColumn($pdo, 'salaries', 'deductions')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN deductions DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER overtime_amount");
        }
        if (!self::hasColumn($pdo, 'salaries', 'basic')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN basic DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER deductions");
        }
        foreach ([
            'hra_percentage DECIMAL(6,2) NOT NULL DEFAULT 0',
            'hra_amount DECIMAL(12,2) NOT NULL DEFAULT 0',
            'pf_percentage DECIMAL(6,2) NOT NULL DEFAULT 0',
            'pf_amount DECIMAL(12,2) NOT NULL DEFAULT 0',
            'esi_employee_percentage DECIMAL(6,2) NOT NULL DEFAULT 0',
            'esi_employee_amount DECIMAL(12,2) NOT NULL DEFAULT 0',
            'esi_employer_percentage DECIMAL(6,2) NOT NULL DEFAULT 0',
            'esi_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0',
            'medical_allowance DECIMAL(12,2) NOT NULL DEFAULT 0',
            'special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0',
            'other_allowances DECIMAL(12,2) NOT NULL DEFAULT 0',
            'leave_deduction DECIMAL(12,2) NOT NULL DEFAULT 0',
            'half_day_deduction DECIMAL(12,2) NOT NULL DEFAULT 0',
            'late_entry_deduction DECIMAL(12,2) NOT NULL DEFAULT 0',
            'gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0',
            'total_deduction DECIMAL(12,2) NOT NULL DEFAULT 0'
        ] as $columnDef) {
            $columnName = explode(' ', $columnDef)[0];
            if (!self::hasColumn($pdo, 'salaries', $columnName)) {
                $pdo->exec("ALTER TABLE salaries ADD COLUMN {$columnDef}");
            }
        }

        if (!self::hasColumn($pdo, 'salaries', 'special_allowance')) {
            try {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER medical_allowance');
            } catch (Throwable) {
                try {
                    $pdo->exec('ALTER TABLE salaries ADD COLUMN special_allowance DECIMAL(12,2) NOT NULL DEFAULT 0');
                } catch (Throwable) {
                }
            }
        }

        if (!self::hasColumn($pdo, 'employees', 'metro')) {
            $after = self::hasColumn($pdo, 'employees', 'esi_salary_limit') ? 'esi_salary_limit' : 'esi_percentage';
            try {
                $pdo->exec("ALTER TABLE employees ADD COLUMN metro TINYINT(1) NOT NULL DEFAULT 0 AFTER {$after}");
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE employees ADD COLUMN metro TINYINT(1) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'employees', 'payroll_auto_indian')) {
            try {
                $pdo->exec('ALTER TABLE employees ADD COLUMN payroll_auto_indian TINYINT(1) NOT NULL DEFAULT 1 AFTER metro');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE employees ADD COLUMN payroll_auto_indian TINYINT(1) NOT NULL DEFAULT 1');
            }
        }
        if (self::hasColumn($pdo, 'employees', 'payroll_auto_indian')) {
            $leg = (int)$pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'employee_payroll_auto_legacy_v1'")->fetchColumn();
            if ($leg === 0) {
                try {
                    $empCount = (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
                    if ($empCount > 0) {
                        $pdo->exec('UPDATE employees SET payroll_auto_indian = 0');
                    }
                    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('employee_payroll_auto_legacy_v1', '1')");
                } catch (Throwable) {
                }
            }
        }
        if (!self::hasColumn($pdo, 'employees', 'dearness_allowance')) {
            try {
                $pdo->exec('ALTER TABLE employees ADD COLUMN dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER medical_allowance');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE employees ADD COLUMN dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'employees', 'travel_allowance')) {
            try {
                $pdo->exec('ALTER TABLE employees ADD COLUMN travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER dearness_allowance');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE employees ADD COLUMN travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'employees', 'gratuity_monthly')) {
            try {
                $pdo->exec('ALTER TABLE employees ADD COLUMN gratuity_monthly DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gross_salary');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE employees ADD COLUMN gratuity_monthly DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_settings (
            id INT NOT NULL PRIMARY KEY DEFAULT 1,
            basic_pct_of_gross DECIMAL(6,2) NOT NULL DEFAULT 50.00,
            da_pct_of_basic DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            da_enabled TINYINT(1) NOT NULL DEFAULT 0,
            hra_pct_non_metro DECIMAL(6,2) NOT NULL DEFAULT 40.00,
            hra_pct_metro DECIMAL(6,2) NOT NULL DEFAULT 50.00,
            medical_enabled TINYINT(1) NOT NULL DEFAULT 1,
            medical_mode VARCHAR(12) NOT NULL DEFAULT 'fixed',
            medical_fixed DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            medical_pct_of_basic DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            travel_enabled TINYINT(1) NOT NULL DEFAULT 0,
            travel_mode VARCHAR(12) NOT NULL DEFAULT 'fixed',
            travel_fixed DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            travel_pct_of_basic DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            gratuity_pct_of_basic DECIMAL(7,3) NOT NULL DEFAULT 4.810,
            pf_employee_pct DECIMAL(6,2) NOT NULL DEFAULT 12.00,
            pf_employer_pct DECIMAL(6,2) NOT NULL DEFAULT 12.00,
            esi_employee_pct DECIMAL(6,2) NOT NULL DEFAULT 0.75,
            esi_employer_pct DECIMAL(6,2) NOT NULL DEFAULT 3.25,
            esi_gross_limit DECIMAL(12,2) NOT NULL DEFAULT 21000.00,
            working_days_default DECIMAL(6,2) NOT NULL DEFAULT 26.00,
            shift_hours_default DECIMAL(6,2) NOT NULL DEFAULT 8.00,
            ot_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            late_deduction_pct_of_daily DECIMAL(6,2) NOT NULL DEFAULT 10.00,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try {
            $pdo->exec('INSERT IGNORE INTO payroll_settings (id) VALUES (1)');
        } catch (Throwable) {
        }

        if (!self::hasColumn($pdo, 'salaries', 'dearness_allowance')) {
            try {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER medical_allowance');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN dearness_allowance DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'salaries', 'travel_allowance')) {
            try {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER dearness_allowance');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN travel_allowance DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'salaries', 'pf_employer_amount')) {
            try {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN pf_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER other_allowances');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN pf_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'salaries', 'tax_deduction')) {
            try {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN tax_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER pf_employer_amount');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN tax_deduction DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }
        if (!self::hasColumn($pdo, 'salaries', 'gratuity_accrual')) {
            try {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN gratuity_accrual DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tax_deduction');
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN gratuity_accrual DECIMAL(12,2) NOT NULL DEFAULT 0');
            }
        }

        if (self::hasColumn($pdo, 'employees', 'gross_salary')) {
            $bk = (int)$pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'employee_gross_backfill_v1'")->fetchColumn();
            if ($bk === 0) {
                try {
                    $pdo->exec('UPDATE employees SET gross_salary = ROUND(basic_salary + COALESCE(hra_amount, basic_salary * COALESCE(hra_percentage, 0) / 100) + COALESCE(medical_allowance, 0) + COALESCE(special_allowance, 0) + COALESCE(other_allowances, 0), 2)');
                    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('employee_gross_backfill_v1', '1')");
                } catch (Throwable) {
                }
            }
        }

        // quality_checks compatibility
        if (!self::hasColumn($pdo, 'quality_checks', 'quality_status')) {
            $pdo->exec("ALTER TABLE quality_checks ADD COLUMN quality_status ENUM('Pass','Fail') NOT NULL DEFAULT 'Pass' AFTER failed_qty");
        }
        if (!self::hasColumn($pdo, 'quality_checks', 'defects')) {
            $pdo->exec("ALTER TABLE quality_checks ADD COLUMN defects VARCHAR(255) NULL AFTER quality_status");
        }

        // inventory compatibility
        if (!self::hasColumn($pdo, 'inventory', 'reorder_level')) {
            $pdo->exec("ALTER TABLE inventory ADD COLUMN reorder_level INT NOT NULL DEFAULT 0 AFTER qty");
        }
        if (!self::hasColumn($pdo, 'inventory', 'warehouse_location')) {
            $pdo->exec("ALTER TABLE inventory ADD COLUMN warehouse_location VARCHAR(120) NOT NULL DEFAULT 'Main Warehouse' AFTER reorder_level");
        }
        if (!self::hasColumn($pdo, 'inventory', 'updated_at')) {
            $pdo->exec("ALTER TABLE inventory ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }

        // dispatch compatibility
        if (!self::hasColumn($pdo, 'dispatch', 'inventory_id')) {
            $pdo->exec("ALTER TABLE dispatch ADD COLUMN inventory_id INT NULL FIRST");
        }
        if (!self::hasColumn($pdo, 'dispatch', 'dispatch_status')) {
            $pdo->exec("ALTER TABLE dispatch ADD COLUMN dispatch_status ENUM('Created','In Transit','Delivered','Cancelled') NOT NULL DEFAULT 'Created' AFTER qty");
        }
        if (!self::hasColumn($pdo, 'dispatch', 'tracking_no')) {
            $pdo->exec("ALTER TABLE dispatch ADD COLUMN tracking_no VARCHAR(80) NULL AFTER dispatch_status");
        }

        // Optional compatibility table requested as payroll naming
        $pdo->exec("CREATE TABLE IF NOT EXISTS payroll (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            month INT NOT NULL,
            year INT NOT NULL,
            present_days DECIMAL(5,2) NOT NULL DEFAULT 0,
            paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
            unpaid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
            overtime_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
            net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_payroll_employee_month_year (employee_id, month, year),
            CONSTRAINT fk_payroll_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        require_once __DIR__ . '/../includes/department_hierarchy.php';
        install_department_hierarchy($pdo);

        $pdo->exec("CREATE TABLE IF NOT EXISTS salary_increments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            old_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            new_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            increment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            increment_percentage DECIMAL(8,2) NOT NULL DEFAULT 0,
            effective_date DATE NOT NULL,
            reason VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_increment_employee (employee_id),
            CONSTRAINT fk_increment_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

