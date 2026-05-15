<?php
declare(strict_types=1);

/**
 * Industrial department hierarchy (categories → departments → designations)
 * for tyre manufacturing ERP. Used by HR employee forms and reporting filters.
 */

function dh_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
    $stmt->execute(['t' => $table, 'c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function dh_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :t');
    $stmt->execute(['t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function install_department_hierarchy(PDO $pdo): void
{
    if (!dh_table_exists($pdo, 'employees')) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS department_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(120) NOT NULL,
        category_code VARCHAR(40) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_dept_cat_code (category_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        department_name VARCHAR(160) NOT NULL,
        department_short_name VARCHAR(80) NULL,
        department_code VARCHAR(40) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_dept_code (department_code),
        UNIQUE KEY uk_dept_cat_name (category_id, department_name),
        INDEX idx_dept_category (category_id),
        CONSTRAINT fk_dept_category FOREIGN KEY (category_id) REFERENCES department_categories(id)
            ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS designations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        designation_name VARCHAR(120) NOT NULL,
        designation_code VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_desig_dept_code (department_id, designation_code),
        INDEX idx_desig_department (department_id),
        CONSTRAINT fk_desig_department FOREIGN KEY (department_id) REFERENCES departments(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!dh_column_exists($pdo, 'employees', 'department_id')) {
        $pdo->exec('ALTER TABLE employees ADD COLUMN department_id INT NULL AFTER designation');
    }
    if (!dh_column_exists($pdo, 'employees', 'designation_id')) {
        $pdo->exec('ALTER TABLE employees ADD COLUMN designation_id INT NULL AFTER department_id');
    }

    try {
        $pdo->exec('CREATE INDEX idx_employees_department_id ON employees (department_id)');
    } catch (Throwable) {
        // index may already exist
    }
    try {
        $pdo->exec('CREATE INDEX idx_employees_designation_id ON employees (designation_id)');
    } catch (Throwable) {
    }

    dh_seed_department_master_data($pdo);

    $pendingOrg = (int)$pdo->query('SELECT COUNT(*) FROM employees WHERE department_id IS NULL')->fetchColumn();
    if ($pendingOrg > 0) {
        dh_migrate_legacy_employee_departments($pdo);
        dh_sync_all_employee_org_labels($pdo);
    }
}

function dh_seed_department_master_data(PDO $pdo): void
{
    $insCat = $pdo->prepare('INSERT IGNORE INTO department_categories (category_name, category_code, status) VALUES (?,?,?)');
    $categories = [
        ['Core Production', 'CORE_PROD'],
        ['Quality & Safety', 'QUAL_SAFETY'],
        ['Engineering & Technical', 'ENG_TECH'],
        ['Logistics & Inventory', 'LOG_INV'],
        ['Administrative & Office', 'ADMIN_OFF'],
        ['Business & Management', 'BIZ_MGMT'],
    ];
    foreach ($categories as [$name, $code]) {
        $insCat->execute([$name, $code, 'active']);
    }

    $catId = static function (PDO $pdo, string $code): int {
        $st = $pdo->prepare('SELECT id FROM department_categories WHERE category_code = ? LIMIT 1');
        $st->execute([$code]);
        return (int)$st->fetchColumn();
    };

    $insDept = $pdo->prepare('INSERT IGNORE INTO departments (category_id, department_name, department_short_name, department_code, status)
        VALUES (:cid,:name,:short,:code,:st)');

    $deptRows = [
        ['CORE_PROD', 'Production Planning & Control (PPC)', 'PPC', 'DEPT_PPC'],
        ['CORE_PROD', 'Raw Materials & Inventory Management', 'Raw Materials', 'DEPT_RAW_MAT'],
        ['CORE_PROD', 'Mixing & Compounding', 'Mixing', 'DEPT_MIXING'],
        ['CORE_PROD', 'Component Preparation (Extrusion & Calendering)', 'Comp. Prep.', 'DEPT_COMP_PREP'],
        ['CORE_PROD', 'Tire Building / Product Assembly', 'Tire Building', 'DEPT_TIRE_BUILD'],
        ['CORE_PROD', 'Curing & Vulcanization', 'Curing', 'DEPT_CURING'],
        ['QUAL_SAFETY', 'Quality Assurance & Testing (QA/QC)', 'QA/QC', 'DEPT_QA_QC'],
        ['QUAL_SAFETY', 'Environment Health & Safety (EHS)', 'EHS', 'DEPT_EHS'],
        ['ENG_TECH', 'Plant Maintenance & Engineering', 'Maintenance', 'DEPT_MAINT'],
        ['ENG_TECH', 'IT Support', 'IT', 'DEPT_IT'],
        ['LOG_INV', 'Logistics & Dispatch', 'Dispatch', 'DEPT_LOG_DISP'],
        ['LOG_INV', 'Warehouse Management', 'Warehouse', 'DEPT_WH'],
        ['LOG_INV', 'Store Management', 'Store', 'DEPT_STORE'],
        ['ADMIN_OFF', 'Human Resources (HR)', 'HR', 'DEPT_HR'],
        ['ADMIN_OFF', 'Administration', 'Administration', 'DEPT_ADMIN'],
        ['ADMIN_OFF', 'Accounts & Finance', 'Accounts', 'DEPT_ACC'],
        ['ADMIN_OFF', 'Purchase & Procurement', 'Purchase', 'DEPT_PUR'],
        ['ADMIN_OFF', 'Unclassified (Legacy)', 'Legacy', 'DEPT_UNCLASS'],
        ['BIZ_MGMT', 'Sales & Marketing', 'Sales', 'DEPT_SALES'],
        ['BIZ_MGMT', 'Management', 'Management', 'DEPT_MGMT'],
    ];

    foreach ($deptRows as [$catCode, $dname, $dshort, $dcode]) {
        $cid = $catId($pdo, $catCode);
        if ($cid < 1) {
            continue;
        }
        $insDept->execute(['cid' => $cid, 'name' => $dname, 'short' => $dshort, 'code' => $dcode, 'st' => 'active']);
    }

    $deptPk = static function (PDO $pdo, string $deptCode): int {
        $st = $pdo->prepare('SELECT id FROM departments WHERE department_code = ? LIMIT 1');
        $st->execute([$deptCode]);
        return (int)$st->fetchColumn();
    };

    $insDes = $pdo->prepare('INSERT IGNORE INTO designations (department_id, designation_name, designation_code, status)
        VALUES (:did,:name,:code,:st)');

    $designationSeed = [
        'DEPT_PPC' => [
            ['PPC Executive', 'DES_PPC_EXEC'],
            ['PPC Manager', 'DES_PPC_MGR'],
            ['Production Planner', 'DES_PPC_PLNR'],
            ['Assistant Planner', 'DES_PPC_ASST'],
        ],
        'DEPT_RAW_MAT' => [
            ['Raw Material Officer', 'DES_RM_OFF'],
            ['Store Keeper', 'DES_RM_SK'],
            ['Inventory Analyst', 'DES_RM_ANL'],
        ],
        'DEPT_MIXING' => [
            ['Mixing Operator', 'DES_MIX_OP'],
            ['Compounding Technician', 'DES_MIX_TECH'],
            ['Shift Supervisor', 'DES_MIX_SUP'],
        ],
        'DEPT_COMP_PREP' => [
            ['Extrusion Operator', 'DES_EXT_OP'],
            ['Calendering Operator', 'DES_CAL_OP'],
            ['Process Technician', 'DES_COMP_TECH'],
        ],
        'DEPT_TIRE_BUILD' => [
            ['Tire Builder', 'DES_TB_OP'],
            ['Building Line Supervisor', 'DES_TB_SUP'],
            ['Assembly Technician', 'DES_TB_TECH'],
        ],
        'DEPT_CURING' => [
            ['Curing Operator', 'DES_CUR_OP'],
            ['Vulcanization Technician', 'DES_CUR_TECH'],
            ['Curing Supervisor', 'DES_CUR_SUP'],
        ],
        'DEPT_QA_QC' => [
            ['QA Inspector', 'DES_QA_INSP'],
            ['Quality Engineer', 'DES_QA_ENG'],
            ['Testing Operator', 'DES_QA_TEST'],
            ['QA Manager', 'DES_QA_MGR'],
        ],
        'DEPT_EHS' => [
            ['EHS Officer', 'DES_EHS_OFF'],
            ['Safety Supervisor', 'DES_EHS_SUP'],
            ['Industrial Hygienist', 'DES_EHS_HYG'],
        ],
        'DEPT_MAINT' => [
            ['Maintenance Engineer', 'DES_MT_ENG'],
            ['Electrical Technician', 'DES_MT_ELEC'],
            ['Machine Operator', 'DES_MT_MACH'],
            ['Millwright', 'DES_MT_MILL'],
        ],
        'DEPT_IT' => [
            ['IT Support Engineer', 'DES_IT_ENG'],
            ['System Administrator', 'DES_IT_SA'],
            ['Helpdesk Executive', 'DES_IT_HD'],
        ],
        'DEPT_LOG_DISP' => [
            ['Dispatch Executive', 'DES_LOG_DISP_EX'],
            ['Logistics Coordinator', 'DES_LOG_COORD'],
            ['Fleet Supervisor', 'DES_LOG_FLEET'],
        ],
        'DEPT_WH' => [
            ['Warehouse Supervisor', 'DES_WH_SUP'],
            ['Warehouse Associate', 'DES_WH_ASC'],
            ['Forklift Operator', 'DES_WH_FL'],
        ],
        'DEPT_STORE' => [
            ['Store Incharge', 'DES_ST_INC'],
            ['Store Assistant', 'DES_ST_ASST'],
            ['Issuing Clerk', 'DES_ST_ISS'],
        ],
        'DEPT_HR' => [
            ['HR Executive', 'DES_HR_EX'],
            ['HR Manager', 'DES_HR_MGR'],
            ['Talent Acquisition Specialist', 'DES_HR_TA'],
        ],
        'DEPT_ADMIN' => [
            ['Admin Executive', 'DES_AD_EX'],
            ['Receptionist', 'DES_AD_REC'],
            ['Office Assistant', 'DES_AD_ASST'],
            ['Peon', 'DES_AD_PEON'],
        ],
        'DEPT_ACC' => [
            ['Accountant', 'DES_ACC_ACT'],
            ['Finance Executive', 'DES_ACC_FIN'],
            ['Payroll Officer', 'DES_ACC_PAY'],
        ],
        'DEPT_PUR' => [
            ['Purchase Executive', 'DES_PUR_EX'],
            ['Procurement Officer', 'DES_PUR_PR'],
            ['Vendor Coordinator', 'DES_PUR_VC'],
        ],
        'DEPT_UNCLASS' => [
            ['General Staff', 'DES_UN_GEN'],
            ['To Be Assigned', 'DES_UN_TBA'],
        ],
        'DEPT_SALES' => [
            ['Sales Executive', 'DES_SAL_EX'],
            ['Marketing Coordinator', 'DES_SAL_MKT'],
            ['Key Account Manager', 'DES_SAL_KAM'],
        ],
        'DEPT_MGMT' => [
            ['Plant Manager', 'DES_MGT_PLANT'],
            ['Operations Manager', 'DES_MGT_OPS'],
            ['Supervisor', 'DES_MGT_SUP'],
            ['General Manager', 'DES_MGT_GM'],
        ],
    ];

    foreach ($designationSeed as $deptCode => $rows) {
        $did = $deptPk($pdo, $deptCode);
        if ($did < 1) {
            continue;
        }
        foreach ($rows as [$dname, $dcode]) {
            $insDes->execute(['did' => $did, 'name' => $dname, 'code' => $dcode, 'st' => 'active']);
        }
    }
}

function dh_migrate_legacy_employee_departments(PDO $pdo): void
{
    if (!dh_column_exists($pdo, 'employees', 'department_id')) {
        return;
    }

    // Force one collation when joining legacy free-text columns to master tables (avoids 1267 errors).
    $ci = 'utf8mb4_general_ci';

    $pdo->exec("UPDATE employees e
        INNER JOIN departments d ON LOWER(TRIM(e.department)) COLLATE {$ci} = LOWER(TRIM(d.department_name)) COLLATE {$ci}
        SET e.department_id = d.id
        WHERE e.department_id IS NULL");

    $pdo->exec("UPDATE employees e
        INNER JOIN departments d ON LOWER(TRIM(e.department)) COLLATE {$ci} = LOWER(TRIM(d.department_short_name)) COLLATE {$ci}
        SET e.department_id = d.id
        WHERE e.department_id IS NULL");

    $pdo->exec("UPDATE employees e
        INNER JOIN departments d ON LOWER(TRIM(e.department)) COLLATE {$ci} = LOWER(TRIM(d.department_code)) COLLATE {$ci}
        SET e.department_id = d.id
        WHERE e.department_id IS NULL");

    $legacyMap = [
        'hr' => 'DEPT_HR',
        'human resources' => 'DEPT_HR',
        'production' => 'DEPT_TIRE_BUILD',
        'quality' => 'DEPT_QA_QC',
        'qc' => 'DEPT_QA_QC',
        'qa' => 'DEPT_QA_QC',
        'maintenance' => 'DEPT_MAINT',
        'dispatch' => 'DEPT_LOG_DISP',
        'logistics' => 'DEPT_LOG_DISP',
        'warehouse' => 'DEPT_WH',
        'store' => 'DEPT_STORE',
        'accounts' => 'DEPT_ACC',
        'finance' => 'DEPT_ACC',
        'purchase' => 'DEPT_PUR',
        'procurement' => 'DEPT_PUR',
        'administration' => 'DEPT_ADMIN',
        'admin' => 'DEPT_ADMIN',
        'sales' => 'DEPT_SALES',
        'marketing' => 'DEPT_SALES',
        'management' => 'DEPT_MGMT',
        'mixing' => 'DEPT_MIXING',
        'curing' => 'DEPT_CURING',
        'ppc' => 'DEPT_PPC',
    ];

    $resolveDept = $pdo->prepare('SELECT id FROM departments WHERE department_code = ? LIMIT 1');
    foreach ($legacyMap as $needle => $deptCode) {
        $resolveDept->execute([$deptCode]);
        $did = (int)$resolveDept->fetchColumn();
        if ($did < 1) {
            continue;
        }
        $like = '%' . $needle . '%';
        $st = $pdo->prepare("UPDATE employees SET department_id = :did
            WHERE department_id IS NULL
            AND LOWER(TRIM(CAST(department AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci))
                LIKE CAST(:pat AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci");
        $st->execute(['did' => $did, 'pat' => $like]);
    }

    $unclass = (int)$pdo->query("SELECT id FROM departments WHERE department_code = 'DEPT_UNCLASS' LIMIT 1")->fetchColumn();
    if ($unclass > 0) {
        $pdo->prepare('UPDATE employees SET department_id = :u WHERE department_id IS NULL')->execute(['u' => $unclass]);
    }

    $pdo->exec("UPDATE employees e
        INNER JOIN designations des
            ON des.department_id = e.department_id
            AND LOWER(TRIM(des.designation_name)) COLLATE {$ci} = LOWER(TRIM(COALESCE(e.designation, ''))) COLLATE {$ci}
        SET e.designation_id = des.id
        WHERE e.designation_id IS NULL AND TRIM(COALESCE(e.designation, '')) != ''");
}

function dh_sync_all_employee_org_labels(PDO $pdo): void
{
    if (!dh_column_exists($pdo, 'employees', 'department_id')) {
        return;
    }
    $pdo->exec("UPDATE employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN designations des ON des.id = e.designation_id
        SET
            e.department = COALESCE(NULLIF(d.department_name, ''), NULLIF(e.department, ''), 'Unclassified'),
            e.designation = CASE
                WHEN e.designation_id IS NOT NULL THEN des.designation_name
                ELSE COALESCE(NULLIF(e.designation, ''), '')
            END");
}

function dh_sync_employee_org_labels(PDO $pdo, int $employeeId): void
{
    if (!dh_column_exists($pdo, 'employees', 'department_id') || $employeeId < 1) {
        return;
    }
    $st = $pdo->prepare("UPDATE employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN designations des ON des.id = e.designation_id
        SET
            e.department = COALESCE(NULLIF(d.department_name, ''), NULLIF(e.department, ''), 'Unclassified'),
            e.designation = CASE
                WHEN e.designation_id IS NOT NULL THEN des.designation_name
                ELSE COALESCE(NULLIF(e.designation, ''), '')
            END
        WHERE e.id = :id");
    $st->execute(['id' => $employeeId]);
}

function hr_department_filter_options(PDO $pdo): array
{
    if (!dh_table_exists($pdo, 'departments')) {
        return $pdo->query("SELECT DISTINCT TRIM(department) AS d FROM employees WHERE status = 'active' AND COALESCE(department,'') != '' ORDER BY d")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    $canonical = $pdo->query("SELECT department_name FROM departments WHERE status = 'active' ORDER BY department_name")
        ->fetchAll(PDO::FETCH_COLUMN);
    $legacy = $pdo->query("SELECT DISTINCT TRIM(e.department) AS d FROM employees e
        WHERE e.status = 'active' AND e.department_id IS NULL AND TRIM(COALESCE(e.department,'')) != '' ORDER BY d")
        ->fetchAll(PDO::FETCH_COLUMN);

    $merged = array_values(array_unique(array_filter(array_merge($canonical, $legacy), static fn ($v) => $v !== null && $v !== '')));
    sort($merged);
    return $merged;
}

function dh_validate_org_assignment(PDO $pdo, int $departmentId, int $designationId): bool
{
    if ($departmentId < 1) {
        return false;
    }
    $chk = $pdo->prepare('SELECT id FROM departments WHERE id = :id AND status = :st LIMIT 1');
    $chk->execute(['id' => $departmentId, 'st' => 'active']);
    if (!(int)$chk->fetchColumn()) {
        return false;
    }
    if ($designationId < 1) {
        return true;
    }
    $chk2 = $pdo->prepare('SELECT id FROM designations WHERE id = :id AND department_id = :did AND status = :st LIMIT 1');
    $chk2->execute(['id' => $designationId, 'did' => $departmentId, 'st' => 'active']);
    return (bool)(int)$chk2->fetchColumn();
}

function dh_fetch_department_row(PDO $pdo, int $departmentId): ?array
{
    if ($departmentId < 1) {
        return null;
    }
    $st = $pdo->prepare('SELECT d.*, c.category_name, c.id AS category_id FROM departments d
        INNER JOIN department_categories c ON c.id = d.category_id WHERE d.id = :id LIMIT 1');
    $st->execute(['id' => $departmentId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}
