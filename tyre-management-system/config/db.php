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
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci',
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS company_holidays (
                holiday_date DATE NOT NULL PRIMARY KEY,
                label VARCHAR(120) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS suppliers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                contact_person VARCHAR(100) NULL,
                phone VARCHAR(20) NULL,
                email VARCHAR(120) NULL,
                address VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS machines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                machine_code VARCHAR(50) NOT NULL UNIQUE,
                machine_name VARCHAR(150) NOT NULL,
                status ENUM('Active','Under Maintenance','Inactive') NOT NULL DEFAULT 'Active',
                last_maintenance_date DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_name VARCHAR(120) NOT NULL,
                batch_ref VARCHAR(80) NOT NULL,
                qty INT NOT NULL DEFAULT 0,
                reorder_level INT NOT NULL DEFAULT 0,
                warehouse_location VARCHAR(120) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_inventory_qty (qty)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS defect_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quality_check_id INT NOT NULL,
                production_id INT NOT NULL,
                failed_qty INT NOT NULL,
                defect_notes VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_defect_quality FOREIGN KEY (quality_check_id) REFERENCES quality_checks(id) ON DELETE CASCADE,
                CONSTRAINT fk_defect_production FOREIGN KEY (production_id) REFERENCES production(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value VARCHAR(255) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

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
                ('Accounts Manager','accounts@ralson.local','accounts_manager','\$2y\$10\$xEW0mYJ6Y4PdPMsst/j2WuGrA6JPKBY5vMJZ8.T8gRoR2GvxQ.YCi','Accounts Manager','active',0),
                ('Employee User','employee@ralson.local','employeeuser','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee','active',0)
            ON DUPLICATE KEY UPDATE
                full_name=VALUES(full_name),
                username=VALUES(username),
                password_hash=VALUES(password_hash),
                role=VALUES(role)");

        self::$initialized = true;
    }

    /** Production & machines — tyre factory workflow schema. */
    private static function migrateProductionModule(PDO $pdo): void
    {
        if (!self::hasTable($pdo, 'machines')) {
            return;
        }

        if (!self::hasColumn($pdo, 'machines', 'machine_type')) {
            $pdo->exec('ALTER TABLE machines ADD COLUMN machine_type VARCHAR(80) NULL AFTER machine_name');
        }
        if (!self::hasColumn($pdo, 'machines', 'shift_capacity')) {
            $pdo->exec('ALTER TABLE machines ADD COLUMN shift_capacity INT NOT NULL DEFAULT 0 AFTER machine_type');
        }
        if (!self::hasColumn($pdo, 'machines', 'notes')) {
            $pdo->exec('ALTER TABLE machines ADD COLUMN notes TEXT NULL AFTER last_maintenance_date');
        }

        try {
            $pdo->exec("ALTER TABLE machines MODIFY status VARCHAR(40) NOT NULL DEFAULT 'Idle'");
            $pdo->exec("UPDATE machines SET status = 'Active' WHERE status IN ('Running','Active','active')");
            $pdo->exec("UPDATE machines SET status = 'Idle' WHERE status IN ('Inactive','inactive')");
            $pdo->exec("UPDATE machines SET status = 'Under Repair' WHERE status IN ('Maintenance','Breakdown','Under Maintenance','under maintenance')");
        } catch (Throwable) {
        }

        if (!self::hasTable($pdo, 'production')) {
            return;
        }

        if (!self::hasColumn($pdo, 'production', 'operator_id')) {
            $pdo->exec('ALTER TABLE production ADD COLUMN operator_id INT NULL AFTER shift');
            try {
                $pdo->exec('ALTER TABLE production ADD CONSTRAINT fk_production_operator FOREIGN KEY (operator_id) REFERENCES employees(id) ON DELETE SET NULL');
            } catch (Throwable) {
            }
        }
        if (!self::hasColumn($pdo, 'production', 'tyre_type')) {
            $pdo->exec("ALTER TABLE production ADD COLUMN tyre_type VARCHAR(120) NULL AFTER operator_id");
        }
        if (!self::hasColumn($pdo, 'production', 'planned_quantity')) {
            $pdo->exec('ALTER TABLE production ADD COLUMN planned_quantity INT NOT NULL DEFAULT 0 AFTER tyre_type');
        }
        if (!self::hasColumn($pdo, 'production', 'rejected_quantity')) {
            $pdo->exec('ALTER TABLE production ADD COLUMN rejected_quantity INT NOT NULL DEFAULT 0 AFTER output_quantity');
        }
        if (!self::hasColumn($pdo, 'production', 'downtime_minutes')) {
            $pdo->exec('ALTER TABLE production ADD COLUMN downtime_minutes INT NOT NULL DEFAULT 0 AFTER rejected_quantity');
        }
        if (!self::hasColumn($pdo, 'production', 'remarks')) {
            $pdo->exec('ALTER TABLE production ADD COLUMN remarks VARCHAR(500) NULL AFTER downtime_minutes');
        }
        if (!self::hasColumn($pdo, 'production', 'efficiency_pct')) {
            $pdo->exec('ALTER TABLE production ADD COLUMN efficiency_pct DECIMAL(6,2) NULL AFTER remarks');
        }
        if (!self::hasColumn($pdo, 'production', 'entry_status')) {
            $pdo->exec("ALTER TABLE production ADD COLUMN entry_status VARCHAR(30) NOT NULL DEFAULT 'Submitted' AFTER efficiency_pct");
        }
        if (!self::hasColumn($pdo, 'production', 'inventory_deducted')) {
            $pdo->exec('ALTER TABLE production ADD COLUMN inventory_deducted TINYINT(1) NOT NULL DEFAULT 0 AFTER entry_status');
        }
        if (!self::hasColumn($pdo, 'production', 'payroll_ot_flag')) {
            $pdo->exec('ALTER TABLE production ADD COLUMN payroll_ot_flag TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Reserved for OT shift payroll link\' AFTER inventory_deducted');
        }

        try {
            $pdo->exec('ALTER TABLE production MODIFY raw_material_id INT NULL');
            $pdo->exec('ALTER TABLE production MODIFY material_used_qty DECIMAL(12,2) NULL DEFAULT NULL');
        } catch (Throwable) {
        }
    }

    /** Production orders workflow — Mixing → Building → Curing → QC → Finished. */
    private static function migrateProductionOrdersWorkflow(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS production_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_code VARCHAR(40) NOT NULL UNIQUE,
            tyre_type VARCHAR(120) NOT NULL,
            target_qty INT NOT NULL DEFAULT 0,
            deadline DATE NULL,
            priority VARCHAR(20) NOT NULL DEFAULT 'Normal',
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            current_stage VARCHAR(40) NOT NULL DEFAULT 'Mixing',
            total_produced INT NOT NULL DEFAULT 0,
            total_rejected INT NOT NULL DEFAULT 0,
            total_downtime INT NOT NULL DEFAULT 0,
            qc_passed_qty INT NOT NULL DEFAULT 0,
            qc_failed_qty INT NOT NULL DEFAULT 0,
            inventory_deducted TINYINT(1) NOT NULL DEFAULT 0,
            remarks VARCHAR(500) NULL,
            created_by_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_prod_orders_status (status),
            INDEX idx_prod_orders_stage (current_stage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS production_stages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            stage_name VARCHAR(40) NOT NULL,
            stage_order TINYINT NOT NULL DEFAULT 1,
            machine_id INT NULL,
            operator_id INT NULL,
            shift VARCHAR(20) NULL DEFAULT 'Morning',
            status VARCHAR(20) NOT NULL DEFAULT 'Pending',
            produced_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0,
            downtime_minutes INT NOT NULL DEFAULT 0,
            started_at DATETIME NULL,
            ended_at DATETIME NULL,
            remarks VARCHAR(500) NULL,
            UNIQUE KEY uk_order_stage (order_id, stage_name),
            INDEX idx_prod_stages_status (status),
            CONSTRAINT fk_prod_stage_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS production_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            stage_id INT NULL,
            action VARCHAR(60) NOT NULL,
            message VARCHAR(500) NOT NULL,
            user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_prod_logs_order (order_id),
            CONSTRAINT fk_prod_log_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS machine_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            stage_id INT NOT NULL,
            machine_id INT NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            released_at DATETIME NULL,
            INDEX idx_ma_order (order_id),
            CONSTRAINT fk_ma_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS production_downtime (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            stage_id INT NULL,
            minutes INT NOT NULL DEFAULT 0,
            reason VARCHAR(255) NULL,
            logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pd_order (order_id),
            CONSTRAINT fk_pd_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS production_bom (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tyre_type VARCHAR(120) NOT NULL,
            raw_material_id INT NOT NULL,
            qty_per_unit DECIMAL(12,4) NOT NULL DEFAULT 0,
            UNIQUE KEY uk_bom_tyre_material (tyre_type, raw_material_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS production_stage_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            stage_id INT NULL,
            stage_name VARCHAR(40) NOT NULL,
            machine_id INT NULL,
            operator_id INT NULL,
            shift VARCHAR(20) NULL,
            status VARCHAR(30) NOT NULL,
            produced_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0,
            downtime_minutes INT NOT NULL DEFAULT 0,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            remarks VARCHAR(500) NULL,
            action_type VARCHAR(40) NOT NULL,
            user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_psl_order (order_id),
            INDEX idx_psl_stage (stage_id),
            CONSTRAINT fk_psl_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (self::hasTable($pdo, 'quality_checks') && !self::hasColumn($pdo, 'quality_checks', 'production_order_id')) {
            $pdo->exec('ALTER TABLE quality_checks ADD COLUMN production_order_id INT NULL AFTER production_id');
            try {
                $pdo->exec('ALTER TABLE quality_checks ADD INDEX idx_qc_prod_order (production_order_id)');
            } catch (Throwable) {
            }
        }

        if (self::hasTable($pdo, 'production_stages') && self::hasTable($pdo, 'machines')) {
            try {
                $pdo->exec('ALTER TABLE production_stages ADD CONSTRAINT fk_prod_stage_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL');
            } catch (Throwable) {
            }
            try {
                $pdo->exec('ALTER TABLE production_stages ADD CONSTRAINT fk_prod_stage_operator FOREIGN KEY (operator_id) REFERENCES employees(id) ON DELETE SET NULL');
            } catch (Throwable) {
            }
        }
    }

    /** Simple daily production entry tables (no orders / workflow). */
    private static function migrateProductionEntryTables(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS mixing_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            production_date DATE NOT NULL,
            shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            machine_id INT NULL,
            operator_id INT NULL,
            tyre_type VARCHAR(120) NOT NULL,
            produced_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            rejected_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            remarks VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mix_entry_date (production_date),
            INDEX idx_mix_entry_shift (shift)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS building_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            production_date DATE NOT NULL,
            shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            machine_id INT NULL,
            operator_id INT NULL,
            tyre_type VARCHAR(120) NOT NULL,
            produced_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0,
            remarks VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bld_entry_date (production_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS curing_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            production_date DATE NOT NULL,
            shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            machine_id INT NULL,
            operator_id INT NULL,
            tyre_type VARCHAR(120) NOT NULL DEFAULT 'Tyre',
            produced_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0,
            downtime_minutes INT NOT NULL DEFAULT 0,
            remarks VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cur_entry_date (production_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS qc_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE NOT NULL,
            shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            inspector_name VARCHAR(150) NOT NULL,
            tyre_type VARCHAR(120) NOT NULL,
            checked_qty INT NOT NULL DEFAULT 0,
            passed_qty INT NOT NULL DEFAULT 0,
            failed_qty INT NOT NULL DEFAULT 0,
            defect_type VARCHAR(120) NULL,
            remarks VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qc_entry_date (entry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    /** QC inspections linked to curing output; defect lines; inventory stock categories. */
    private static function migrateQualityControlModule(PDO $pdo): void
    {
        if (self::hasTable($pdo, 'curing_entries') && !self::hasColumn($pdo, 'curing_entries', 'batch_code')) {
            $pdo->exec('ALTER TABLE curing_entries ADD COLUMN batch_code VARCHAR(40) NULL AFTER id');
        }
        if (self::hasTable($pdo, 'curing_entries') && !self::hasColumn($pdo, 'curing_entries', 'qc_status')) {
            $pdo->exec("ALTER TABLE curing_entries ADD COLUMN qc_status VARCHAR(30) NOT NULL DEFAULT 'Pending' AFTER remarks");
        }
        if (self::hasTable($pdo, 'curing_entries') && !self::hasColumn($pdo, 'curing_entries', 'qc_inspection_id')) {
            $pdo->exec('ALTER TABLE curing_entries ADD COLUMN qc_inspection_id INT NULL AFTER qc_status');
        }
        if (self::hasTable($pdo, 'inventory') && !self::hasColumn($pdo, 'inventory', 'stock_category')) {
            $pdo->exec("ALTER TABLE inventory ADD COLUMN stock_category VARCHAR(30) NULL DEFAULT NULL AFTER warehouse_location");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS qc_inspections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            curing_entry_id INT NOT NULL,
            batch_code VARCHAR(40) NOT NULL,
            tyre_type VARCHAR(120) NOT NULL,
            machine_id INT NULL,
            production_date DATE NOT NULL,
            production_shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            produced_qty INT NOT NULL DEFAULT 0,
            inspected_qty INT NOT NULL DEFAULT 0,
            passed_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0,
            rework_qty INT NOT NULL DEFAULT 0,
            inspector_name VARCHAR(150) NOT NULL,
            inspection_date DATE NOT NULL,
            inspection_shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            qc_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            remarks VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_qc_curing (curing_entry_id),
            INDEX idx_qc_insp_date (inspection_date),
            INDEX idx_qc_batch (batch_code),
            CONSTRAINT fk_qc_curing FOREIGN KEY (curing_entry_id) REFERENCES curing_entries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS qc_defect_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inspection_id INT NOT NULL,
            defect_type VARCHAR(40) NOT NULL,
            defect_label VARCHAR(120) NOT NULL,
            qty INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qc_def_insp (inspection_id),
            CONSTRAINT fk_qc_def_insp FOREIGN KEY (inspection_id) REFERENCES qc_inspections(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (self::hasTable($pdo, 'curing_entries')) {
            $pdo->exec("UPDATE curing_entries SET batch_code = CONCAT('CUR-', DATE_FORMAT(production_date,'%Y%m%d'), '-', LPAD(id, 4, '0'))
                WHERE batch_code IS NULL OR batch_code = ''");
            $pdo->exec("UPDATE curing_entries SET qc_status = 'Pending'
                WHERE qc_status IS NULL OR qc_status = ''");
        }
    }

    /** Warehouse: raw materials, inward, usage, activity log. */
    private static function migrateInventoryWarehouse(PDO $pdo): void
    {
        if (!self::hasColumn($pdo, 'suppliers', 'status')) {
            $pdo->exec("ALTER TABLE suppliers ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER address");
        }
        if (!self::hasColumn($pdo, 'suppliers', 'materials_supplied')) {
            $pdo->exec('ALTER TABLE suppliers ADD COLUMN materials_supplied VARCHAR(500) NULL AFTER status');
        }

        if (!self::hasColumn($pdo, 'raw_materials', 'material_code')) {
            $pdo->exec('ALTER TABLE raw_materials ADD COLUMN material_code VARCHAR(40) NULL AFTER id');
        }
        if (!self::hasColumn($pdo, 'raw_materials', 'category')) {
            $pdo->exec("ALTER TABLE raw_materials ADD COLUMN category VARCHAR(80) NOT NULL DEFAULT 'General' AFTER material_name");
        }
        if (!self::hasColumn($pdo, 'raw_materials', 'storage_location')) {
            $pdo->exec("ALTER TABLE raw_materials ADD COLUMN storage_location VARCHAR(120) NOT NULL DEFAULT 'Main Store' AFTER reorder_level");
        }
        if (!self::hasColumn($pdo, 'raw_materials', 'remarks')) {
            $pdo->exec('ALTER TABLE raw_materials ADD COLUMN remarks VARCHAR(500) NULL AFTER storage_location');
        }
        if (!self::hasColumn($pdo, 'raw_materials', 'status')) {
            $pdo->exec("ALTER TABLE raw_materials ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER remarks");
        }
        if (!self::hasColumn($pdo, 'raw_materials', 'max_stock_level')) {
            $pdo->exec('ALTER TABLE raw_materials ADD COLUMN max_stock_level DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER reorder_level');
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_inward (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inward_date DATE NOT NULL,
            supplier_id INT NULL,
            invoice_no VARCHAR(80) NULL,
            material_id INT NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
            rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            received_by VARCHAR(150) NULL,
            remarks VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_inward_date (inward_date),
            INDEX idx_inward_material (material_id),
            INDEX idx_inward_supplier (supplier_id),
            CONSTRAINT fk_inward_material FOREIGN KEY (material_id) REFERENCES raw_materials(id),
            CONSTRAINT fk_inward_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usage_date DATE NOT NULL,
            material_id INT NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
            usage_type ENUM('production','manual') NOT NULL DEFAULT 'manual',
            department VARCHAR(40) NULL,
            reference_id INT NULL,
            remarks VARCHAR(500) NULL,
            created_by VARCHAR(150) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usage_date (usage_date),
            INDEX idx_usage_material (material_id),
            INDEX idx_usage_type (usage_type),
            CONSTRAINT fk_usage_material FOREIGN KEY (material_id) REFERENCES raw_materials(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            activity_type VARCHAR(40) NOT NULL,
            material_id INT NULL,
            qty_change DECIMAL(12,2) NOT NULL DEFAULT 0,
            message VARCHAR(500) NOT NULL,
            INDEX idx_inv_activity_at (activity_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!self::hasColumn($pdo, 'stock_inward', 'batch_no')) {
            $pdo->exec('ALTER TABLE stock_inward ADD COLUMN batch_no VARCHAR(80) NULL AFTER material_id');
        }
        if (!self::hasColumn($pdo, 'stock_inward', 'expiry_date')) {
            $pdo->exec('ALTER TABLE stock_inward ADD COLUMN expiry_date DATE NULL AFTER batch_no');
        }
        if (!self::hasColumn($pdo, 'suppliers', 'gst_number')) {
            $pdo->exec('ALTER TABLE suppliers ADD COLUMN gst_number VARCHAR(40) NULL AFTER address');
        }
        if (!self::hasColumn($pdo, 'raw_materials', 'avg_purchase_rate')) {
            $pdo->exec('ALTER TABLE raw_materials ADD COLUMN avg_purchase_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER max_stock_level');
        }
        $purchaseInwardCols = [
            'pinv_no' => 'VARCHAR(40) NULL AFTER id',
            'challan_no' => 'VARCHAR(80) NULL AFTER invoice_no',
            'gst_percent' => 'DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER rate',
            'transport_charges' => 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gst_percent',
            'loading_charges' => 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER transport_charges',
            'other_charges' => 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loading_charges',
            'discount_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER other_charges',
            'subtotal' => 'DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER discount_amount',
            'gst_amount' => 'DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER subtotal',
            'total_amount' => 'DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER gst_amount',
            'paid_amount' => 'DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_amount',
            'payment_status' => "ENUM('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Unpaid' AFTER paid_amount",
            'payment_mode' => 'VARCHAR(40) NULL AFTER payment_status',
            'due_date' => 'DATE NULL AFTER payment_mode',
            'payment_ref' => 'VARCHAR(80) NULL AFTER due_date',
            'warehouse_location' => 'VARCHAR(120) NULL AFTER payment_ref',
        ];
        foreach ($purchaseInwardCols as $col => $def) {
            if (!self::hasColumn($pdo, 'stock_inward', $col)) {
                $pdo->exec("ALTER TABLE stock_inward ADD COLUMN {$col} {$def}");
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inward_id INT NOT NULL,
            payment_date DATE NOT NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            payment_mode VARCHAR(40) NULL,
            payment_ref VARCHAR(80) NULL,
            notes VARCHAR(500) NULL,
            recorded_by VARCHAR(150) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pp_inward (inward_id),
            INDEX idx_pp_date (payment_date),
            CONSTRAINT fk_pp_inward FOREIGN KEY (inward_id) REFERENCES stock_inward(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!self::hasColumn($pdo, 'stock_usage', 'usage_reason')) {
            $pdo->exec("ALTER TABLE stock_usage ADD COLUMN usage_reason VARCHAR(80) NULL AFTER department");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            adjust_date DATE NOT NULL,
            material_id INT NOT NULL,
            previous_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            actual_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            difference_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            reason VARCHAR(80) NOT NULL,
            operator_name VARCHAR(150) NULL,
            remarks VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_adj_date (adjust_date),
            INDEX idx_adj_material (material_id),
            CONSTRAINT fk_adj_material FOREIGN KEY (material_id) REFERENCES raw_materials(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $count = (int)$pdo->query('SELECT COUNT(*) FROM raw_materials')->fetchColumn();
        if ($count === 0) {
            $defaults = [
                ['RM-RUBBER', 'Natural Rubber', 'Rubber', 'kg', 5000, 800, 'Store-A1'],
                ['RM-CARBON', 'Carbon Black', 'Fillers', 'kg', 3000, 500, 'Store-A2'],
                ['RM-STEEL', 'Steel Wire', 'Reinforcement', 'kg', 2000, 300, 'Store-B1'],
                ['RM-FABRIC', 'Fabric', 'Reinforcement', 'piece', 1500, 200, 'Store-B2'],
                ['RM-CHEM', 'Chemicals', 'Chemicals', 'kg', 800, 100, 'Store-C1'],
                ['RM-PACK', 'Packaging', 'Packaging', 'piece', 5000, 500, 'Store-D1'],
            ];
            $ins = $pdo->prepare(
                'INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, storage_location, status)
                 VALUES (:c, :n, :cat, :u, :q, :r, :loc, \'Active\')'
            );
            foreach ($defaults as [$code, $name, $cat, $unit, $stock, $reorder, $loc]) {
                $ins->execute(['c' => $code, 'n' => $name, 'cat' => $cat, 'u' => $unit, 'q' => $stock, 'r' => $reorder, 'loc' => $loc]);
            }
        }

        $codes = $pdo->query("SELECT id, material_code FROM raw_materials WHERE material_code IS NULL OR material_code = ''")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($codes as $row) {
            $pdo->prepare('UPDATE raw_materials SET material_code = :c WHERE id = :id')
                ->execute(['c' => 'RM-' . (int)$row['id'], 'id' => (int)$row['id']]);
        }
    }

    /** Dispatch workflow — customers, extended dispatch records, simple statuses. */
    private static function migrateDispatchModule(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS dispatch_customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(150) NOT NULL,
            company VARCHAR(150) NULL,
            phone VARCHAR(30) NULL,
            gst_number VARCHAR(40) NULL,
            address VARCHAR(255) NULL,
            city VARCHAR(80) NULL,
            state VARCHAR(80) NULL,
            status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dcust_name (customer_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!self::hasColumn($pdo, 'dispatch', 'dispatch_code')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN dispatch_code VARCHAR(40) NULL AFTER id');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'customer_id')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN customer_id INT NULL AFTER customer_name');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'tyre_type')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN tyre_type VARCHAR(120) NULL AFTER customer_id');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'vehicle_no')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN vehicle_no VARCHAR(40) NULL AFTER invoice_no');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'driver_name')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN driver_name VARCHAR(150) NULL AFTER vehicle_no');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'transport_company')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN transport_company VARCHAR(150) NULL AFTER driver_name');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'remarks')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN remarks VARCHAR(500) NULL AFTER qty');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'status')) {
            $pdo->exec("ALTER TABLE dispatch ADD COLUMN status ENUM('Pending','Dispatched','Delivered') NOT NULL DEFAULT 'Delivered' AFTER remarks");
        }
        if (!self::hasColumn($pdo, 'dispatch', 'stock_deducted')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN stock_deducted TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
        }

        try {
            $pdo->exec("UPDATE dispatch SET status = CASE
                WHEN dispatch_status IN ('Delivered') THEN 'Delivered'
                WHEN dispatch_status IN ('In Transit','Created') THEN 'Dispatched'
                ELSE 'Pending' END WHERE status IS NULL OR status = ''");
        } catch (Throwable) {
        }

        if (self::hasColumn($pdo, 'dispatch', 'inventory_id')) {
            $pdo->exec('ALTER TABLE dispatch MODIFY inventory_id INT NULL');
        }

        $count = (int)$pdo->query('SELECT COUNT(*) FROM dispatch_customers')->fetchColumn();
        if ($count === 0) {
            $pdo->exec("INSERT INTO dispatch_customers (customer_name, company, phone, city, state) VALUES
                ('Metro Tyre Traders', 'Metro Tyre Traders Pvt Ltd', '9876500001', 'Mumbai', 'Maharashtra'),
                ('North Highway Fleet', 'North Highway Fleet Services', '9876500002', 'Delhi', 'Delhi'),
                ('Southern Auto Mart', 'Southern Auto Mart', '9876500003', 'Chennai', 'Tamil Nadu')");
        }

        foreach ($pdo->query('SELECT id FROM dispatch WHERE dispatch_code IS NULL OR dispatch_code = ""')->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
            $pdo->prepare('UPDATE dispatch SET dispatch_code = :c WHERE id = :id')->execute([
                'c' => 'DSP-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT),
                'id' => (int)$id,
            ]);
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS dispatch_drivers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NULL,
            license_number VARCHAR(60) NULL,
            vehicle_no VARCHAR(40) NULL,
            transport_company VARCHAR(150) NULL,
            address VARCHAR(255) NULL,
            status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ddriver_name (driver_name),
            INDEX idx_ddriver_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!self::hasColumn($pdo, 'dispatch', 'driver_id')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN driver_id INT NULL AFTER driver_name');
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS dispatch_transport_companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(150) NOT NULL,
            contact_person VARCHAR(120) NULL,
            phone VARCHAR(30) NULL,
            gst_number VARCHAR(40) NULL,
            address VARCHAR(255) NULL,
            status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_transport_name (company_name),
            INDEX idx_transport_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!self::hasColumn($pdo, 'dispatch_drivers', 'transport_company_id')) {
            $pdo->exec('ALTER TABLE dispatch_drivers ADD COLUMN transport_company_id INT NULL AFTER vehicle_no');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'transport_company_id')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN transport_company_id INT NULL AFTER transport_company');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'gross_weight_kg')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN gross_weight_kg DECIMAL(12,2) NULL AFTER qty');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'tare_weight_kg')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN tare_weight_kg DECIMAL(12,2) NULL AFTER gross_weight_kg');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'net_weight_kg')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN net_weight_kg DECIMAL(12,2) NULL AFTER tare_weight_kg');
        }

        $transportCount = (int)$pdo->query('SELECT COUNT(*) FROM dispatch_transport_companies')->fetchColumn();
        if ($transportCount === 0) {
            $pdo->exec("INSERT INTO dispatch_transport_companies (company_name, contact_person, phone, gst_number, address, status) VALUES
                ('Punjab Logistics', 'Harpreet Singh', '9876520001', '03AABCP1234F1Z5', 'Ludhiana, Punjab', 'Active'),
                ('Western Freight Lines', 'Amit Shah', '9876520002', '24AABCW5678G1Z9', 'Ahmedabad, Gujarat', 'Active'),
                ('North Star Transport', 'Vikram Mehta', '9876520003', '06AABCN9012H1Z3', 'Gurgaon, Haryana', 'Active')");
        }

        try {
            $pdo->exec("UPDATE dispatch_drivers d
                INNER JOIN dispatch_transport_companies t ON t.company_name = d.transport_company
                SET d.transport_company_id = t.id
                WHERE d.transport_company_id IS NULL AND d.transport_company IS NOT NULL AND d.transport_company != ''");
        } catch (Throwable) {
        }

        $driverCount = (int)$pdo->query('SELECT COUNT(*) FROM dispatch_drivers')->fetchColumn();
        if ($driverCount === 0) {
            $pdo->exec("INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_no, transport_company_id, transport_company, address, status)
                SELECT 'Ravi Kumar', '9876510001', 'DL-09-2018-0012345', 'PB10AX1234', id, 'Punjab Logistics', 'Ludhiana, Punjab', 'Active' FROM dispatch_transport_companies WHERE company_name = 'Punjab Logistics' LIMIT 1");
            $pdo->exec("INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_no, transport_company_id, transport_company, address, status)
                SELECT 'Suresh Patel', '9876510002', 'GJ-12-2019-0098765', 'GJ01BT5678', id, 'Western Freight Lines', 'Ahmedabad, Gujarat', 'Active' FROM dispatch_transport_companies WHERE company_name = 'Western Freight Lines' LIMIT 1");
            $pdo->exec("INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_no, transport_company_id, transport_company, address, status)
                SELECT 'Mohit Singh', '9876510003', 'HR-05-2020-0045678', 'HR26CD9012', id, 'North Star Transport', 'Gurgaon, Haryana', 'Active' FROM dispatch_transport_companies WHERE company_name = 'North Star Transport' LIMIT 1");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS dispatch_vehicles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_number VARCHAR(40) NOT NULL,
            vehicle_type VARCHAR(60) NULL,
            capacity VARCHAR(40) NULL,
            driver_id INT NULL,
            transport_company_id INT NULL,
            status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_vehicle_number (vehicle_number),
            INDEX idx_vehicle_status (status),
            INDEX idx_vehicle_driver (driver_id),
            INDEX idx_vehicle_transport (transport_company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!self::hasColumn($pdo, 'dispatch_drivers', 'vehicle_id')) {
            $pdo->exec('ALTER TABLE dispatch_drivers ADD COLUMN vehicle_id INT NULL AFTER license_number');
        }
        if (!self::hasColumn($pdo, 'dispatch', 'vehicle_id')) {
            $pdo->exec('ALTER TABLE dispatch ADD COLUMN vehicle_id INT NULL AFTER vehicle_no');
        }
        if (!self::hasColumn($pdo, 'dispatch_vehicles', 'insurance_expiry')) {
            try {
                $pdo->exec('ALTER TABLE dispatch_vehicles ADD COLUMN insurance_expiry DATE NULL AFTER capacity');
            } catch (Throwable) {
            }
        }
        if (!self::hasColumn($pdo, 'dispatch_vehicles', 'rc_number')) {
            try {
                $pdo->exec('ALTER TABLE dispatch_vehicles ADD COLUMN rc_number VARCHAR(60) NULL AFTER insurance_expiry');
            } catch (Throwable) {
            }
        }

        $vehicleCount = (int)$pdo->query('SELECT COUNT(*) FROM dispatch_vehicles')->fetchColumn();
        if ($vehicleCount === 0) {
            foreach ($pdo->query(
                'SELECT id, vehicle_no, transport_company_id FROM dispatch_drivers WHERE vehicle_no IS NOT NULL AND vehicle_no != \'\''
            )->fetchAll(PDO::FETCH_ASSOC) ?: [] as $dr) {
                $vn = strtoupper(trim((string)$dr['vehicle_no']));
                $tid = (int)($dr['transport_company_id'] ?? 0) ?: null;
                $pdo->prepare(
                    'INSERT IGNORE INTO dispatch_vehicles (vehicle_number, vehicle_type, capacity, driver_id, transport_company_id, status)
                     VALUES (:n, \'Truck\', \'40 tyres\', :did, :tid, \'Active\')'
                )->execute(['n' => $vn, 'did' => (int)$dr['id'], 'tid' => $tid]);
                $vid = (int)$pdo->lastInsertId();
                if ($vid < 1) {
                    $st = $pdo->prepare('SELECT id FROM dispatch_vehicles WHERE vehicle_number = :n LIMIT 1');
                    $st->execute(['n' => $vn]);
                    $vid = (int)$st->fetchColumn();
                }
                if ($vid > 0) {
                    $pdo->prepare('UPDATE dispatch_drivers SET vehicle_id = :vid WHERE id = :id')->execute([
                        'vid' => $vid,
                        'id' => (int)$dr['id'],
                    ]);
                }
            }
        }
    }

    /** Parallel department production — Mixing, Building, Curing, QC batches. */
    private static function migrateProductionDepartments(PDO $pdo): void
    {
        if (!self::hasTable($pdo, 'production_orders')) {
            return;
        }

        if (self::hasTable($pdo, 'machines') && !self::hasColumn($pdo, 'machines', 'department')) {
            $pdo->exec("ALTER TABLE machines ADD COLUMN department VARCHAR(40) NULL AFTER machine_type");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS mixing_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NULL,
            batch_code VARCHAR(40) NOT NULL UNIQUE,
            compound_name VARCHAR(120) NOT NULL DEFAULT 'Rubber Compound',
            produced_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            wastage_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            unit VARCHAR(20) NOT NULL DEFAULT 'kg',
            machine_id INT NULL,
            operator_id INT NULL,
            shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            production_date DATE NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Ready',
            notes VARCHAR(500) NULL,
            inventory_deducted TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mix_order (order_id),
            INDEX idx_mix_date (production_date),
            CONSTRAINT fk_mix_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS building_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NULL,
            mixing_batch_id INT NULL,
            batch_code VARCHAR(40) NOT NULL UNIQUE,
            produced_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0,
            machine_id INT NULL,
            operator_id INT NULL,
            shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            production_date DATE NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'In Progress',
            notes VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bld_order (order_id),
            INDEX idx_bld_mix (mixing_batch_id),
            CONSTRAINT fk_bld_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE SET NULL,
            CONSTRAINT fk_bld_mix FOREIGN KEY (mixing_batch_id) REFERENCES mixing_batches(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS curing_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NULL,
            building_batch_id INT NULL,
            batch_code VARCHAR(40) NOT NULL UNIQUE,
            cured_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0,
            cycle_time_min INT NOT NULL DEFAULT 0,
            downtime_min INT NOT NULL DEFAULT 0,
            machine_id INT NULL,
            operator_id INT NULL,
            shift VARCHAR(20) NOT NULL DEFAULT 'Morning',
            production_date DATE NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'In Progress',
            notes VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cur_order (order_id),
            INDEX idx_cur_bld (building_batch_id),
            CONSTRAINT fk_cur_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE SET NULL,
            CONSTRAINT fk_cur_bld FOREIGN KEY (building_batch_id) REFERENCES building_batches(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS production_qc_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NULL,
            curing_batch_id INT NULL,
            batch_ref VARCHAR(40) NULL,
            inspection_date DATE NOT NULL,
            inspected_qty INT NOT NULL DEFAULT 0,
            passed_qty INT NOT NULL DEFAULT 0,
            rejected_qty INT NOT NULL DEFAULT 0,
            defect_type VARCHAR(120) NULL,
            inspector_name VARCHAR(150) NOT NULL,
            remarks VARCHAR(500) NULL,
            inventory_added TINYINT(1) NOT NULL DEFAULT 0,
            warehouse_location VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pqc_order (order_id),
            INDEX idx_pqc_date (inspection_date),
            CONSTRAINT fk_pqc_order FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE SET NULL,
            CONSTRAINT fk_pqc_cur FOREIGN KEY (curing_batch_id) REFERENCES curing_batches(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
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

    private static function hasTable(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name");
        $stmt->execute(['table_name' => $table]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private static function normalizeErpCollation(PDO $pdo): void
    {
        $flagKey = 'erp_collation_utf8mb4_general_ci_v1';
        $flagCheck = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = ?');
        $flagCheck->execute([$flagKey]);
        if ((int)$flagCheck->fetchColumn() > 0) {
            return;
        }

        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName !== '') {
            try {
                $pdo->exec('ALTER DATABASE `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
            } catch (Throwable) {
            }
        }

        $tables = [
            'users',
            'employees',
            'department_categories',
            'departments',
            'designations',
            'attendance',
            'leaves',
            'salaries',
            'payroll',
            'payroll_settings',
            'settings',
            'company_holidays',
            'hr_holidays',
        ];
        foreach ($tables as $table) {
            if (!self::hasTable($pdo, $table)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            } catch (Throwable) {
            }
        }

        $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)')
            ->execute([$flagKey, '1']);
    }

    /** Ensure attendance FK cascades on employee delete (legacy DBs may lack ON DELETE CASCADE). */
    private static function normalizeEmployeeFkCascade(PDO $pdo): void
    {
        if (!self::hasTable($pdo, 'attendance') || !self::hasTable($pdo, 'employees')) {
            return;
        }
        $flagKey = 'attendance_fk_cascade_v1';
        $flagCheck = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = ?');
        $flagCheck->execute([$flagKey]);
        if ((int)$flagCheck->fetchColumn() > 0) {
            return;
        }
        try {
            $pdo->exec('ALTER TABLE attendance DROP FOREIGN KEY fk_att_employee');
        } catch (Throwable) {
        }
        try {
            $pdo->exec('ALTER TABLE attendance DROP FOREIGN KEY fk_attendance_employee');
        } catch (Throwable) {
        }
        try {
            $pdo->exec(
                'ALTER TABLE attendance ADD CONSTRAINT fk_att_employee FOREIGN KEY (employee_id) '
                . 'REFERENCES employees(id) ON DELETE CASCADE'
            );
        } catch (Throwable) {
        }
        $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)')
            ->execute([$flagKey, '1']);
    }

    private static function applyCompatibilityMigrations(PDO $pdo): void
    {
        // Runtime compatibility patches (legacy). Portable schema history lives in
        // database/sql/migrations/*.sql — add new files there and run: php tools/db_sync.php export

        // users compatibility for department roles
        if (!self::hasColumn($pdo, 'users', 'full_name')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(150) NULL");
        }
        if (!self::hasColumn($pdo, 'users', 'password_hash')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL");
        }
        if (!self::hasColumn($pdo, 'users', 'status')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
        } else {
            try {
                $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
                $type = strtolower((string)($col['Type'] ?? ''));
                if ($type !== '' && str_contains($type, 'enum')) {
                    $pdo->exec("ALTER TABLE users MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active'");
                }
            } catch (Throwable) {
            }
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

        if (!self::hasColumn($pdo, 'attendance', 'needs_verification')) {
            $pdo->exec('ALTER TABLE attendance ADD COLUMN needs_verification TINYINT(1) NOT NULL DEFAULT 0 AFTER is_emergency_duty');
        }
        if (!self::hasColumn($pdo, 'attendance', 'verified_by')) {
            $pdo->exec('ALTER TABLE attendance ADD COLUMN verified_by INT NULL AFTER needs_verification');
        }
        if (!self::hasColumn($pdo, 'attendance', 'verified_at')) {
            $pdo->exec('ALTER TABLE attendance ADD COLUMN verified_at DATETIME NULL AFTER verified_by');
        }
        if (!self::hasColumn($pdo, 'attendance', 'linked_leave_id')) {
            $pdo->exec('ALTER TABLE attendance ADD COLUMN linked_leave_id INT NULL AFTER remarks');
            try {
                $pdo->exec('ALTER TABLE attendance ADD INDEX idx_attendance_linked_leave (linked_leave_id)');
            } catch (Throwable) {
            }
        }

        if (!self::hasColumn($pdo, 'employees', 'emergency_contact')) {
            $pdo->exec('ALTER TABLE employees ADD COLUMN emergency_contact VARCHAR(120) NULL AFTER contact_no');
        }

        $attPolicyCols = [
            'full_day_min_hours' => 'DECIMAL(6,2) NOT NULL DEFAULT 8.00',
            'half_day_min_hours' => 'DECIMAL(6,2) NOT NULL DEFAULT 4.00',
            'grace_late_minutes' => 'INT NOT NULL DEFAULT 15',
            'min_valid_punch_hours' => 'DECIMAL(6,2) NOT NULL DEFAULT 0.50',
            'auto_absent_after_hours' => 'DECIMAL(6,2) NOT NULL DEFAULT 0.00',
            'ot_threshold_hours' => 'DECIMAL(6,2) NOT NULL DEFAULT 0.00',
        ];
        foreach ($attPolicyCols as $col => $def) {
            if (!self::hasColumn($pdo, 'payroll_settings', $col)) {
                $pdo->exec("ALTER TABLE payroll_settings ADD COLUMN {$col} {$def}");
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS company_holidays (
            holiday_date DATE NOT NULL PRIMARY KEY,
            label VARCHAR(120) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

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
        if (!self::hasColumn($pdo, 'leaves', 'paid_days')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN paid_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER status');
        }
        if (!self::hasColumn($pdo, 'leaves', 'half_paid_days')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN half_paid_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER paid_days');
        }
        if (!self::hasColumn($pdo, 'leaves', 'unpaid_days')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN unpaid_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER half_paid_days');
        }
        if (!self::hasColumn($pdo, 'leaves', 'total_days')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN total_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER unpaid_days');
        }
        if (!self::hasColumn($pdo, 'leaves', 'is_emergency')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN is_emergency TINYINT(1) NOT NULL DEFAULT 0 AFTER total_days');
        }
        if (!self::hasColumn($pdo, 'leaves', 'auto_approved')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN auto_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER is_emergency');
        }
        if (!self::hasColumn($pdo, 'leaves', 'staffing_risk')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN staffing_risk VARCHAR(20) NOT NULL DEFAULT 'Safe' AFTER auto_approved");
        }
        if (!self::hasColumn($pdo, 'leaves', 'system_note')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN system_note VARCHAR(255) NULL AFTER staffing_risk');
        }
        if (!self::hasColumn($pdo, 'leaves', 'day_allocation_json')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN day_allocation_json TEXT NULL AFTER system_note');
        }
        if (!self::hasColumn($pdo, 'leaves', 'approved_by')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN approved_by INT NULL AFTER status');
        }
        if (!self::hasColumn($pdo, 'leaves', 'rejection_reason')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER day_allocation_json');
        }
        if (!self::hasColumn($pdo, 'leaves', 'approved_at')) {
            $after = self::hasColumn($pdo, 'leaves', 'approved_by') ? 'approved_by' : 'status';
            $pdo->exec("ALTER TABLE leaves ADD COLUMN approved_at DATETIME NULL AFTER {$after}");
        }
        if (!self::hasColumn($pdo, 'leaves', 'entry_source')) {
            $pdo->exec("ALTER TABLE leaves ADD COLUMN entry_source VARCHAR(20) NOT NULL DEFAULT 'employee' AFTER auto_approved");
        }
        if (!self::hasColumn($pdo, 'leaves', 'recorded_by')) {
            $pdo->exec('ALTER TABLE leaves ADD COLUMN recorded_by INT NULL AFTER entry_source');
        }
        try {
            $pdo->exec("ALTER TABLE leaves MODIFY status ENUM('Applied','Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'");
        } catch (Throwable) {
            // enum may already include Pending
        }
        $pdo->exec("UPDATE leaves SET status = 'Pending' WHERE status = 'Applied'");

        $pdo->exec("CREATE TABLE IF NOT EXISTS hr_notification_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_key VARCHAR(120) NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            dismissed_at TIMESTAMP NULL,
            UNIQUE KEY uq_hr_notif_user_key (user_id, notification_key),
            INDEX idx_hr_notif_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS leave_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NULL,
            leave_id INT NULL,
            notice_type VARCHAR(40) NOT NULL,
            message VARCHAR(500) NOT NULL,
            audience ENUM('employee','hr') NOT NULL DEFAULT 'employee',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ln_employee (employee_id),
            INDEX idx_ln_audience (audience, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $leaveSettings = [
            'leave_auto_approve_enabled' => '0',
            'leave_min_present_pct' => '50',
        ];
        foreach ($leaveSettings as $key => $val) {
            $chk = $pdo->prepare('SELECT 1 FROM settings WHERE setting_key = :k LIMIT 1');
            $chk->execute(['k' => $key]);
            if (!$chk->fetchColumn()) {
                $ins = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)');
                $ins->execute(['k' => $key, 'v' => $val]);
            }
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
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
        if (!self::hasColumn($pdo, 'salaries', 'payment_status')) {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid' AFTER net_salary");
        }
        if (!self::hasColumn($pdo, 'salaries', 'is_draft')) {
            $pdo->exec('ALTER TABLE salaries ADD COLUMN is_draft TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_status');
        }
        if (!self::hasColumn($pdo, 'salaries', 'paid_at')) {
            $pdo->exec('ALTER TABLE salaries ADD COLUMN paid_at DATETIME NULL DEFAULT NULL AFTER is_draft');
        }
        if (!self::hasColumn($pdo, 'salaries', 'generated_at')) {
            try {
                if (self::hasColumn($pdo, 'salaries', 'paid_at')) {
                    $pdo->exec('ALTER TABLE salaries ADD COLUMN generated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER paid_at');
                } elseif (self::hasColumn($pdo, 'salaries', 'net_salary')) {
                    $pdo->exec('ALTER TABLE salaries ADD COLUMN generated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER net_salary');
                } else {
                    $pdo->exec('ALTER TABLE salaries ADD COLUMN generated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
                }
            } catch (Throwable) {
                $pdo->exec('ALTER TABLE salaries ADD COLUMN generated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        require_once __DIR__ . '/../includes/department_hierarchy.php';
        install_department_hierarchy($pdo);

        if (self::hasTable($pdo, 'departments') && !self::hasColumn($pdo, 'departments', 'min_staff_required')) {
            $pdo->exec('ALTER TABLE departments ADD COLUMN min_staff_required INT NULL DEFAULT NULL AFTER status');
        }

        self::migrateProductionModule($pdo);
        self::migrateProductionOrdersWorkflow($pdo);
        self::migrateProductionDepartments($pdo);
        self::migrateProductionEntryTables($pdo);
        require_once __DIR__ . '/../includes/machine_service.php';
        mach_ensure_schema($pdo);
        self::migrateInventoryWarehouse($pdo);
        self::migrateDispatchModule($pdo);
        self::migrateQualityControlModule($pdo);

        self::normalizeErpCollation($pdo);
        self::normalizeEmployeeFkCascade($pdo);

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        require_once __DIR__ . '/../includes/database_migration.php';
        $ranSqlMigrations = DatabaseMigrationRunner::runPending($pdo, false);
        if ($ranSqlMigrations !== [] && (getenv('ERP_AUTO_DB_BACKUP') === '1' || (defined('APP_ENV') && APP_ENV === 'local'))) {
            DatabaseMigrationRunner::exportFullBackup($pdo);
        }
    }
}

