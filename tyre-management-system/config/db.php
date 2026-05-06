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
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'Employee',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS employees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL UNIQUE,
                employee_code VARCHAR(50) NOT NULL UNIQUE,
                full_name VARCHAR(150) NOT NULL,
                department VARCHAR(100) NOT NULL,
                role_name VARCHAR(100) NOT NULL DEFAULT 'Employee',
                contact_no VARCHAR(20) NULL,
                address VARCHAR(255) NULL,
                profile_image VARCHAR(255) NULL,
                joining_date DATE NOT NULL,
                basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                attendance_date DATE NOT NULL,
                shift ENUM('Morning','Evening','Night') NOT NULL DEFAULT 'Morning',
                status ENUM('Present','Absent','Late','Half Day','Leave') NOT NULL DEFAULT 'Present',
                remarks VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_attendance_employee_day (employee_id, attendance_date),
                INDEX idx_attendance_date (attendance_date),
                CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS leaves (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                from_date DATE NOT NULL,
                to_date DATE NOT NULL,
                leave_type VARCHAR(50) NOT NULL DEFAULT 'Casual',
                reason VARCHAR(255) NOT NULL,
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
                unpaid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
                overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
                overtime_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
                basic DECIMAL(12,2) NOT NULL DEFAULT 0,
                net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_salary_employee_month (employee_id, month_year),
                CONSTRAINT fk_salaries_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
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
            "INSERT INTO users(full_name,email,password_hash,role,status) VALUES
                ('Super Admin','superadmin@ralson.local','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Super Admin','active'),
                ('HR Manager','hr@ralson.local','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','HR Manager','active'),
                ('Production Manager','production@ralson.local','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Production Manager','active'),
                ('Inventory Manager','inventory@ralson.local','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Inventory Manager','active'),
                ('Dispatch Manager','dispatch@ralson.local','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Dispatch Manager','active'),
                ('Quality Manager','quality@ralson.local','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Quality Manager','active'),
                ('Employee User','employee@ralson.local','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee','active')
            ON DUPLICATE KEY UPDATE
                full_name=VALUES(full_name),
                password_hash=VALUES(password_hash),
                role=VALUES(role),
                status='active'",
        ];

        foreach ($queries as $query) {
            $pdo->exec($query);
        }

        self::applyCompatibilityMigrations($pdo);

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

        // attendance
        if (!self::hasColumn($pdo, 'attendance', 'remarks')) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN remarks VARCHAR(255) NULL AFTER status");
        }

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

        // salaries/payroll compatibility columns
        if (!self::hasColumn($pdo, 'salaries', 'present_days')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN present_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER month_year");
        }
        if (!self::hasColumn($pdo, 'salaries', 'paid_leave_days')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER present_days");
        }
        if (!self::hasColumn($pdo, 'salaries', 'unpaid_leave_days')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN unpaid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER paid_leave_days");
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
    }
}

