<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/department_hierarchy.php';
require_once __DIR__ . '/../../includes/employee_credentials.php';
require_once __DIR__ . '/../../includes/payroll_logic.php';
require_once __DIR__ . '/../../includes/indian_payroll.php';
require_once __DIR__ . '/../../includes/employee_lifecycle.php';
require_once __DIR__ . '/../../includes/employee_list_service.php';
require_once __DIR__ . '/../../includes/employee_increment_service.php';
require_once __DIR__ . '/../../includes/employee_profile_service.php';
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

        $newStatus = strtolower(post_string('status', 20) ?: 'active');
        $prevSt = $pdo->prepare('SELECT status FROM employees WHERE id = :id LIMIT 1');
        $prevSt->execute(['id' => $empId]);
        $prevStatus = strtolower((string)($prevSt->fetchColumn() ?: 'active'));
        if ($prevStatus === 'active' && $newStatus !== 'active') {
            require_once __DIR__ . '/../../includes/machine_service.php';
            mach_close_assignments_for_employee($pdo, $empId, 'Employee marked inactive / left');
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
            'status' => $newStatus,
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
        try {
            employee_delete_safe($pdo, post_int('id'));
            set_flash('warning', 'Employee and related HR records were deleted.');
        } catch (Throwable $e) {
            set_flash('danger', 'Could not delete employee: ' . $e->getMessage());
        }
    }
    redirect('employees/list');
}

$filters = emp_list_parse_filters($_GET);
$profileExport = (string)($_GET['profile_export'] ?? '');
$profileEmpId = (int)($_GET['emp_profile'] ?? 0);
if ($profileExport !== '' && $profileEmpId > 0) {
    emp_profile_render_export($pdo, $filters, $profileEmpId, $profileExport);
}

$incFilters = emp_inc_parse_filters($_GET);
$empPerPageDefault = emp_list_per_page_default();
$perPageAllowed = emp_list_per_page_allowed();
$limit = (int)$filters['per_page'];
$empScrollVisibleRows = min(max($limit, 3), 6);

$incExport = (string)($_GET['inc_export'] ?? '');
if ($incExport !== '') {
    $incExportRows = emp_inc_export_rows($pdo, $incFilters);
    $companyName = hr_reports_company_name($pdo);
    $incTotal = count($incExportRows);
    if ($incExport === 'excel') {
        emp_inc_export_excel($incExportRows);
        exit;
    }
    if ($incExport === 'pdf' || $incExport === 'print') {
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/increment_print.php';
        exit;
    }
}

$export = (string)($_GET['export'] ?? '');
if ($export !== '') {
    $exportRows = emp_list_export_rows($pdo, $filters);
    $companyName = hr_reports_company_name($pdo);
    $totalFiltered = count($exportRows);
    if ($export === 'excel') {
        emp_list_export_excel($exportRows, $filters);
        exit;
    }
    if ($export === 'pdf' || $export === 'print') {
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/list_print.php';
        exit;
    }
}

$pageNo = (int)$filters['page'];
$offset = ($pageNo - 1) * $limit;
$listBundle = emp_list_fetch($pdo, $filters, $limit, $offset);
$total = $listBundle['total'];
$rows = $listBundle['rows'];
$totalPages = max(1, (int)ceil($total / $limit));
if ($pageNo > $totalPages) {
    $pageNo = $totalPages;
    $filters['page'] = $pageNo;
    $offset = ($pageNo - 1) * $limit;
    $listBundle = emp_list_fetch($pdo, $filters, $limit, $offset);
    $rows = $listBundle['rows'];
}
$rows = emp_list_enrich_rows($pdo, $rows);
$empIncrementsById = emp_list_increments_for_employees($pdo, array_column($rows, 'id'));

$empListQuery = static function (array $extra = []) use ($filters): string {
    $merged = $filters;
    foreach ($extra as $k => $v) {
        if ($k === 'p') {
            $merged['page'] = (int)$v;
        } else {
            $merged[$k] = $v;
        }
    }

    return emp_list_build_url($merged);
};

$incPerPageDefault = emp_inc_per_page_default();
$incPerPageAllowed = emp_inc_per_page_allowed();
$incLimit = (int)$incFilters['per_page'];
$incScrollVisibleRows = min(max($incLimit, 3), 6);
$incPageNo = (int)$incFilters['page'];
$incOffset = ($incPageNo - 1) * $incLimit;
$incBundle = emp_inc_fetch($pdo, $incFilters, $incLimit, $incOffset);
$incTotal = $incBundle['total'];
$incrementRows = $incBundle['rows'];
$incTotalPages = max(1, (int)ceil($incTotal / $incLimit));
if ($incPageNo > $incTotalPages) {
    $incPageNo = $incTotalPages;
    $incFilters['page'] = $incPageNo;
    $incOffset = ($incPageNo - 1) * $incLimit;
    $incBundle = emp_inc_fetch($pdo, $incFilters, $incLimit, $incOffset);
    $incrementRows = $incBundle['rows'];
}
$incShowFrom = $incTotal > 0 ? $incOffset + 1 : 0;
$incShowTo = min($incOffset + count($incrementRows), $incTotal);
$incExportBase = emp_inc_build_url($filters, $incFilters);
$incResetUrl = emp_inc_build_url($filters, array_merge($incFilters, ['q' => '', 'from' => '', 'to' => '', 'page' => 1]));
$incFilterSummary = emp_inc_filter_summary($incFilters);

$dashStats = emp_list_dashboard_stats($pdo);
$activeCount = $dashStats['active'];
$staffCount = $dashStats['staff'];
$presentToday = $dashStats['present'];
$monthPayroll = $dashStats['payroll'];
$filterSummary = emp_list_filter_summary_label($filters);
$exportBase = emp_list_build_url($filters);
$resetUrl = emp_list_build_url(array_merge($filters, [
    'q' => '', 'department' => '', 'employee_type' => '', 'shift' => '', 'status' => '',
    'join_from' => '', 'join_to' => '', 'page' => 1, 'sort' => 'recent', 'dir' => 'desc',
]));
$payrollSettingsApiUrl = route_url('api/payroll-settings');
$scriptBaseEmp = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
$payrollSettingsApiUrlAbs = ($scriptBaseEmp !== '' ? $scriptBaseEmp . '/' : '/') . ltrim($payrollSettingsApiUrl, '/');
$previewScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$payrollSettingsApiUrlFull = $previewScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $payrollSettingsApiUrlAbs;
$showFrom = $total > 0 ? $offset + 1 : 0;
$showTo = min($offset + count($rows), $total);
?>
<div class="hr-page module-shell">
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
            <a class="btn btn-primary btn-sm" href="<?= e(route_url('employees/create')) ?>"><i class="bi bi-plus-lg me-1"></i>Add Employee</a>
        </div>
    </div>

    <form method="get" class="emp-list-filters" id="empListFilterForm">
        <input type="hidden" name="page" value="employees/list">
        <div class="emp-list-filters__grid">
            <div class="emp-list-filters__field emp-list-filters__field--search">
                <label class="emp-list-filters__label" for="emp_q">Search employee</label>
                <input type="search" class="form-control" id="emp_q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, code, phone, email…" autocomplete="off">
            </div>
            <div class="emp-list-filters__field">
                <label class="emp-list-filters__label" for="emp_department">Department</label>
                <select class="form-select" id="emp_department" name="department">
                    <?php foreach (emp_list_department_filter_options() as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $filters['department'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="emp-list-filters__field">
                <label class="emp-list-filters__label" for="emp_type">Employee type</label>
                <select class="form-select" id="emp_type" name="employee_type">
                    <option value="">All types</option>
                    <option value="Worker" <?= $filters['employee_type'] === 'Worker' ? 'selected' : '' ?>>Worker</option>
                    <option value="Staff" <?= $filters['employee_type'] === 'Staff' ? 'selected' : '' ?>>Staff</option>
                </select>
            </div>
            <div class="emp-list-filters__field">
                <label class="emp-list-filters__label" for="emp_shift">Shift</label>
                <select class="form-select" id="emp_shift" name="shift">
                    <option value="">All shifts</option>
                    <?php foreach (['Morning', 'Evening', 'Night'] as $sh): ?>
                        <option value="<?= e($sh) ?>" <?= $filters['shift'] === $sh ? 'selected' : '' ?>><?= e($sh) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="emp-list-filters__field">
                <label class="emp-list-filters__label" for="emp_status">Status</label>
                <select class="form-select" id="emp_status" name="status">
                    <option value="">All status</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="emp-list-filters__field emp-list-filters__field--dates">
                <label class="emp-list-filters__label" for="emp_join_from">Joining from <span class="text-muted fw-normal">(optional)</span></label>
                <input type="date" class="form-control" id="emp_join_from" name="join_from" value="<?= e($filters['join_from']) ?>">
            </div>
            <div class="emp-list-filters__field emp-list-filters__field--dates">
                <label class="emp-list-filters__label" for="emp_join_to">Joining to <span class="text-muted fw-normal">(optional)</span></label>
                <input type="date" class="form-control" id="emp_join_to" name="join_to" value="<?= e($filters['join_to']) ?>">
            </div>
        </div>
        <div class="emp-list-filters__actions">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply filter</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e($resetUrl) ?>">Reset filter</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e($exportBase . '&export=pdf') ?>" target="_blank"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e($exportBase . '&export=excel') ?>"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e($exportBase . '&export=print') ?>" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
            <?php if ($limit !== $empPerPageDefault): ?>
                <input type="hidden" name="per_page" value="<?= (int)$limit ?>">
            <?php endif; ?>
            <?php if ($filters['sort'] !== 'recent'): ?>
                <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
            <?php endif; ?>
            <?php if ($filters['dir'] !== ''): ?>
                <input type="hidden" name="dir" value="<?= e($filters['dir']) ?>">
            <?php endif; ?>
            <span class="emp-list-filters__hint d-none d-lg-inline">Joining dates are optional · exports use current filters</span>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-red"><i class="bi bi-people"></i></span><small>Total Employees</small><h5><?= e((string)$total) ?></h5><p>All Employees</p></div></div></div>
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-blue"><i class="bi bi-person-check"></i></span><small>Active Employees</small><h5><?= e((string)$activeCount) ?></h5><p>100% Active</p></div></div></div>
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-green"><i class="bi bi-person-workspace"></i></span><small>Staff</small><h5><?= e((string)$staffCount) ?></h5><p>All Staff</p></div></div></div>
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-amber"><i class="bi bi-calendar-check"></i></span><small>Today's Present</small><h5><?= e((string)$presentToday) ?></h5><p>View Attendance</p></div></div></div>
        <div class="col-lg col-md-6"><div class="card employee-stat-card"><div class="card-body"><span class="stat-icon soft-purple"><i class="bi bi-currency-rupee"></i></span><small>Total Payroll (Month)</small><h5>₹<?= e(number_format($monthPayroll, 0)) ?></h5><p>This Month</p></div></div></div>
    </div>

    <div class="card mb-3 employee-directory-card">
        <div class="card-header employee-directory-card__head d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="min-w-0">
                <div class="employee-directory-card__title mb-0">Employee Directory</div>
                <div class="employee-directory-card__meta"><?= e((string)$total) ?> match<?= $total === 1 ? '' : 'es' ?><?= emp_list_has_active_filters($filters) ? ' (filtered)' : '' ?> · click row for full profile</div>
                <div class="employee-directory-card__meta--filter" title="<?= e($filterSummary) ?>"><?= e($filterSummary) ?></div>
            </div>
            <form method="get" class="emp-table-toolbar">
                <input type="hidden" name="page" value="employees/list">
                <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                <input type="hidden" name="department" value="<?= e($filters['department']) ?>">
                <input type="hidden" name="employee_type" value="<?= e($filters['employee_type']) ?>">
                <input type="hidden" name="shift" value="<?= e($filters['shift']) ?>">
                <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                <input type="hidden" name="join_from" value="<?= e($filters['join_from']) ?>">
                <input type="hidden" name="join_to" value="<?= e($filters['join_to']) ?>">
                <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
                <input type="hidden" name="dir" value="<?= e($filters['dir']) ?>">
                <label class="small text-muted mb-0">Rows</label>
                <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()">
                    <?php foreach ($perPageAllowed as $pp): ?>
                        <option value="<?= $pp ?>" <?= $limit === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php require __DIR__ . '/_directory_table.php'; ?>
        <div class="card-footer employee-directory-card__foot d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted mb-0">
                Showing <?= e((string)$showFrom) ?>–<?= e((string)$showTo) ?> of <?= e((string)$total) ?>
                <?php if ($totalPages > 1): ?> · Page <?= e((string)$pageNo) ?> of <?= e((string)$totalPages) ?><?php endif; ?>
                <?php if (count($rows) > $empScrollVisibleRows): ?>
                    <span class="employee-directory-scroll-hint d-none d-md-inline"> · Scroll table ↔↕</span>
                <?php endif; ?>
            </span>
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Employee pages">
                <ul class="pagination pagination-sm employee-directory-pagination mb-0">
                    <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e($empListQuery(['p' => '1'])) ?>">First</a>
                    </li>
                    <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e($empListQuery(['p' => (string)max(1, $pageNo - 1)])) ?>">Prev</a>
                    </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($empListQuery(['p' => (string)$i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e($empListQuery(['p' => (string)min($totalPages, $pageNo + 1)])) ?>">Next</a>
                    </li>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e($empListQuery(['p' => (string)$totalPages])) ?>">Last</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4 employee-directory-card emp-increment-card" id="salaryIncrementSection">
        <div class="card-header employee-directory-card__head d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div class="min-w-0">
                <div class="employee-directory-card__title mb-0">Salary Increment History</div>
                <div class="employee-directory-card__meta"><?= e((string)$incTotal) ?> record<?= $incTotal === 1 ? '' : 's' ?></div>
                <div class="employee-directory-card__meta--filter" title="<?= e($incFilterSummary) ?>"><?= e($incFilterSummary) ?></div>
            </div>
            <form method="get" class="emp-inc-toolbar d-flex flex-wrap align-items-end gap-2">
                <input type="hidden" name="page" value="employees/list">
                <?php foreach (emp_list_query_params($filters) as $pk => $pv): if ($pk === 'page' || $pv === null) continue; ?>
                    <input type="hidden" name="<?= e($pk === 'p' ? 'p' : $pk) ?>" value="<?= e((string)$pv) ?>">
                <?php endforeach; ?>
                <div>
                    <label class="emp-list-filters__label" for="inc_q">Search</label>
                    <input type="search" class="form-control form-control-sm" id="inc_q" name="inc_q" value="<?= e($incFilters['q']) ?>" placeholder="Name, code, reason…" style="min-width:10rem">
                </div>
                <div>
                    <label class="emp-list-filters__label" for="inc_from">Effective from</label>
                    <input type="date" class="form-control form-control-sm" id="inc_from" name="inc_from" value="<?= e($incFilters['from']) ?>">
                </div>
                <div>
                    <label class="emp-list-filters__label" for="inc_to">Effective to</label>
                    <input type="date" class="form-control form-control-sm" id="inc_to" name="inc_to" value="<?= e($incFilters['to']) ?>">
                </div>
                <div class="d-flex flex-wrap gap-1 align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e($incResetUrl) ?>">Reset</a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e($incExportBase . '&inc_export=pdf') ?>" target="_blank"><i class="bi bi-file-pdf"></i></a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e($incExportBase . '&inc_export=excel') ?>"><i class="bi bi-file-earmark-spreadsheet"></i></a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e($incExportBase . '&inc_export=print') ?>" target="_blank"><i class="bi bi-printer"></i></a>
                </div>
            </form>
        </div>
        <div class="employee-directory-scroll emp-table-viewport emp-increment-scroll"
             style="--emp-visible-rows: <?= (int)$incScrollVisibleRows ?>"
             tabindex="0"
             aria-label="Salary increments — scroll for more rows">
            <table class="table table-sm table-hover align-middle mb-0 employee-list-table emp-increment-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Code</th>
                        <th>Department</th>
                        <th class="text-end">Old (₹)</th>
                        <th class="text-end">New (₹)</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">%</th>
                        <th>Effective</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$incrementRows): ?><tr><td colspan="9" class="text-center text-muted py-4">No increments match your filters.</td></tr><?php endif; ?>
                <?php foreach ($incrementRows as $inc): ?>
                    <tr>
                        <td><?= e((string)$inc['full_name']) ?></td>
                        <td class="font-monospace small"><?= e((string)($inc['employee_code'] ?? '')) ?></td>
                        <td><?= e((string)($inc['department_name'] ?? '—')) ?></td>
                        <td class="text-end"><?= e(number_format((float)$inc['old_salary'], 2)) ?></td>
                        <td class="text-end"><?= e(number_format((float)$inc['new_salary'], 2)) ?></td>
                        <td class="text-end text-success fw-semibold"><?= e(number_format((float)$inc['increment_amount'], 2)) ?></td>
                        <td class="text-end"><?= e(number_format((float)$inc['increment_percentage'], 2)) ?></td>
                        <td class="emp-col-joined"><?= e((string)$inc['effective_date']) ?></td>
                        <td><?= e((string)($inc['reason'] ?? '') !== '' ? $inc['reason'] : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer employee-directory-card__foot d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted mb-0">
                    Showing <?= e((string)$incShowFrom) ?>–<?= e((string)$incShowTo) ?> of <?= e((string)$incTotal) ?>
                    <?php if ($incTotalPages > 1): ?> · Page <?= e((string)$incPageNo) ?> of <?= e((string)$incTotalPages) ?><?php endif; ?>
                    <?php if (count($incrementRows) > $incScrollVisibleRows): ?>
                        <span class="employee-directory-scroll-hint d-none d-md-inline"> · Scroll table ↔↕</span>
                    <?php endif; ?>
                </span>
                <form method="get" class="d-flex align-items-center gap-1 mb-0">
                    <input type="hidden" name="page" value="employees/list">
                    <?php foreach (array_merge(emp_list_query_params($filters), emp_inc_query_params($incFilters)) as $pk => $pv): ?>
                        <?php if ($pk === 'page' || $pv === null || $pk === 'inc_per_page' || $pk === 'inc_p') continue; ?>
                        <input type="hidden" name="<?= e($pk) ?>" value="<?= e((string)$pv) ?>">
                    <?php endforeach; ?>
                    <label class="small text-muted mb-0">Rows</label>
                    <select class="form-select form-select-sm" name="inc_per_page" onchange="this.form.submit()" style="width:auto;min-width:3.5rem">
                        <?php foreach ($incPerPageAllowed as $ipp): ?>
                            <option value="<?= $ipp ?>" <?= $incLimit === $ipp ? 'selected' : '' ?>><?= $ipp ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php if ($incTotalPages > 1): ?>
            <nav aria-label="Increment pages">
                <ul class="pagination pagination-sm employee-directory-pagination mb-0">
                    <li class="page-item <?= $incPageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(emp_inc_build_url($filters, $incFilters, ['page' => 1])) ?>">First</a>
                    </li>
                    <li class="page-item <?= $incPageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(emp_inc_build_url($filters, $incFilters, ['page' => max(1, $incPageNo - 1)])) ?>">Prev</a>
                    </li>
                    <?php for ($ii = 1; $ii <= $incTotalPages; $ii++): ?>
                    <li class="page-item <?= $ii === $incPageNo ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(emp_inc_build_url($filters, $incFilters, ['page' => $ii])) ?>"><?= $ii ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $incPageNo >= $incTotalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(emp_inc_build_url($filters, $incFilters, ['page' => min($incTotalPages, $incPageNo + 1)])) ?>">Next</a>
                    </li>
                    <li class="page-item <?= $incPageNo >= $incTotalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(emp_inc_build_url($filters, $incFilters, ['page' => $incTotalPages])) ?>">Last</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end emp-record-drawer" tabindex="-1" id="empRecordDrawer" aria-labelledby="empRecordDrawerTitle">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="empRecordDrawerTitle">Employee record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="empRecordDrawerBody"></div>
</div>

<?php foreach ($rows as $r): ?>
    <?php
    $empIncrements = $empIncrementsById[(int)$r['id']] ?? [];
    $profile = emp_profile_build($pdo, $r, $empIncrements);
    ?>
    <template id="empRecordTpl<?= (int)$r['id'] ?>">
        <?php require __DIR__ . '/_record_drawer_content.php'; ?>
    </template>

    <div class="modal fade employee-edit-modal" id="editEmp<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true"
        data-initial-category-id="<?= (int)($r['dept_category_id'] ?? 0) ?>"
        data-initial-department-id="<?= (int)($r['department_id'] ?? 0) ?>"
        data-initial-designation-id="<?= (int)($r['designation_id'] ?? 0) ?>">
        <div class="modal-dialog modal-dialog-scrollable employee-edit-modal__dialog">
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
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="employee_type" data-payroll-emp-type>
                                    <option value="Staff" <?= ($r['employee_type'] ?? 'Staff') === 'Staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="Worker" <?= ($r['employee_type'] ?? '') === 'Worker' ? 'selected' : '' ?>>Worker</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Job role label</label>
                                <input class="form-control" name="role" value="<?= e((string)($r['role'] ?? 'Employee')) ?>" title="Stored on employee record">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department category <span class="text-danger">*</span></label>
                                <select id="edit_org_<?= (int)$r['id'] ?>_category_id" class="form-select" data-dept-cascade="1" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department <span class="text-danger">*</span></label>
                                <select id="edit_org_<?= (int)$r['id'] ?>_department_id" name="department_id" class="form-select" data-dept-cascade="1" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Designation</label>
                                <select id="edit_org_<?= (int)$r['id'] ?>_designation_id" name="designation_id" class="form-select" data-dept-cascade="1"></select>
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
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
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
        PayrollPreview.init({ settingsUrl: <?= json_encode($payrollSettingsApiUrlFull, JSON_THROW_ON_ERROR) ?> });
    }

    document.querySelectorAll('.employee-row-actions-dd .dropdown-item[data-bs-toggle="modal"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dd = btn.closest('.dropdown');
            if (!dd || !window.bootstrap) {
                return;
            }
            var toggle = dd.querySelector('[data-bs-toggle="dropdown"]');
            var inst = toggle ? bootstrap.Dropdown.getInstance(toggle) : null;
            if (inst) {
                inst.hide();
            }
            document.querySelectorAll('.modal-backdrop').forEach(function (el, i, list) {
                if (list.length > 1 && i < list.length - 1) {
                    el.remove();
                }
            });
        });
    });
});
</script>
