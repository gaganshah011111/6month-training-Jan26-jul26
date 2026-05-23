<?php
declare(strict_types=1);

require_once __DIR__ . '/department_hierarchy.php';
require_once __DIR__ . '/machine_service.php';

const DEMO_SEED_MARKER = 'tyre_erp_demo_v1';

/** Run full tyre-factory demo dataset (idempotent unless $force). */
function demo_seed_run(PDO $pdo, bool $force = false): array
{
    mach_ensure_schema($pdo);
    install_department_hierarchy($pdo);

    if (!$force && demo_seed_already_applied($pdo)) {
        return ['skipped' => true, 'message' => 'Demo data already present (use --force to re-run).'];
    }

    if ($force) {
        demo_seed_clear($pdo);
    }

    $ids = [];
    $ids['employees'] = demo_seed_employees($pdo);
    $ids['machines'] = demo_seed_machines($pdo);
    demo_seed_machine_assignments($pdo, $ids['employees'], $ids['machines']);
    $ids['suppliers'] = demo_seed_suppliers($pdo);
    $ids['materials'] = demo_seed_materials($pdo, $ids['suppliers']);
    demo_seed_inventory_movements($pdo, $ids['materials']);
    demo_seed_finished_goods($pdo);
    demo_seed_production_entries($pdo, $ids['employees'], $ids['machines']);
    $ids['customers'] = demo_seed_customers($pdo);
    $ids['transport'] = demo_seed_logistics($pdo);
    demo_seed_dispatches($pdo, $ids['customers'], $ids['transport']);
    demo_seed_attendance($pdo, $ids['employees']);
    demo_seed_payroll_sample($pdo, $ids['employees']);

    demo_seed_mark_applied($pdo);

    return [
        'skipped' => false,
        'employees' => count($ids['employees']),
        'machines' => count($ids['machines']),
        'materials' => count($ids['materials']),
        'customers' => count($ids['customers']),
    ];
}

function demo_seed_already_applied(PDO $pdo): bool
{
    if (demo_seed_table_exists($pdo, 'settings')) {
        $st = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1');
        $st->execute(['k' => DEMO_SEED_MARKER]);
        if ($st->fetchColumn() === '1') {
            return true;
        }
    }
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE employee_code REGEXP '^EMP[0-9]{3}$'")->fetchColumn();

    return $cnt >= 10;
}

function demo_seed_mark_applied(PDO $pdo): void
{
    if (!demo_seed_table_exists($pdo, 'settings')) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute(['k' => DEMO_SEED_MARKER, 'v' => '1']);
}

function demo_seed_table_exists(PDO $pdo, string $table): bool
{
    return dh_table_exists($pdo, $table);
}

function demo_seed_clear(PDO $pdo): void
{
    $codes = array_map(static fn(int $n): string => sprintf('EMP%03d', $n), range(1, 15));
    $ph = implode(',', array_fill(0, count($codes), '?'));

    $empIds = $pdo->prepare("SELECT id FROM employees WHERE employee_code IN ({$ph})");
    $empIds->execute($codes);
    $eids = $empIds->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if ($eids !== []) {
        $eph = implode(',', array_fill(0, count($eids), '?'));
        $pdo->prepare("DELETE FROM machine_operator_assignments WHERE employee_id IN ({$eph})")->execute($eids);
        $pdo->prepare("DELETE FROM attendance WHERE employee_id IN ({$eph})")->execute($eids);
        $pdo->prepare("DELETE FROM salaries WHERE employee_id IN ({$eph})")->execute($eids);
        $pdo->prepare("DELETE FROM employees WHERE id IN ({$eph})")->execute($eids);
    }

    $pdo->exec("DELETE FROM dispatch WHERE dispatch_code LIKE 'DSP-2026%' OR invoice_no LIKE 'INV-2026%'");
    $pdo->exec("DELETE FROM mixing_entries WHERE remarks LIKE '%[demo]%'");
    $pdo->exec("DELETE FROM building_entries WHERE remarks LIKE '%[demo]%'");
    $pdo->exec("DELETE FROM curing_entries WHERE remarks LIKE '%[demo]%'");
    $pdo->exec("DELETE FROM stock_inward WHERE remarks LIKE '%[demo]%'");
    $pdo->exec("DELETE FROM stock_usage WHERE remarks LIKE '%[demo]%'");
    $pdo->exec("DELETE FROM inventory WHERE batch_ref LIKE 'DEMO-%'");
    $pdo->exec("DELETE FROM machines WHERE machine_code IN (
        'MC-101','MC-102','TB-201','TB-202','CL-301','CU-401','CU-402','TB-203','MC-103','CL-302'
    )");
    $pdo->exec("DELETE FROM raw_materials WHERE material_code LIKE 'RM-0%'");
    $pdo->exec("DELETE FROM suppliers WHERE name LIKE '%[demo]%'");
    $pdo->exec("DELETE FROM dispatch_customers WHERE customer_name LIKE '%[demo]%'");
    $pdo->exec("DELETE FROM dispatch_drivers WHERE license_number LIKE 'DL-DEMO-%'");
    $pdo->exec("DELETE FROM dispatch_vehicles WHERE vehicle_number LIKE 'DEMO-%'");
    $pdo->exec("DELETE FROM dispatch_transport_companies WHERE company_name LIKE '%[demo]%'");
    $pdo->prepare('DELETE FROM settings WHERE setting_key = :k')->execute(['k' => DEMO_SEED_MARKER]);
}

function demo_seed_dept_id(PDO $pdo, string $deptCode): int
{
    $st = $pdo->prepare('SELECT id, department_name FROM departments WHERE department_code = ? LIMIT 1');
    $st->execute([$deptCode]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException("Department not found: {$deptCode}");
    }

    return (int)$row['id'];
}

function demo_seed_desig_id(PDO $pdo, string $deptCode, string $desigCode): ?int
{
    $did = demo_seed_dept_id($pdo, $deptCode);
    $st = $pdo->prepare('SELECT id FROM designations WHERE department_id = ? AND designation_code = ? LIMIT 1');
    $st->execute([$did, $desigCode]);
    $id = (int)$st->fetchColumn();

    return $id > 0 ? $id : null;
}

function demo_seed_dept_name(PDO $pdo, int $deptId): string
{
    $st = $pdo->prepare('SELECT department_name FROM departments WHERE id = ?');
    $st->execute([$deptId]);

    return (string)$st->fetchColumn();
}

/** @return array<string, int> code => employee id */
function demo_seed_employees(PDO $pdo): array
{
    $rows = [
        ['EMP001', 'Ravi Kumar', 'Worker', 'DEPT_MIXING', 'DES_MIX_OP', 'Morning', 22000, '9876100001', '123456789001', 'active', 'Village Rampur, Ludhiana, Punjab'],
        ['EMP002', 'Neha Sharma', 'Staff', 'DEPT_MIXING', 'DES_MIX_SUP', 'Morning', 32000, '9876100002', '123456789002', 'active', 'Sector 22, Chandigarh'],
        ['EMP003', 'Amit Verma', 'Worker', 'DEPT_MIXING', 'DES_MIX_OP', 'Evening', 21500, '9876100003', '123456789003', 'active', 'Industrial Area, Jalandhar'],
        ['EMP004', 'Gagan Kumar Shah', 'Worker', 'DEPT_TIRE_BUILD', 'DES_TB_OP', 'Morning', 23000, '9876100004', '123456789004', 'active', 'GT Road, Amritsar'],
        ['EMP005', 'Suresh Patel', 'Worker', 'DEPT_TIRE_BUILD', 'DES_TB_OP', 'Evening', 22500, '9876100005', '123456789005', 'active', 'Naroda, Ahmedabad, Gujarat'],
        ['EMP006', 'Mohit Singh', 'Worker', 'DEPT_COMP_PREP', 'DES_CAL_OP', 'Night', 21800, '9876100006', '123456789006', 'active', 'Manesar, Gurgaon, Haryana'],
        ['EMP007', 'Rakesh Yadav', 'Worker', 'DEPT_CURING', 'DES_CUR_OP', 'Morning', 22400, '9876100007', '123456789007', 'active', 'Ballabhgarh, Faridabad'],
        ['EMP008', 'Deepak Kumar', 'Worker', 'DEPT_CURING', 'DES_CUR_TECH', 'Evening', 22800, '9876100008', '123456789008', 'active', 'Dadri, Greater Noida'],
        ['EMP009', 'Pooja Mehta', 'Staff', 'DEPT_WH', 'DES_WH_ASC', 'Morning', 26000, '9876100009', '123456789009', 'active', 'Warehouse Colony, Bhiwandi'],
        ['EMP010', 'Vikram Joshi', 'Staff', 'DEPT_LOG_DISP', 'DES_LOG_COORD', 'Morning', 28500, '9876100010', '123456789010', 'active', 'Transport Nagar, Delhi'],
        ['EMP011', 'Anjali Reddy', 'Staff', 'DEPT_HR', 'DES_HR_EX', 'Morning', 30000, '9876100011', '123456789011', 'active', 'MG Road, Bengaluru'],
        ['EMP012', 'Rajesh Gupta', 'Staff', 'DEPT_PPC', 'DES_PPC_EXEC', 'Morning', 31000, '9876100012', '123456789012', 'active', 'Panchkula, Haryana'],
        ['EMP013', 'Sunil Nambiar', 'Staff', 'DEPT_QA_QC', 'DES_QA_INSP', 'Morning', 27000, '9876100013', '123456789013', 'active', 'Peenya, Bengaluru'],
        ['EMP014', 'Kiran Desai', 'Worker', 'DEPT_MIXING', 'DES_MIX_OP', 'Night', 20000, '9876100014', '123456789014', 'inactive', 'Vadodara, Gujarat'],
        ['EMP015', 'Meena Iyer', 'Staff', 'DEPT_ADMIN', 'DES_AD_EX', 'Morning', 24000, '9876100015', '123456789015', 'active', 'Anna Nagar, Chennai'],
    ];

    $ins = $pdo->prepare(
        'INSERT INTO employees (
            employee_code, full_name, father_name, dob, aadhaar_number, email, employee_type,
            department, designation, department_id, designation_id, role, salary_type,
            shift_timing, shift_start, shift_end, contact_no, address, joining_date,
            basic_salary, gross_salary, paid_leave_limit, half_paid_leave_limit,
            hra_percentage, pf_applicable, esi_applicable, payroll_auto_indian, status
        ) VALUES (
            :code, :name, :fname, :dob, :aad, :email, :etype,
            :dept, :des, :did, :dsid, :role, :stype,
            :shift, :ss, :se, :phone, :addr, :join,
            :basic, :gross, 12, 6, 40, 1, 1, 1, :status
        )
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name), department_id = VALUES(department_id),
            designation_id = VALUES(designation_id), department = VALUES(department),
            designation = VALUES(designation), status = VALUES(status),
            shift_timing = VALUES(shift_timing), gross_salary = VALUES(gross_salary)'
    );

    $map = [];
    $joinBase = date('Y-m-d', strtotime('-18 months'));
    foreach ($rows as $i => $r) {
        [$code, $name, $etype, $deptCode, $desCode, $shift, $gross, $phone, $aad, $status, $addr] = $r;
        $deptId = demo_seed_dept_id($pdo, $deptCode);
        $desId = demo_seed_desig_id($pdo, $deptCode, $desCode);
        $deptName = demo_seed_dept_name($pdo, $deptId);
        $desName = '';
        if ($desId) {
            $dn = $pdo->prepare('SELECT designation_name FROM designations WHERE id = ?');
            $dn->execute([$desId]);
            $desName = (string)$dn->fetchColumn();
        }
        [$ss, $se] = match ($shift) {
            'Evening' => ['14:00:00', '22:00:00'],
            'Night' => ['22:00:00', '06:00:00'],
            default => ['09:00:00', '18:00:00'],
        };
        $basic = round($gross * 0.55, 2);
        $ins->execute([
            'code' => $code,
            'name' => $name,
            'fname' => 'S/O Demo Father',
            'dob' => date('Y-m-d', strtotime('1988-01-01 +' . $i . ' months')),
            'aad' => $aad,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@demo.tyreerp.local',
            'etype' => $etype,
            'dept' => $deptName,
            'des' => $desName,
            'did' => $deptId,
            'dsid' => $desId,
            'role' => 'Employee',
            'stype' => $etype === 'Worker' ? 'Monthly' : 'Monthly',
            'shift' => $shift . ' Shift',
            'ss' => $ss,
            'se' => $se,
            'phone' => $phone,
            'addr' => $addr,
            'join' => date('Y-m-d', strtotime($joinBase . ' +' . $i . ' weeks')),
            'basic' => $basic,
            'gross' => $gross,
            'status' => $status,
        ]);
        $st = $pdo->prepare('SELECT id FROM employees WHERE employee_code = ?');
        $st->execute([$code]);
        $map[$code] = (int)$st->fetchColumn();
    }

    return $map;
}

/** @return array<string, int> */
function demo_seed_machines(PDO $pdo): array
{
    $rows = [
        ['MC-101', 'Banbury Mixer 1', 'Mixing', 'Banbury', 'Active', 'Line A'],
        ['MC-102', 'Compound Mixer 2', 'Mixing', 'Internal Mixer', 'Active', 'Line A'],
        ['MC-103', 'Mixing Mill 3', 'Mixing', 'Two-Roll Mill', 'Idle', 'Line B'],
        ['TB-201', 'Tyre Building Machine 1', 'Building', 'Building Drum', 'Active', 'Building-1'],
        ['TB-202', 'Tyre Building Machine 2', 'Building', 'Building Drum', 'Active', 'Building-1'],
        ['TB-203', 'Tyre Building Machine 3', 'Building', 'Building Drum', 'Under Repair', 'Building-2'],
        ['CL-301', 'Calendar Line 1', 'Building', 'Calendering Line', 'Active', 'Calendering'],
        ['CL-302', 'Calendar Line 2', 'Building', 'Calendering Line', 'Idle', 'Calendering'],
        ['CU-401', 'Curing Press 1', 'Curing', 'Curing Press', 'Active', 'Curing Bay 1'],
        ['CU-402', 'Curing Press 2', 'Curing', 'Curing Press', 'Scrap / Deactivated', 'Curing Bay 2'],
    ];

    $map = [];
    foreach ($rows as [$code, $name, $dept, $type, $status, $section]) {
        $isActive = $status === 'Scrap / Deactivated' ? 0 : 1;
        $st = $pdo->prepare(
            'INSERT INTO machines (machine_code, machine_name, department, section, machine_type, status,
             shift_capacity, installation_date, last_maintenance_date, is_active, remarks)
             VALUES (:c, :n, :dep, :sec, :t, :s, 120, :inst, :maint, :ia, :rm)
             ON DUPLICATE KEY UPDATE machine_name = VALUES(machine_name), department = VALUES(department),
             section = VALUES(section), machine_type = VALUES(machine_type), status = VALUES(status),
             is_active = VALUES(is_active)'
        );
        $st->execute([
            'c' => $code,
            'n' => $name,
            'dep' => $dept,
            'sec' => $section,
            't' => $type,
            's' => $status,
            'inst' => '2022-03-15',
            'maint' => date('Y-m-d', strtotime('-45 days')),
            'ia' => $isActive,
            'rm' => 'Demo factory asset',
        ]);
        $idSt = $pdo->prepare('SELECT id FROM machines WHERE machine_code = ?');
        $idSt->execute([$code]);
        $map[$code] = (int)$idSt->fetchColumn();
    }

    return $map;
}

/** @param array<string, int> $employees @param array<string, int> $machines */
function demo_seed_machine_assignments(PDO $pdo, array $employees, array $machines): void
{
    $pairs = [
        ['MC-101', 'EMP001', 'Mixing', 'Morning'],
        ['MC-102', 'EMP003', 'Mixing', 'Evening'],
        ['TB-201', 'EMP004', 'Building', 'Morning'],
        ['TB-202', 'EMP005', 'Building', 'Evening'],
        ['CL-301', 'EMP006', 'Building', 'Night'],
        ['CU-401', 'EMP007', 'Curing', 'Morning'],
    ];
    $from = date('Y-m-d', strtotime('-90 days'));

    foreach ($pairs as [$mCode, $eCode, $dept, $shift]) {
        if (!isset($machines[$mCode], $employees[$eCode])) {
            continue;
        }
        $exists = $pdo->prepare(
            'SELECT id FROM machine_operator_assignments WHERE machine_id = ? AND employee_id = ? AND is_active = 1 LIMIT 1'
        );
        $exists->execute([$machines[$mCode], $employees[$eCode]]);
        if ($exists->fetchColumn()) {
            continue;
        }
        mach_close_active_assignments_for_machine($pdo, $machines[$mCode], 'Demo seed reassignment');
        $pdo->prepare(
            'INSERT INTO machine_operator_assignments (machine_id, employee_id, department, shift, assigned_from, is_active, remarks)
             VALUES (:mid, :eid, :dep, :sh, :af, 1, :rm)'
        )->execute([
            'mid' => $machines[$mCode],
            'eid' => $employees[$eCode],
            'dep' => $dept,
            'sh' => $shift,
            'af' => $from,
            'rm' => 'Initial demo assignment',
        ]);
    }

    $pdo->prepare(
        'INSERT INTO machine_operator_assignments (machine_id, employee_id, department, shift, assigned_from, assigned_till, is_active, closed_reason, remarks)
         SELECT :mid, :eid, :dep, :sh, :af, :at, 0, :cr, :rm FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM machine_operator_assignments WHERE machine_id = :mid2 AND assigned_from = :af2)'
    )->execute([
        'mid' => $machines['MC-101'] ?? 0,
        'eid' => $employees['EMP014'] ?? 0,
        'dep' => 'Mixing',
        'sh' => 'Night',
        'af' => date('Y-m-d', strtotime('-120 days')),
        'at' => date('Y-m-d', strtotime('-95 days')),
        'cr' => 'Employee left shift',
        'rm' => 'Historical demo assignment',
        'mid2' => $machines['MC-101'] ?? 0,
        'af2' => date('Y-m-d', strtotime('-120 days')),
    ]);
}

/** @return array<string, int> */
function demo_seed_suppliers(PDO $pdo): array
{
    $names = [
        'Punjab Rubber Corp [demo]',
        'ChemTech Industries [demo]',
        'Bharat Chemicals [demo]',
        'Metro Industrial Supply [demo]',
        'Gujarat Carbon Works [demo]',
        'Southern Cord Suppliers [demo]',
        'Himalaya Elastomers [demo]',
        'Steel Wire India [demo]',
        'PackPro Logistics Materials [demo]',
        'National Tyre Chemicals [demo]',
    ];
    $map = [];
    $hasSupStatus = dh_column_exists($pdo, 'suppliers', 'status');
    $hasSupMat = dh_column_exists($pdo, 'suppliers', 'materials_supplied');
    foreach ($names as $i => $name) {
        $params = [
            'n' => $name,
            'cp' => 'Contact ' . ($i + 1),
            'ph' => '9812300' . str_pad((string)($i + 100), 3, '0', STR_PAD_LEFT),
            'em' => 'vendor' . ($i + 1) . '@demo.supply',
            'ad' => 'Industrial Estate, Plot ' . ($i + 10) . ', India',
        ];
        if ($hasSupStatus && $hasSupMat) {
            $pdo->prepare(
                'INSERT INTO suppliers (name, contact_person, phone, email, address, status, materials_supplied)
                 VALUES (:n, :cp, :ph, :em, :ad, :st, :ms)'
            )->execute($params + ['st' => 'Active', 'ms' => 'Rubber, chemicals, reinforcement']);
        } else {
            $pdo->prepare(
                'INSERT INTO suppliers (name, contact_person, phone, email, address)
                 VALUES (:n, :cp, :ph, :em, :ad)'
            )->execute($params);
        }
        $st = $pdo->prepare('SELECT id FROM suppliers WHERE name = ?');
        $st->execute([$name]);
        $map[$name] = (int)$st->fetchColumn();
    }

    return $map;
}

/** @return array<string, int> material_code => id */
function demo_seed_materials(PDO $pdo, array $suppliers): array
{
    $supplierIds = array_values($suppliers);
    $materials = [
        ['RM-001', 'Natural Rubber', 'Rubber', 'kg', 4200, 800, 12000],
        ['RM-002', 'Carbon Black', 'Fillers', 'kg', 3100, 500, 10000],
        ['RM-003', 'Sulphur', 'Chemicals', 'kg', 650, 100, 2000],
        ['RM-004', 'Nylon Cord', 'Reinforcement', 'kg', 1800, 300, 5000],
        ['RM-005', 'Steel Wire', 'Reinforcement', 'kg', 2200, 400, 6000],
        ['RM-006', 'Fabric Layer', 'Reinforcement', 'm', 950, 150, 3000],
        ['RM-007', 'Synthetic Rubber', 'Rubber', 'kg', 1500, 250, 5000],
        ['RM-008', 'Antioxidant MB', 'Chemicals', 'kg', 280, 50, 1000],
        ['RM-009', 'Zinc Oxide', 'Chemicals', 'kg', 420, 80, 1500],
        ['RM-010', 'Bead Wire', 'Reinforcement', 'kg', 1100, 200, 4000],
        ['RM-011', 'Inner Liner Compound', 'Rubber', 'kg', 900, 180, 3500],
        ['RM-012', 'Tread Compound', 'Rubber', 'kg', 1300, 220, 4500],
        ['RM-013', 'Solvent Oil', 'Chemicals', 'L', 350, 60, 1200],
        ['RM-014', 'Packing Carton', 'Packaging', 'piece', 8000, 1000, 20000],
        ['RM-015', 'Process Oil', 'Chemicals', 'L', 90, 120, 2000],
    ];

    $map = [];
    $hasCode = dh_column_exists($pdo, 'raw_materials', 'material_code');
    $hasCat = dh_column_exists($pdo, 'raw_materials', 'category');

    foreach ($materials as $i => $m) {
        [$code, $name, $cat, $unit, $stock, $reorder, $max] = $m;
        $sid = $supplierIds[$i % count($supplierIds)] ?? null;
        if ($hasCode && $hasCat) {
            $sql = 'INSERT INTO raw_materials (material_code, material_name, category, unit, stock_qty, reorder_level, max_stock_level, supplier_id, storage_location, status)
                VALUES (:c,:n,:cat,:u,:sq,:rl,:mx,:sid,:loc,:st)
                ON DUPLICATE KEY UPDATE stock_qty = VALUES(stock_qty), reorder_level = VALUES(reorder_level)';
            $pdo->prepare($sql)->execute([
                'c' => $code, 'n' => $name, 'cat' => $cat, 'u' => $unit,
                'sq' => $stock, 'rl' => $reorder, 'mx' => $max,
                'sid' => $sid, 'loc' => 'Store-' . chr(65 + ($i % 4)), 'st' => 'Active',
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO raw_materials (material_name, unit, stock_qty, reorder_level, supplier_id)
                 VALUES (:n,:u,:sq,:rl,:sid)
                 ON DUPLICATE KEY UPDATE stock_qty = VALUES(stock_qty)'
            )->execute(['n' => $name, 'u' => $unit, 'sq' => $stock, 'rl' => $reorder, 'sid' => $sid]);
        }
        if ($hasCode) {
            $st = $pdo->prepare('SELECT id FROM raw_materials WHERE material_code = ?');
            $st->execute([$code]);
        } else {
            $st = $pdo->prepare('SELECT id FROM raw_materials WHERE material_name = ?');
            $st->execute([$name]);
        }
        $map[$code] = (int)$st->fetchColumn();
    }

    return $map;
}

/** @param array<string, int> $materials */
function demo_seed_inventory_movements(PDO $pdo, array $materials): void
{
    if (!demo_seed_table_exists($pdo, 'stock_inward')) {
        return;
    }

    $codes = array_keys($materials);
    $supId = (int)$pdo->query('SELECT id FROM suppliers LIMIT 1')->fetchColumn();

    for ($i = 0; $i < 10; $i++) {
        $mid = $materials[$codes[$i % count($codes)]] ?? null;
        if (!$mid) {
            continue;
        }
        $dt = date('Y-m-d', strtotime('-' . (20 - $i) . ' days'));
        $pdo->prepare(
            'INSERT INTO stock_inward (inward_date, supplier_id, invoice_no, material_id, batch_no, quantity, rate, received_by, remarks)
             VALUES (:d, :sid, :inv, :mid, :bn, :q, :r, :rb, :rm)'
        )->execute([
            'd' => $dt,
            'sid' => $supId ?: null,
            'inv' => 'PINV-2026-' . str_pad((string)(1000 + $i), 4, '0', STR_PAD_LEFT),
            'mid' => $mid,
            'bn' => 'BIN-DEMO-' . ($i + 1),
            'q' => 200 + ($i * 35),
            'r' => 85 + $i,
            'rb' => 'Pooja Mehta',
            'rm' => 'Demo inward [demo]',
        ]);
        $pdo->prepare('UPDATE raw_materials SET stock_qty = stock_qty + :q WHERE id = :id')->execute([
            'q' => 200 + ($i * 35),
            'id' => $mid,
        ]);
    }

    if (!demo_seed_table_exists($pdo, 'stock_usage')) {
        return;
    }

    $depts = ['Mixing', 'Building', 'Curing'];
    for ($i = 0; $i < 10; $i++) {
        $mid = $materials[$codes[($i + 3) % count($codes)]] ?? null;
        if (!$mid) {
            continue;
        }
        $qty = 40 + ($i * 8);
        $pdo->prepare(
            'INSERT INTO stock_usage (usage_date, material_id, quantity, usage_type, department, usage_reason, created_by, remarks)
             VALUES (:d, :mid, :q, :ut, :dep, :ur, :cb, :rm)'
        )->execute([
            'd' => date('Y-m-d', strtotime('-' . (12 - $i) . ' days')),
            'mid' => $mid,
            'q' => $qty,
            'ut' => $i % 2 === 0 ? 'production' : 'manual',
            'dep' => $depts[$i % 3],
            'ur' => 'Production consumption',
            'cb' => 'System',
            'rm' => 'Demo usage [demo]',
        ]);
        $pdo->prepare('UPDATE raw_materials SET stock_qty = GREATEST(0, stock_qty - :q) WHERE id = :id')->execute([
            'q' => $qty,
            'id' => $mid,
        ]);
    }

    if (isset($materials['RM-015'])) {
        $pdo->prepare('UPDATE raw_materials SET stock_qty = 85, reorder_level = 120 WHERE id = ?')
            ->execute([$materials['RM-015']]);
    }
}

function demo_seed_finished_goods(PDO $pdo): void
{
    $tyres = [
        ['PCR Car', 420],
        ['PCR SUV', 380],
        ['TBR Truck', 260],
        ['Farm / OTR', 140],
        ['Two Wheeler', 510],
    ];
    $hasCat = dh_column_exists($pdo, 'inventory', 'stock_category');
    foreach ($tyres as $i => [$name, $qty]) {
        $batch = 'DEMO-FG-' . strtoupper(str_replace([' ', '/'], '-', $name));
        if ($hasCat) {
            $pdo->prepare(
                'INSERT INTO inventory (product_name, batch_ref, qty, reorder_level, warehouse_location, stock_category)
                 VALUES (:n, :b, :q, 50, :w, :sc)
                 ON DUPLICATE KEY UPDATE qty = VALUES(qty)'
            )->execute(['n' => $name, 'b' => $batch, 'q' => $qty, 'w' => 'FG-A1', 'sc' => 'dispatch_ready']);
        } else {
            $pdo->prepare(
                'INSERT INTO inventory (product_name, batch_ref, qty, reorder_level, warehouse_location)
                 VALUES (:n, :b, :q, 50, :w)
                 ON DUPLICATE KEY UPDATE qty = VALUES(qty)'
            )->execute(['n' => $name, 'b' => $batch, 'q' => $qty, 'w' => 'FG-A1']);
        }
    }
}

/** @param array<string, int> $employees @param array<string, int> $machines */
function demo_seed_production_entries(PDO $pdo, array $employees, array $machines): void
{
    $tyres = ['PCR Car', 'PCR SUV', 'TBR Truck', 'Farm / OTR', 'Two Wheeler'];
    $shifts = ['Morning', 'Evening', 'Night'];

    $mixOps = [$employees['EMP001'] ?? 0, $employees['EMP003'] ?? 0, $employees['EMP002'] ?? 0];
    $bldOps = [$employees['EMP004'] ?? 0, $employees['EMP005'] ?? 0, $employees['EMP006'] ?? 0];
    $curOps = [$employees['EMP007'] ?? 0, $employees['EMP008'] ?? 0];

    $mixMachines = [$machines['MC-101'] ?? 0, $machines['MC-102'] ?? 0];
    $bldMachines = [$machines['TB-201'] ?? 0, $machines['TB-202'] ?? 0, $machines['CL-301'] ?? 0];
    $curMachines = [$machines['CU-401'] ?? 0];

    for ($i = 0; $i < 12; $i++) {
        $dt = date('Y-m-d', strtotime('-' . $i . ' days'));
        $pdo->prepare(
            'INSERT INTO mixing_entries (production_date, shift, machine_id, operator_id, tyre_type, produced_qty, rejected_qty, remarks)
             VALUES (:d, :sh, :mid, :oid, :tt, :pq, :rq, :rm)'
        )->execute([
            'd' => $dt,
            'sh' => $shifts[$i % 3],
            'mid' => $mixMachines[$i % count($mixMachines)] ?: null,
            'oid' => $mixOps[$i % count($mixOps)] ?: null,
            'tt' => $tyres[$i % count($tyres)],
            'pq' => 820 + ($i * 12),
            'rq' => $i % 4 === 0 ? 15.5 : 0,
            'rm' => 'Demo mixing batch [demo]',
        ]);
    }

    for ($i = 0; $i < 12; $i++) {
        $dt = date('Y-m-d', strtotime('-' . $i . ' days'));
        $pdo->prepare(
            'INSERT INTO building_entries (production_date, shift, machine_id, operator_id, tyre_type, produced_qty, rejected_qty, remarks)
             VALUES (:d, :sh, :mid, :oid, :tt, :pq, :rq, :rm)'
        )->execute([
            'd' => $dt,
            'sh' => $shifts[($i + 1) % 3],
            'mid' => $bldMachines[$i % count($bldMachines)] ?: null,
            'oid' => $bldOps[$i % count($bldOps)] ?: null,
            'tt' => $tyres[$i % count($tyres)],
            'pq' => 180 + ($i * 6),
            'rq' => $i % 5 === 0 ? 4 : 0,
            'rm' => 'Demo building run [demo]',
        ]);
    }

    for ($i = 0; $i < 12; $i++) {
        $dt = date('Y-m-d', strtotime('-' . $i . ' days'));
        $pdo->prepare(
            'INSERT INTO curing_entries (production_date, shift, machine_id, operator_id, tyre_type, produced_qty, rejected_qty, downtime_minutes, remarks)
             VALUES (:d, :sh, :mid, :oid, :tt, :pq, :rq, :dm, :rm)'
        )->execute([
            'd' => $dt,
            'sh' => $shifts[$i % 3],
            'mid' => $curMachines[$i % count($curMachines)] ?: null,
            'oid' => $curOps[$i % count($curOps)] ?: null,
            'tt' => $tyres[$i % count($tyres)],
            'pq' => 175 + ($i * 5),
            'rq' => $i % 6 === 0 ? 3 : 0,
            'dm' => $i % 3 === 0 ? 25 : 0,
            'rm' => 'Demo curing batch [demo]',
        ]);
    }

    if (demo_seed_table_exists($pdo, 'qc_entries')) {
        for ($i = 0; $i < 8; $i++) {
            $pdo->prepare(
                'INSERT INTO qc_entries (entry_date, shift, inspector_name, tyre_type, checked_qty, passed_qty, failed_qty, defect_type, remarks)
                 VALUES (:d, :sh, :insp, :tt, :chk, :pass, :fail, :def, :rm)'
            )->execute([
                'd' => date('Y-m-d', strtotime('-' . $i . ' days')),
                'sh' => 'Morning',
                'insp' => 'Sunil Nambiar',
                'tt' => $tyres[$i % count($tyres)],
                'chk' => 200,
                'pass' => 192 - ($i % 3),
                'fail' => 8 + ($i % 3),
                'def' => $i % 3 === 0 ? 'Sidewall blemish' : null,
                'rm' => 'Demo QC log [demo]',
            ]);
        }
    }
}

/** @return array<string, int> */
function demo_seed_customers(PDO $pdo): array
{
    if (!demo_seed_table_exists($pdo, 'dispatch_customers')) {
        return [];
    }
    $rows = [
        ['Metro Tyre Traders [demo]', 'Metro Tyre Traders Pvt Ltd', 'Mumbai', 'Maharashtra'],
        ['Southern Auto Mart [demo]', 'Southern Auto Mart', 'Chennai', 'Tamil Nadu'],
        ['Bharat Wheels [demo]', 'Bharat Wheels LLP', 'Pune', 'Maharashtra'],
        ['Punjab Tyres Distributor [demo]', 'Punjab Tyres Distributor', 'Ludhiana', 'Punjab'],
        ['North Highway Fleet [demo]', 'North Highway Fleet', 'Delhi', 'Delhi'],
        ['Western Rubber House [demo]', 'Western Rubber House', 'Ahmedabad', 'Gujarat'],
        ['Eastern Tyre Hub [demo]', 'Eastern Tyre Hub', 'Kolkata', 'West Bengal'],
        ['Deccan Auto Agencies [demo]', 'Deccan Auto Agencies', 'Hyderabad', 'Telangana'],
        ['Kerala Tyre Mart [demo]', 'Kerala Tyre Mart', 'Kochi', 'Kerala'],
        ['Rajasthan Wheel Centre [demo]', 'Rajasthan Wheel Centre', 'Jaipur', 'Rajasthan'],
    ];
    $map = [];
    $ins = $pdo->prepare(
        'INSERT INTO dispatch_customers (customer_name, company, phone, city, state, status)
         VALUES (:n, :co, :ph, :ci, :st, :active)
         ON DUPLICATE KEY UPDATE company = VALUES(company)'
    );
    foreach ($rows as $i => $r) {
        $ins->execute([
            'n' => $r[0], 'co' => $r[1], 'ph' => '989910' . str_pad((string)(1000 + $i), 4, '0', STR_PAD_LEFT),
            'ci' => $r[2], 'st' => $r[3], 'active' => 'Active',
        ]);
        $st = $pdo->prepare('SELECT id FROM dispatch_customers WHERE customer_name = ?');
        $st->execute([$r[0]]);
        $map[$r[0]] = (int)$st->fetchColumn();
    }

    return $map;
}

/** @return array{transport: array<string, int>, drivers: array<string, int>, vehicles: array<string, int>} */
function demo_seed_logistics(PDO $pdo): array
{
    $transport = [];
    $drivers = [];
    $vehicles = [];

    if (!demo_seed_table_exists($pdo, 'dispatch_transport_companies')) {
        return ['transport' => $transport, 'drivers' => $drivers, 'vehicles' => $vehicles];
    }

    $tcos = [
        'North Star Transport [demo]',
        'Punjab Logistics Express [demo]',
        'Western Freight Lines [demo]',
        'Southern Hauliers [demo]',
        'Bharat Road Carriers [demo]',
    ];
    $insT = $pdo->prepare(
        'INSERT INTO dispatch_transport_companies (company_name, contact_person, phone, gst_number, address, status)
         VALUES (:n, :cp, :ph, :gst, :ad, :st)
         ON DUPLICATE KEY UPDATE contact_person = VALUES(contact_person)'
    );
    foreach ($tcos as $i => $name) {
        $insT->execute([
            'n' => $name,
            'cp' => 'Logistics Head ' . ($i + 1),
            'ph' => '9812400' . str_pad((string)(200 + $i), 3, '0', STR_PAD_LEFT),
            'gst' => '29AABCT' . str_pad((string)(1000 + $i), 4, '0', STR_PAD_LEFT) . 'Z1',
            'ad' => 'Transport Nagar, India',
            'st' => 'Active',
        ]);
        $st = $pdo->prepare('SELECT id FROM dispatch_transport_companies WHERE company_name = ?');
        $st->execute([$name]);
        $transport[$name] = (int)$st->fetchColumn();
    }

    $driverRows = [
        ['Mohit Singh', 'DL-DEMO-0001', 'DEMO-MH12AB1234', 'North Star Transport [demo]'],
        ['Suresh Patel', 'DL-DEMO-0002', 'DEMO-GJ01BT5678', 'Western Freight Lines [demo]'],
        ['Ravi Kumar', 'DL-DEMO-0003', 'DEMO-PB10AX9876', 'Punjab Logistics Express [demo]'],
        ['Ajay Thakur', 'DL-DEMO-0004', 'DEMO-HR26CD4521', 'North Star Transport [demo]'],
        ['Vikram Joshi', 'DL-DEMO-0005', 'DEMO-DL01EF3344', 'Bharat Road Carriers [demo]'],
        ['Deepak Kumar', 'DL-DEMO-0006', 'DEMO-UP32GH7788', 'Southern Hauliers [demo]'],
        ['Sanjay Mehta', 'DL-DEMO-0007', 'DEMO-RJ14JK2233', 'Western Freight Lines [demo]'],
        ['Harpreet Singh', 'DL-DEMO-0008', 'DEMO-PB65LM8899', 'Punjab Logistics Express [demo]'],
        ['Karan Malhotra', 'DL-DEMO-0009', 'DEMO-CH01NO5566', 'Bharat Road Carriers [demo]'],
        ['Rohit Sharma', 'DL-DEMO-0010', 'DEMO-MP09PQ1122', 'Southern Hauliers [demo]'],
    ];

    $insD = $pdo->prepare(
        'INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_no, transport_company_id, transport_company, address, status)
         VALUES (:n, :ph, :lic, :veh, :tid, :tn, :ad, :st)
         ON DUPLICATE KEY UPDATE phone = VALUES(phone)'
    );
    foreach ($driverRows as $i => $r) {
        $tid = $transport[$r[3]] ?? null;
        $insD->execute([
            'n' => $r[0],
            'ph' => '9812500' . str_pad((string)(300 + $i), 3, '0', STR_PAD_LEFT),
            'lic' => $r[1],
            'veh' => $r[2],
            'tid' => $tid,
            'tn' => $r[3],
            'ad' => 'Driver quarters, plant township',
            'st' => 'Active',
        ]);
        $st = $pdo->prepare('SELECT id FROM dispatch_drivers WHERE license_number = ?');
        $st->execute([$r[1]]);
        $drivers[$r[0]] = (int)$st->fetchColumn();
    }

    if (demo_seed_table_exists($pdo, 'dispatch_vehicles')) {
        foreach ($driverRows as $r) {
            $vehNo = $r[2];
            $tid = $transport[$r[3]] ?? null;
            $did = $drivers[$r[0]] ?? null;
            $pdo->prepare(
                'INSERT IGNORE INTO dispatch_vehicles (vehicle_number, vehicle_type, capacity, driver_id, transport_company_id, status)
                 VALUES (:vn, :vt, :cap, :did, :tid, :st)'
            )->execute([
                'vn' => $vehNo,
                'vt' => 'Truck',
                'cap' => '40 tyres',
                'did' => $did,
                'tid' => $tid,
                'st' => 'Active',
            ]);
            $vehicles[$vehNo] = (int)$pdo->query("SELECT id FROM dispatch_vehicles WHERE vehicle_number = " . $pdo->quote($vehNo))->fetchColumn();
        }
    }

    return ['transport' => $transport, 'drivers' => $drivers, 'vehicles' => $vehicles];
}

/** @param array<string, int> $customers @param array{transport: array, drivers: array, vehicles: array} $logistics */
function demo_seed_dispatches(PDO $pdo, array $customers, array $logistics): void
{
    if ($customers === [] || !demo_seed_table_exists($pdo, 'dispatch')) {
        return;
    }

    $custList = array_values($customers);
    $driverIds = array_values($logistics['drivers']);
    $driverNames = array_keys($logistics['drivers']);
    $tyres = ['PCR Car', 'PCR SUV', 'TBR Truck', 'Farm / OTR', 'Two Wheeler'];

    for ($i = 0; $i < 12; $i++) {
        $dt = date('Y-m-d', strtotime('-' . $i . ' days'));
        $code = 'DSP-' . str_replace('-', '', $dt) . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
        $inv = 'INV-' . str_replace('-', '', $dt) . '-' . str_pad((string)(7862 + $i), 4, '0', STR_PAD_LEFT);
        $ord = 'ORD-DEMO-' . str_pad((string)(10000 + $i), 5, '0', STR_PAD_LEFT);

        $dup = $pdo->prepare('SELECT 1 FROM dispatch WHERE dispatch_code = :c OR invoice_no = :i LIMIT 1');
        $dup->execute(['c' => $code, 'i' => $inv]);
        if ($dup->fetchColumn()) {
            continue;
        }

        $cid = $custList[$i % count($custList)];
        $cst = $pdo->prepare('SELECT customer_name FROM dispatch_customers WHERE id = ?');
        $cst->execute([$cid]);
        $cname = (string)$cst->fetchColumn();
        $tt = $tyres[$i % count($tyres)];
        $qty = 20 + ($i * 3);
        $gw = 1200 + ($i * 40);
        $tw = 420 + ($i * 2);
        $nw = $gw - $tw;
        $did = $driverIds[$i % count($driverIds)] ?? null;
        $dname = $driverNames[$i % count($driverNames)] ?? 'Mohit Singh';
        $tid = (int)$pdo->query('SELECT transport_company_id FROM dispatch_drivers WHERE id = ' . (int)$did)->fetchColumn();
        $tname = (string)$pdo->query('SELECT transport_company FROM dispatch_drivers WHERE id = ' . (int)$did)->fetchColumn();
        $veh = (string)$pdo->query('SELECT vehicle_no FROM dispatch_drivers WHERE id = ' . (int)$did)->fetchColumn();

        $pdo->prepare(
            'UPDATE inventory SET qty = GREATEST(qty, :need) WHERE LOWER(product_name) = LOWER(:tt) LIMIT 1'
        )->execute(['need' => $qty + 50, 'tt' => $tt]);

        $pdo->prepare(
            'UPDATE inventory SET qty = qty - :deduct WHERE LOWER(product_name) = LOWER(:tt) AND qty >= :need ORDER BY id LIMIT 1'
        )->execute(['deduct' => $qty, 'tt' => $tt, 'need' => $qty]);

        $pdo->prepare(
            'INSERT INTO dispatch (
                dispatch_code, order_no, customer_id, customer_name, tyre_type, invoice_no,
                vehicle_no, driver_id, driver_name, transport_company_id, transport_company,
                dispatch_date, qty, gross_weight_kg, tare_weight_kg, net_weight_kg, remarks,
                status, stock_deducted
            ) VALUES (
                :code, :ord, :cid, :cname, :tt, :inv, :veh, :did, :dname, :tid, :tname,
                :dt, :qty, :gw, :tw, :nw, :rm, :st, 1
            )'
        )->execute([
            'code' => $code,
            'ord' => $ord,
            'cid' => $cid,
            'cname' => $cname,
            'tt' => $tt,
            'inv' => $inv,
            'veh' => $veh,
            'did' => $did,
            'dname' => $dname,
            'tid' => $tid ?: null,
            'tname' => $tname,
            'dt' => $dt,
            'qty' => $qty,
            'gw' => $gw,
            'tw' => $tw,
            'nw' => $nw,
            'rm' => 'Demo dispatch [demo]',
            'st' => 'Delivered',
        ]);
    }
}

/** @param array<string, int> $employees */
function demo_seed_attendance(PDO $pdo, array $employees): void
{
    if (!demo_seed_table_exists($pdo, 'attendance')) {
        return;
    }
    $codes = ['EMP001', 'EMP002', 'EMP003', 'EMP004', 'EMP005', 'EMP007', 'EMP009', 'EMP010'];
    $shifts = ['Morning', 'Evening', 'Night'];
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO attendance (employee_id, attendance_date, shift, status, total_hours, overtime_hours)
         VALUES (:eid, :d, :sh, :st, :th, :ot)'
    );
    for ($day = 1; $day <= 14; $day++) {
        $dt = date('Y-m-d', strtotime('-' . $day . ' days'));
        foreach ($codes as $j => $code) {
            $eid = $employees[$code] ?? 0;
            if ($eid < 1) {
                continue;
            }
            $status = ($day === 7 && $j === 2) ? 'Absent' : 'Present';
            $ins->execute([
                'eid' => $eid,
                'd' => $dt,
                'sh' => $shifts[$j % 3],
                'st' => $status,
                'th' => $status === 'Present' ? 8.5 : 0,
                'ot' => ($status === 'Present' && $j === 0 && $day < 5) ? 1.5 : 0,
            ]);
        }
    }
}

/** @param array<string, int> $employees */
function demo_seed_payroll_sample(PDO $pdo, array $employees): void
{
    if (!demo_seed_table_exists($pdo, 'salaries')) {
        return;
    }
    $month = date('Y-m');
    $ins = $pdo->prepare(
        'INSERT INTO salaries (employee_id, month_year, present_days, gross_salary, total_deduction, net_salary, payment_status, is_draft)
         VALUES (:eid, :m, :pd, :g, :td, :n, :ps, 0)
         ON DUPLICATE KEY UPDATE gross_salary = VALUES(gross_salary), net_salary = VALUES(net_salary)'
    );
    foreach (['EMP001', 'EMP002', 'EMP004', 'EMP007', 'EMP009', 'EMP010', 'EMP011'] as $code) {
        $eid = $employees[$code] ?? 0;
        if ($eid < 1) {
            continue;
        }
        $g = (float)$pdo->query('SELECT gross_salary FROM employees WHERE id = ' . $eid)->fetchColumn();
        $ded = round($g * 0.12, 2);
        $ins->execute([
            'eid' => $eid,
            'm' => $month,
            'pd' => 24,
            'g' => $g,
            'td' => $ded,
            'n' => $g - $ded,
            'ps' => 'paid',
        ]);
    }
}
