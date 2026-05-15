<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/department_hierarchy.php';
require_once __DIR__ . '/../../includes/employee_credentials.php';
require_once __DIR__ . '/../../includes/payroll_logic.php';
require_once __DIR__ . '/../../includes/indian_payroll.php';
if (!has_role(['Super Admin', 'HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update') {
        $empId = post_int('id');
        $deptId = post_int('department_id');
        $desId = post_int('designation_id');
        if (!dh_validate_org_assignment($pdo, $deptId, $desId)) {
            set_flash('danger', 'Please select a valid department (and designation, if chosen).');
            redirect('employees/list');
        }
        $father = post_string('father_name', 120);
        if ($err = ec_validate_father_name($father)) {
            set_flash('danger', $err);
            redirect('employees/list');
        }
        $dob = (string)($_POST['dob'] ?? '');
        if ($err = ec_validate_dob($dob)) {
            set_flash('danger', $err);
            redirect('employees/list');
        }
        $aadhaarRaw = preg_replace('/\D/', '', (string)($_POST['aadhaar_number'] ?? '')) ?? '';
        if ($err = ec_validate_aadhaar($aadhaarRaw, $pdo, $empId)) {
            set_flash('danger', $err);
            redirect('employees/list');
        }

        $payrollAuto = isset($_POST['payroll_auto_indian']);
        $metro = isset($_POST['metro']) ? 1 : 0;
        $salaryMerge = [
            'basic_salary' => post_float('basic_salary'),
            'dearness_allowance' => post_float('dearness_allowance'),
            'hra_percentage' => post_float('hra_percentage'),
            'hra_amount' => post_float('hra_amount'),
            'medical_allowance' => post_float('medical_allowance'),
            'travel_allowance' => post_float('travel_allowance'),
            'special_allowance' => post_float('special_allowance'),
            'other_allowances' => post_float('other_allowances'),
        ];
        if ($payrollAuto) {
            $grossComputed = max(0.0, round(post_float('gross_salary'), 2));
        } else {
            $grossComputed = employee_fixed_gross_monthly(array_merge($salaryMerge, ['payroll_auto_indian' => 0, 'gross_salary' => 0.0]));
        }

        $stmt = $pdo->prepare('UPDATE employees SET full_name=:n, father_name=:fn, dob=:dob, aadhaar_number=:aad, employee_type=:et, department_id=:did, designation_id=:dsid, role=:r, salary_type=:st, shift_timing=:sh, shift_start=:ss, shift_end=:se, contact_no=:p, address=:a, joining_date=:j, basic_salary=:s, paid_leave_limit=:pll, half_paid_leave_limit=:hpll, hra_percentage=:hrp, hra_amount=:hra, pf_applicable=:pfa, pf_percentage=:pfp, esi_applicable=:esia, esi_percentage=:esip, medical_allowance=:ma, special_allowance=:sa, other_allowances=:oa, metro=:metro, payroll_auto_indian=:pai, dearness_allowance=:da, travel_allowance=:ta, gratuity_monthly=:gr, gross_salary=:gs, overtime_rate=:otr, daily_wage=:dw, hourly_rate=:hr, status=:status WHERE id=:id');
        $stmt->execute([
            'id' => $empId,
            'n' => post_string('full_name', 150),
            'fn' => $father,
            'dob' => $dob,
            'aad' => $aadhaarRaw,
            'et' => post_string('employee_type', 20) ?: 'Staff',
            'did' => $deptId,
            'dsid' => $desId > 0 ? $desId : null,
            'r' => post_string('role', 100),
            'st' => post_string('salary_type', 50),
            'sh' => post_string('shift_timing', 80),
            'ss' => post_string('shift_start', 8) ?: null,
            'se' => post_string('shift_end', 8) ?: null,
            'p' => post_string('contact_no', 20),
            'a' => post_string('address'),
            'j' => $_POST['joining_date'],
            's' => $salaryMerge['basic_salary'],
            'pll' => post_float('paid_leave_limit'),
            'hpll' => post_float('half_paid_leave_limit'),
            'hrp' => $salaryMerge['hra_percentage'],
            'hra' => $salaryMerge['hra_amount'],
            'pfa' => isset($_POST['pf_applicable']) ? 1 : 0,
            'pfp' => post_float('pf_percentage'),
            'esia' => isset($_POST['esi_applicable']) ? 1 : 0,
            'esip' => post_float('esi_percentage'),
            'ma' => $salaryMerge['medical_allowance'],
            'sa' => $salaryMerge['special_allowance'],
            'oa' => $salaryMerge['other_allowances'],
            'metro' => $metro,
            'pai' => $payrollAuto ? 1 : 0,
            'da' => $salaryMerge['dearness_allowance'],
            'ta' => $salaryMerge['travel_allowance'],
            'gr' => post_float('gratuity_monthly'),
            'gs' => $grossComputed,
            'otr' => post_float('overtime_rate'),
            'dw' => post_float('daily_wage'),
            'hr' => post_float('hourly_rate'),
            'status' => post_string('status', 20) ?: 'active',
        ]);
        if ($payrollAuto && $grossComputed > 0) {
            indian_apply_components_to_employee($pdo, $empId);
        } elseif (!$payrollAuto) {
            employee_sync_gross_salary($pdo, $empId);
        }
        dh_sync_employee_org_labels($pdo, $empId);
        set_flash('success', 'Employee updated successfully.');
    } elseif ($action === 'reset_employee_login') {
        $empId = post_int('id');
        $row = $pdo->prepare('SELECT e.id, e.full_name, e.employee_code, e.department, e.designation, e.user_id, e.aadhaar_number, e.dob FROM employees e WHERE e.id = :id LIMIT 1');
        $row->execute(['id' => $empId]);
        $er = $row->fetch(PDO::FETCH_ASSOC);
        if (!$er || !(int)($er['user_id'] ?? 0)) {
            set_flash('danger', 'This employee has no ERP login to reset.');
            redirect('employees/list');
        }
        $uid = (int)$er['user_id'];
        $uStmt = $pdo->prepare('SELECT username, role FROM users WHERE id = :id LIMIT 1');
        $uStmt->execute(['id' => $uid]);
        $ur = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ur) {
            set_flash('danger', 'User account not found.');
            redirect('employees/list');
        }
        $aad = preg_replace('/\D/', '', (string)($er['aadhaar_number'] ?? '')) ?? '';
        if (strlen($aad) < 12) {
            $aad = '123456789012';
        }
        $plain = ec_generate_temp_password((string)$er['full_name'], $aad, (string)($er['dob'] ?? ''));
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = :h, must_change_password = 1 WHERE id = :id')->execute(['h' => $hash, 'id' => $uid]);
        ec_set_credential_reveal([
            'full_name' => (string)$er['full_name'],
            'employee_code' => (string)$er['employee_code'],
            'department' => (string)$er['department'],
            'designation' => (string)($er['designation'] ?? ''),
            'username' => (string)($ur['username'] ?? ''),
            'password_plain' => $plain,
            'role' => (string)($ur['role'] ?? 'Employee'),
        ]);
        redirect('employees/credentials');
    } elseif ($action === 'increment') {
        $employeeId = post_int('id');
        $incPercent = post_float('increment_percentage');
        $incReason = post_string('increment_reason');
        $effectiveDate = (string)($_POST['effective_date'] ?? date('Y-m-d'));

        $empRowStmt = $pdo->prepare('SELECT * FROM employees WHERE id=:id LIMIT 1');
        $empRowStmt->execute(['id' => $employeeId]);
        $empRow = $empRowStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $pdo->beginTransaction();
        try {
            if (employee_payroll_auto_indian($empRow) && (float)($empRow['gross_salary'] ?? 0) > 0) {
                $oldSalary = (float)$empRow['gross_salary'];
                $incrementAmount = round($oldSalary * $incPercent / 100, 2);
                $newSalary = round($oldSalary + $incrementAmount, 2);
                $pdo->prepare('UPDATE employees SET gross_salary=:newSalary WHERE id=:id')->execute(['newSalary' => $newSalary, 'id' => $employeeId]);
                indian_apply_components_to_employee($pdo, $employeeId);
            } else {
                $oldSalary = (float)($empRow['basic_salary'] ?? 0);
                $incrementAmount = round($oldSalary * $incPercent / 100, 2);
                $newSalary = $oldSalary + $incrementAmount;
                $pdo->prepare('UPDATE employees SET basic_salary=:newSalary WHERE id=:id')->execute(['newSalary' => $newSalary, 'id' => $employeeId]);
                employee_sync_gross_salary($pdo, $employeeId);
            }
            $pdo->prepare('INSERT INTO salary_increments(employee_id,old_salary,new_salary,increment_amount,increment_percentage,effective_date,reason) VALUES(:employee_id,:old_salary,:new_salary,:increment_amount,:increment_percentage,:effective_date,:reason)')
                ->execute([
                    'employee_id' => $employeeId,
                    'old_salary' => $oldSalary,
                    'new_salary' => $newSalary,
                    'increment_amount' => $incrementAmount,
                    'increment_percentage' => $incPercent,
                    'effective_date' => $effectiveDate,
                    'reason' => $incReason,
                ]);
            $pdo->commit();
            set_flash('success', 'Salary increment applied and history recorded.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            set_flash('danger', 'Increment failed: ' . $e->getMessage());
        }
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM employees WHERE id=:id');
        $stmt->execute(['id' => post_int('id')]);
        set_flash('warning', 'Employee deleted successfully.');
    }
    redirect('employees/list');
}

$search = trim((string)($_GET['q'] ?? ''));
$pageNo = max(1, (int)($_GET['p'] ?? 1));
$limit = 12;
$offset = ($pageNo - 1) * $limit;
$sort = (string)($_GET['sort'] ?? 'recent');
$orderBy = match ($sort) {
    'name' => 'e.full_name ASC',
    'salary_high' => 'e.gross_salary DESC, e.basic_salary DESC',
    'salary_low' => 'e.gross_salary ASC, e.basic_salary ASC',
    default => 'e.id DESC',
};
$orgJoinSql = ' FROM employees e
    LEFT JOIN users u ON u.id = e.user_id
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN designations des ON des.id = e.designation_id
    LEFT JOIN department_categories dc ON dc.id = d.category_id ';

$countStmt = $pdo->prepare('SELECT COUNT(DISTINCT e.id) ' . $orgJoinSql . ' WHERE e.full_name LIKE :q1 OR e.employee_code LIKE :q2 OR e.department LIKE :q3 OR d.department_name LIKE :q4 OR des.designation_name LIKE :q5 OR dc.category_name LIKE :q6');
$countStmt->execute([
    'q1' => '%' . $search . '%',
    'q2' => '%' . $search . '%',
    'q3' => '%' . $search . '%',
    'q4' => '%' . $search . '%',
    'q5' => '%' . $search . '%',
    'q6' => '%' . $search . '%',
]);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));

$rowsStmt = $pdo->prepare("SELECT e.*, u.email AS user_email, u.username AS login_username, d.department_name AS dept_canonical_name, des.designation_name AS designation_canonical_name, dc.category_name AS dept_category_name, dc.id AS dept_category_id
    {$orgJoinSql}
    WHERE e.full_name LIKE :q1 OR e.employee_code LIKE :q2 OR e.department LIKE :q3 OR d.department_name LIKE :q4 OR des.designation_name LIKE :q5 OR dc.category_name LIKE :q6
    ORDER BY {$orderBy} LIMIT :lim OFFSET :off");
$rowsStmt->bindValue(':q1', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q2', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q3', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q4', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q5', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q6', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$rowsStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll();

$incrementRows = $pdo->query('SELECT si.*, e.full_name FROM salary_increments si JOIN employees e ON e.id = si.employee_id ORDER BY si.id DESC LIMIT 20')->fetchAll();

$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$staffCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE employee_type='Staff'")->fetchColumn();
$presentToday = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status IN ('Present','Late','Half Day','Emergency Duty')")->fetchColumn();
$monthPayroll = (float)$pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM salaries WHERE month_year = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
$payrollSettingsApiUrl = route_url('api/payroll-settings');
$showFrom = $total > 0 ? $offset + 1 : 0;
$showTo = min($offset + count($rows), $total);
?>
<div class="module-shell">
    <div class="employee-page-head mb-3">
        <div>
            <h4 class="mb-0">Employees</h4>
            <div class="employee-breadcrumb small">
                <a href="<?= e(route_url('hr/dashboard')) ?>">Home</a>
                <span>›</span>
                <span>HR</span>
                <span>›</span>
                <span class="text-dark">Employees</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <form method="get" class="d-flex gap-2 employee-toolbar-form">
                <input type="hidden" name="page" value="employees/list">
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <div class="search-input-wrap">
                    <i class="bi bi-search"></i>
                    <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search name, code, department, category…">
                </div>
                <button class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
            </form>
            <a class="btn btn-primary" href="<?= e(route_url('employees/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Add Employee</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-red"><i class="bi bi-people"></i></span><small>Total Employees</small><h5><?= e((string)$total) ?></h5><p>All Employees</p></div></div></div>
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-blue"><i class="bi bi-person-check"></i></span><small>Active Employees</small><h5><?= e((string)$activeCount) ?></h5><p>100% Active</p></div></div></div>
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-green"><i class="bi bi-person-workspace"></i></span><small>Staff</small><h5><?= e((string)$staffCount) ?></h5><p>All Staff</p></div></div></div>
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-amber"><i class="bi bi-calendar-check"></i></span><small>Today's Present</small><h5><?= e((string)$presentToday) ?></h5><p>View Attendance</p></div></div></div>
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-purple"><i class="bi bi-currency-rupee"></i></span><small>Total Payroll (Month)</small><h5>₹<?= e(number_format($monthPayroll, 0)) ?></h5><p>This Month</p></div></div></div>
    </div>

    <div class="card mb-3 employee-directory-card">
        <div class="card-header employee-directory-card__head d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="employee-directory-card__title mb-0">Employee Directory</div>
                <div class="employee-directory-card__meta"><?= e((string)$total) ?> employees<?= $search !== '' ? ' · filtered' : '' ?></div>
            </div>
            <form method="get" class="employee-directory-sort d-flex align-items-center gap-2">
                <input type="hidden" name="page" value="employees/list">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <label class="small text-muted mb-0">Sort</label>
                <select class="form-select form-select-sm" name="sort" onchange="this.form.submit()">
                    <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Recently added</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A–Z)</option>
                    <option value="salary_high" <?= $sort === 'salary_high' ? 'selected' : '' ?>>Gross (high → low)</option>
                    <option value="salary_low" <?= $sort === 'salary_low' ? 'selected' : '' ?>>Gross (low → high)</option>
                </select>
            </form>
        </div>
        <div class="table-responsive employee-directory-scroll">
            <table class="table table-hover align-middle mb-0 employee-list-table">
                <thead>
                <tr>
                    <th class="emp-col-employee">Employee</th>
                    <th class="emp-col-org">Organization</th>
                    <th class="emp-col-type">Type</th>
                    <th class="emp-col-salary text-end">Monthly gross</th>
                    <th class="emp-col-status">Status</th>
                    <th class="emp-col-actions text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">No employees match your search.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php $initials = strtoupper(substr((string)$r['full_name'], 0, 1)); ?>
                    <?php
                    $dept = (string)($r['dept_canonical_name'] ?? $r['department'] ?? '');
                    $deptLabel = $dept !== '' ? $dept : 'General';
                    $desigRaw = trim((string)($r['designation_canonical_name'] ?? $r['designation'] ?? ''));
                    $hasDesig = $desigRaw !== '' && $desigRaw !== '—';
                    $loginUser = trim((string)($r['login_username'] ?? ''));
                    $type = (string)($r['employee_type'] ?? 'Staff');
                    $typeBadge = $type === 'Worker' ? 'badge-worker' : 'badge-staff';
                    $rowGross = (float)($r['gross_salary'] ?? 0);
                    if ($rowGross <= 0) {
                        $rowGross = employee_fixed_gross_monthly($r);
                    }
                    ?>
                    <tr>
                        <td data-label="Employee">
                            <div class="d-flex align-items-center gap-2">
                                <span class="employee-avatar"><?= e($initials) ?></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="employee-name text-truncate" title="<?= e((string)$r['full_name']) ?>"><?= e((string)$r['full_name']) ?></div>
                                    <div class="emp-employee-meta">
                                        <span class="employee-code"><?= e((string)$r['employee_code']) ?></span>
                                        <?php if ($loginUser !== ''): ?>
                                            <span class="emp-meta-dot" aria-hidden="true">·</span>
                                            <span class="emp-login-hint font-monospace" title="ERP login"><?= e($loginUser) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Organization" class="emp-org-cell">
                            <div class="emp-org-dept" title="<?= e($deptLabel) ?>"><?= e($deptLabel) ?></div>
                            <?php if ($hasDesig): ?>
                                <div class="emp-org-desig" title="<?= e($desigRaw) ?>"><?= e($desigRaw) ?></div>
                            <?php else: ?>
                                <div class="emp-org-desig emp-text-empty">No designation</div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Type"><span class="badge <?= e($typeBadge) ?>"><?= e($type) ?></span></td>
                        <td data-label="Gross" class="emp-gross-cell text-end">
                            <span class="emp-gross-value">₹<?= e(number_format($rowGross, 0, '.', ',')) ?></span><span class="emp-gross-period">/mo</span>
                        </td>
                        <td data-label="Status"><span class="badge <?= (($r['status'] ?? 'active') === 'active') ? 'bg-success' : 'bg-secondary' ?>"><?= e(ucfirst((string)($r['status'] ?? 'active'))) ?></span></td>
                        <td data-label="Actions" class="table-actions text-end">
                            <div class="dropdown employee-row-actions-dd">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle emp-actions-toggle" data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="true" aria-expanded="false" aria-label="Row actions"><i class="bi bi-three-dots"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewEmp<?= (int)$r['id'] ?>"><i class="bi bi-eye me-2"></i>View</button></li>
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editEmp<?= (int)$r['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</button></li>
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#incEmp<?= (int)$r['id'] ?>"><i class="bi bi-graph-up-arrow me-2"></i>Salary increment</button></li>
                                    <?php if ((int)($r['user_id'] ?? 0) > 0): ?>
                                    <li>
                                        <form method="post" class="m-0" onsubmit="return confirm('Issue a new temporary password? The current password will stop working immediately.');">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="reset_employee_login">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="dropdown-item"><i class="bi bi-key me-2"></i>Reset login</button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" class="m-0" onsubmit="return confirm('Delete this employee?');">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer employee-directory-card__foot d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted mb-0">Showing <?= e((string)$showFrom) ?>–<?= e((string)$showTo) ?> of <?= e((string)$total) ?></span>
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Employee pages">
                <ul class="pagination pagination-sm employee-directory-pagination mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(route_url('employees/list') . '&q=' . urlencode($search) . '&sort=' . urlencode($sort) . '&p=' . $i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header section-title">Salary Increment History</div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>Employee</th><th>Old</th><th>New</th><th>Amount</th><th>%</th><th>Effective</th><th>Reason</th></tr></thead>
                <tbody>
                <?php if (!$incrementRows): ?><tr><td colspan="7" class="text-center text-muted">No increments recorded yet.</td></tr><?php endif; ?>
                <?php foreach ($incrementRows as $inc): ?>
                    <tr>
                        <td><?= e((string)$inc['full_name']) ?></td>
                        <td><?= e((string)$inc['old_salary']) ?></td>
                        <td><?= e((string)$inc['new_salary']) ?></td>
                        <td><?= e((string)$inc['increment_amount']) ?></td>
                        <td><?= e((string)$inc['increment_percentage']) ?></td>
                        <td><?= e((string)$inc['effective_date']) ?></td>
                        <td><?= e((string)($inc['reason'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($rows as $r): ?>
    <div class="modal fade" id="viewEmp<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Employee Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-2">
                    <div class="col-md-6"><strong>Name:</strong> <?= e((string)$r['full_name']) ?></div>
                    <div class="col-md-6"><strong>Code:</strong> <?= e((string)$r['employee_code']) ?></div>
                    <div class="col-md-6"><strong>Category:</strong> <?= e((string)($r['dept_category_name'] ?? '—')) ?></div>
                    <div class="col-md-6"><strong>Department:</strong> <?= e((string)($r['dept_canonical_name'] ?? $r['department'])) ?></div>
                    <div class="col-md-6"><strong>Designation:</strong> <?= e((string)($r['designation_canonical_name'] ?? ($r['designation'] ?? '-'))) ?></div>
                    <div class="col-md-6"><strong>Father name:</strong> <?= e((string)($r['father_name'] ?? '—')) ?></div>
                    <div class="col-md-6"><strong>Date of birth:</strong> <?= e((string)($r['dob'] ?? '—')) ?></div>
                    <div class="col-md-6"><strong>Aadhaar:</strong> <?= e(ec_mask_aadhaar($r['aadhaar_number'] ?? null)) ?></div>
                    <div class="col-md-6"><strong>Login username:</strong> <?= e((string)($r['login_username'] ?: '—')) ?></div>
                    <div class="col-md-6"><strong>Type:</strong> <?= e((string)($r['employee_type'] ?? 'Staff')) ?></div>
                    <div class="col-md-6"><strong>Role:</strong> <?= e((string)($r['role'] ?? 'Employee')) ?></div>
                    <div class="col-md-6"><strong>Shift:</strong> <?= e((string)($r['shift_timing'] ?? '-')) ?></div>
                    <div class="col-md-6"><strong>Email (optional):</strong> <?= e((string)($r['email'] ?? '—')) ?></div>
                    <div class="col-md-12"><strong>Address:</strong> <?= e((string)($r['address'] ?? '-')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEmp<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true"
        data-initial-category-id="<?= (int)($r['dept_category_id'] ?? 0) ?>"
        data-initial-department-id="<?= (int)($r['department_id'] ?? 0) ?>"
        data-initial-designation-id="<?= (int)($r['designation_id'] ?? 0) ?>">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <form method="post" class="modal-content" data-payroll-preview>
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="pf_percentage" value="<?= e((string)($r['pf_percentage'] ?? '12')) ?>">
                    <input type="hidden" name="esi_percentage" value="<?= e((string)($r['esi_percentage'] ?? '0.75')) ?>">
                    <?php
                    $rowPayrollAuto = employee_payroll_auto_indian($r);
                    $rowGrossVal = (float)($r['gross_salary'] ?? 0) > 0 ? (float)$r['gross_salary'] : employee_fixed_gross_monthly($r);
                    ?>
                    <div class="card mb-3 payroll-erp-card">
                        <div class="card-header payroll-erp-card__title">Payroll</div>
                        <div class="card-body row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="payroll_auto_indian" value="1" id="editPayrollAuto<?= (int)$r['id'] ?>" data-payroll-auto <?= $rowPayrollAuto ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="editPayrollAuto<?= (int)$r['id'] ?>">Automatic split from gross (uses Payroll Settings)</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gross monthly (₹)</label>
                                <input class="form-control" type="number" step="0.01" name="gross_salary" data-payroll-gross value="<?= e((string)$rowGrossVal) ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="metro" value="1" id="editMetro<?= (int)$r['id'] ?>" data-payroll-metro <?= !empty($r['metro']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="editMetro<?= (int)$r['id'] ?>">Metro</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="row g-2 small text-muted payroll-preview-grid">
                                    <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Basic</span><span class="payroll-kv__v" data-pv="basic">—</span></div></div>
                                    <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">DA</span><span class="payroll-kv__v" data-pv="da">—</span></div></div>
                                    <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">HRA</span><span class="payroll-kv__v" data-pv="hra">—</span></div></div>
                                    <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Medical</span><span class="payroll-kv__v" data-pv="medical">—</span></div></div>
                                    <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Travel</span><span class="payroll-kv__v" data-pv="travel">—</span></div></div>
                                    <div class="col-6 col-md-4 col-lg-2"><div class="payroll-kv"><span class="payroll-kv__k">Special</span><span class="payroll-kv__v" data-pv="special">—</span></div></div>
                                </div>
                            </div>
                            <div class="col-md-2"><label class="form-label">Basic</label><input class="form-control" type="number" step="0.01" name="basic_salary" value="<?= e((string)($r['basic_salary'] ?? 0)) ?>"></div>
                            <div class="col-md-2"><label class="form-label">DA</label><input class="form-control" type="number" step="0.01" name="dearness_allowance" value="<?= e((string)($r['dearness_allowance'] ?? 0)) ?>"></div>
                            <div class="col-md-2"><label class="form-label">HRA %</label><input class="form-control" type="number" step="0.01" name="hra_percentage" value="<?= e((string)($r['hra_percentage'] ?? '40')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">HRA ₹</label><input class="form-control" type="number" step="0.01" name="hra_amount" value="<?= e((string)($r['hra_amount'] ?? '0')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Medical</label><input class="form-control" type="number" step="0.01" name="medical_allowance" value="<?= e((string)($r['medical_allowance'] ?? '0')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Travel</label><input class="form-control" type="number" step="0.01" name="travel_allowance" value="<?= e((string)($r['travel_allowance'] ?? '0')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Special</label><input class="form-control" type="number" step="0.01" name="special_allowance" value="<?= e((string)($r['special_allowance'] ?? '0')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Other</label><input class="form-control" type="number" step="0.01" name="other_allowances" value="<?= e((string)($r['other_allowances'] ?? '0')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Gratuity ₹</label><input class="form-control" type="number" step="0.01" name="gratuity_monthly" value="<?= e((string)($r['gratuity_monthly'] ?? '0')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">OT rate</label><input class="form-control" type="number" step="0.01" name="overtime_rate" value="<?= e((string)($r['overtime_rate'] ?? '0')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Daily wage</label><input class="form-control" type="number" step="0.01" name="daily_wage" value="<?= e((string)($r['daily_wage'] ?? '0')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Hourly</label><input class="form-control" type="number" step="0.01" name="hourly_rate" value="<?= e((string)($r['hourly_rate'] ?? '0')) ?>"></div>
                            <div class="col-md-2 d-flex flex-column justify-content-end gap-1">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="pf_applicable" value="1" id="editPf<?= (int)$r['id'] ?>" <?= !empty($r['pf_applicable']) ? 'checked' : '' ?>><label class="form-check-label" for="editPf<?= (int)$r['id'] ?>">PF</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="esi_applicable" value="1" id="editEsi<?= (int)$r['id'] ?>" <?= !empty($r['esi_applicable']) ? 'checked' : '' ?>><label class="form-check-label" for="editEsi<?= (int)$r['id'] ?>">ESI</label></div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header section-title">Basic Information</div>
                        <div class="card-body row g-3">
                            <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="full_name" value="<?= e((string)$r['full_name']) ?>" required></div>
                            <div class="col-md-3"><label class="form-label">Father name</label><input class="form-control" name="father_name" value="<?= e((string)($r['father_name'] ?? '')) ?>" required pattern="[\p{L}\s.\-]+"></div>
                            <div class="col-md-3"><label class="form-label">Date of birth</label><input class="form-control" type="date" name="dob" max="<?= e(date('Y-m-d')) ?>" value="<?= e((string)($r['dob'] ?? '')) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Aadhaar (12 digits)</label><input class="form-control" name="aadhaar_number" inputmode="numeric" maxlength="12" pattern="\d{12}" value="<?= e((string)($r['aadhaar_number'] ?? '')) ?>" required></div>
                            <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="employee_type" data-payroll-emp-type><option value="Staff" <?= ($r['employee_type'] ?? 'Staff')==='Staff'?'selected':'' ?>>Staff</option><option value="Worker" <?= ($r['employee_type'] ?? '')==='Worker'?'selected':'' ?>>Worker</option></select>
                            <div class="col-md-3"><label class="form-label">Job role label</label><input class="form-control" name="role" value="<?= e((string)($r['role'] ?? 'Employee')) ?>" title="Stored on employee record"></div>
                            <div class="col-md-4">
                                <label class="form-label">Department category <span class="text-danger">*</span></label>
                                <select id="edit_org_<?= (int)$r['id'] ?>_category_id" class="form-select" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department <span class="text-danger">*</span></label>
                                <select id="edit_org_<?= (int)$r['id'] ?>_department_id" name="department_id" class="form-select" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Designation</label>
                                <select id="edit_org_<?= (int)$r['id'] ?>_designation_id" name="designation_id" class="form-select"></select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="contact_no" value="<?= e((string)($r['contact_no'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e((string)($r['address'] ?? '')) ?>"></div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header section-title">Work Information</div>
                        <div class="card-body row g-3">
                            <div class="col-md-4"><label class="form-label">Salary Type</label><select class="form-select" name="salary_type"><option <?= ($r['salary_type'] ?? 'Monthly')==='Monthly'?'selected':'' ?>>Monthly</option><option <?= ($r['salary_type'] ?? '')==='Daily Wage'?'selected':'' ?>>Daily Wage</option><option <?= ($r['salary_type'] ?? '')==='Hourly'?'selected':'' ?>>Hourly</option></select></div>
                            <div class="col-md-4"><label class="form-label">Shift</label><input class="form-control" name="shift_timing" value="<?= e((string)($r['shift_timing'] ?? '')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Shift Start</label><input class="form-control" type="time" name="shift_start" value="<?= e((string)($r['shift_start'] ?? '')) ?>"></div>
                            <div class="col-md-2"><label class="form-label">Shift End</label><input class="form-control" type="time" name="shift_end" value="<?= e((string)($r['shift_end'] ?? '')) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Joining Date</label><input class="form-control" type="date" name="joining_date" value="<?= e((string)$r['joining_date']) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= (($r['status'] ?? 'active')==='active')?'selected':'' ?>>active</option><option value="inactive" <?= (($r['status'] ?? '')==='inactive')?'selected':'' ?>>inactive</option></select></div>
                            <div class="col-12">
                                <div class="small border rounded px-3 py-2 bg-light">
                                    <span class="text-muted">Stored monthly gross</span>
                                    <strong class="text-danger ms-1">₹<?= e(number_format(((float)($r['gross_salary'] ?? 0) > 0 ? (float)$r['gross_salary'] : employee_fixed_gross_monthly($r)), 0, '.', ',')) ?></strong>
                                    <span class="text-muted ms-1 d-none d-md-inline">— With auto payroll, components are recalculated from this gross on save.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header section-title">Leave policy & login</div>
                        <div class="card-body row g-3">
                            <div class="col-md-4"><label class="form-label">Paid Leave</label><input class="form-control" type="number" step="0.01" name="paid_leave_limit" value="<?= e((string)($r['paid_leave_limit'] ?? 12)) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Half Paid Leave</label><input class="form-control" type="number" step="0.01" name="half_paid_leave_limit" value="<?= e((string)($r['half_paid_leave_limit'] ?? 6)) ?>"></div>
                            <div class="col-md-4">
                                <label class="form-label">ERP login</label>
                                <?php if ((int)($r['user_id'] ?? 0) > 0): ?>
                                    <div class="small">Username: <span class="font-monospace"><?= e((string)($r['login_username'] ?? '')) ?></span></div>
                                    <div class="small text-muted">Use “Reset login” in the directory row to issue a new temporary password.</div>
                                <?php else: ?>
                                    <div class="small text-muted">No ERP user linked. Create login from Add Employee flow for new hires.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    $incOnGross = employee_payroll_auto_indian($r) && (float)($r['gross_salary'] ?? 0) > 0;
    $incCurrent = $incOnGross ? (float)$r['gross_salary'] : (float)($r['basic_salary'] ?? 0);
    $incPreviewLabel = $incOnGross ? 'monthly gross' : 'basic salary';
    ?>
    <div class="modal fade increment-modal" id="incEmp<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true" data-current-salary="<?= e((string)$incCurrent) ?>">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form method="post" class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Salary Increment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="increment">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <div class="card mb-3"><div class="card-body small"><div><strong><?= e((string)$r['full_name']) ?></strong></div><div class="text-muted"><?= e((string)$r['department']) ?> | <?= e((string)($r['employee_type'] ?? 'Staff')) ?></div><div class="mt-2">Increment applied on: <strong><?= e($incPreviewLabel) ?></strong> — current: <strong>INR <?= e(number_format($incCurrent, 2)) ?></strong></div><div class="mt-1">Monthly gross (fixed): <strong class="text-danger">INR <?= e(number_format(((float)($r['gross_salary'] ?? 0) > 0 ? (float)$r['gross_salary'] : employee_fixed_gross_monthly($r)), 2)) ?></strong> <span class="text-muted">(sum of earnings excl. OT)</span></div></div></div>
                    <div class="mb-2"><label class="form-label">Increment %</label><input class="form-control increment-percent" type="number" step="0.01" name="increment_percentage" required></div>
                    <div class="mb-2"><label class="form-label">Increment Amount</label><input class="form-control increment-amount" type="number" step="0.01" readonly></div>
                    <div class="mb-2"><label class="form-label">Effective Date</label><input class="form-control" type="date" name="effective_date" value="<?= e(date('Y-m-d')) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Reason</label><input class="form-control" name="increment_reason" placeholder="Annual cycle / promotion"></div>
                    <div class="increment-preview small">New Salary Preview: INR <strong class="increment-new-salary">0.00</strong></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Apply Increment</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php
$dcVerIdx = is_file(__DIR__ . '/../../assets/js/department-cascade.js') ? (int)filemtime(__DIR__ . '/../../assets/js/department-cascade.js') : (int)time();
$ipsVerIdx = is_file(__DIR__ . '/../../assets/js/indian-payroll-split.js') ? (int)filemtime(__DIR__ . '/../../assets/js/indian-payroll-split.js') : (int)time();
$ppVerIdx = is_file(__DIR__ . '/../../assets/js/payroll-preview.js') ? (int)filemtime(__DIR__ . '/../../assets/js/payroll-preview.js') : (int)time();
?>
<script src="assets/js/department-cascade.js?v=<?= e((string)$dcVerIdx) ?>"></script>
<script src="assets/js/indian-payroll-split.js?v=<?= e((string)$ipsVerIdx) ?>"></script>
<script src="assets/js/payroll-preview.js?v=<?= e((string)$ppVerIdx) ?>"></script>
<script>
document.querySelectorAll('[id^="editEmp"]').forEach(function (modal) {
    var id = modal.id.replace(/^editEmp/, '');
    if (id) {
        window.DepartmentCascade.bindModal(modal, 'edit_org_' + id + '_');
    }
});
document.addEventListener('DOMContentLoaded', function () {
    if (window.PayrollPreview) {
        PayrollPreview.init({ settingsUrl: <?= json_encode($payrollSettingsApiUrl, JSON_THROW_ON_ERROR) ?> });
    }
});
</script>
