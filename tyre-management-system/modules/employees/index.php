<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Super Admin', 'HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update') {
        $stmt = $pdo->prepare('UPDATE employees SET full_name=:n, employee_type=:et, department=:d, designation=:des, role=:r, salary_type=:st, shift_timing=:sh, shift_start=:ss, shift_end=:se, contact_no=:p, address=:a, joining_date=:j, basic_salary=:s, paid_leave_limit=:pll, half_paid_leave_limit=:hpll, hra_percentage=:hrp, hra_amount=:hra, pf_applicable=:pfa, pf_percentage=:pfp, esi_applicable=:esia, esi_percentage=:esip, medical_allowance=:ma, other_allowances=:oa, overtime_rate=:otr, daily_wage=:dw, hourly_rate=:hr, status=:status, user_id=:u WHERE id=:id');
        $stmt->execute([
            'id' => post_int('id'),
            'n' => post_string('full_name', 150),
            'et' => post_string('employee_type', 20) ?: 'Staff',
            'd' => post_string('department', 100),
            'des' => post_string('designation', 120),
            'r' => post_string('role', 100),
            'st' => post_string('salary_type', 50),
            'sh' => post_string('shift_timing', 80),
            'ss' => post_string('shift_start', 8) ?: null,
            'se' => post_string('shift_end', 8) ?: null,
            'p' => post_string('contact_no', 20),
            'a' => post_string('address'),
            'j' => $_POST['joining_date'],
            's' => post_float('basic_salary'),
            'pll' => post_float('paid_leave_limit'),
            'hpll' => post_float('half_paid_leave_limit'),
            'hrp' => post_float('hra_percentage'),
            'hra' => post_float('hra_amount'),
            'pfa' => isset($_POST['pf_applicable']) ? 1 : 0,
            'pfp' => post_float('pf_percentage'),
            'esia' => isset($_POST['esi_applicable']) ? 1 : 0,
            'esip' => post_float('esi_percentage'),
            'ma' => post_float('medical_allowance'),
            'oa' => post_float('other_allowances'),
            'otr' => post_float('overtime_rate'),
            'dw' => post_float('daily_wage'),
            'hr' => post_float('hourly_rate'),
            'status' => post_string('status', 20) ?: 'active',
            'u' => !empty($_POST['user_id']) ? post_int('user_id') : null,
        ]);
        set_flash('success', 'Employee updated successfully.');
    } elseif ($action === 'increment') {
        $employeeId = post_int('id');
        $incPercent = post_float('increment_percentage');
        $incReason = post_string('increment_reason');
        $effectiveDate = (string)($_POST['effective_date'] ?? date('Y-m-d'));

        $currentSalaryStmt = $pdo->prepare('SELECT basic_salary FROM employees WHERE id=:id');
        $currentSalaryStmt->execute(['id' => $employeeId]);
        $oldSalary = (float)$currentSalaryStmt->fetchColumn();
        $incrementAmount = round($oldSalary * $incPercent / 100, 2);
        $newSalary = $oldSalary + $incrementAmount;

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE employees SET basic_salary=:newSalary WHERE id=:id')->execute(['newSalary' => $newSalary, 'id' => $employeeId]);
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
    'salary_high' => 'e.basic_salary DESC',
    'salary_low' => 'e.basic_salary ASC',
    default => 'e.id DESC',
};
$employeeUsers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'Employee' ORDER BY full_name")->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE full_name LIKE :q1 OR employee_code LIKE :q2 OR department LIKE :q3');
$countStmt->execute(['q1' => '%' . $search . '%', 'q2' => '%' . $search . '%', 'q3' => '%' . $search . '%']);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));

$rowsStmt = $pdo->prepare("SELECT e.*, u.email AS user_email FROM employees e LEFT JOIN users u ON u.id = e.user_id WHERE e.full_name LIKE :q1 OR e.employee_code LIKE :q2 OR e.department LIKE :q3 ORDER BY {$orderBy} LIMIT :lim OFFSET :off");
$rowsStmt->bindValue(':q1', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q2', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':q3', '%' . $search . '%', PDO::PARAM_STR);
$rowsStmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$rowsStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll();

$incrementRows = $pdo->query('SELECT si.*, e.full_name FROM salary_increments si JOIN employees e ON e.id = si.employee_id ORDER BY si.id DESC LIMIT 20')->fetchAll();

$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$staffCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE employee_type='Staff'")->fetchColumn();
$presentToday = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status IN ('Present','Late','Half Day','Emergency Duty')")->fetchColumn();
$monthPayroll = (float)$pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM salaries WHERE month_year = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
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
                    <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by name / code / email...">
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

    <div class="card mb-3">
        <div class="card-header section-title d-flex justify-content-between align-items-center">
            <span>Employee Directory</span>
            <form method="get" class="d-flex align-items-center gap-2">
                <input type="hidden" name="page" value="employees/list">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <label class="small text-muted mb-0">Sort By</label>
                <select class="form-select form-select-sm" name="sort" onchange="this.form.submit()">
                    <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Recently Added</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
                    <option value="salary_high" <?= $sort === 'salary_high' ? 'selected' : '' ?>>Salary (High)</option>
                    <option value="salary_low" <?= $sort === 'salary_low' ? 'selected' : '' ?>>Salary (Low)</option>
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 employee-list-table">
                <thead class="table-light">
                <tr><th>Employee</th><th>Department</th><th>Designation</th><th>Type</th><th>Salary</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted">No employee records found.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php $initials = strtoupper(substr((string)$r['full_name'], 0, 1)); ?>
                    <?php
                    $dept = (string)($r['department'] ?? '');
                    $deptBadge = match ($dept) {
                        'HR', 'Human Resources' => 'badge-hr',
                        'Production' => 'badge-production',
                        default => 'bg-secondary',
                    };
                    $type = (string)($r['employee_type'] ?? 'Staff');
                    $typeBadge = $type === 'Worker' ? 'badge-worker' : 'badge-staff';
                    ?>
                    <tr>
                        <td data-label="Employee">
                            <div class="d-flex align-items-center gap-2">
                                <span class="employee-avatar"><?= e($initials) ?></span>
                                <div>
                                    <div class="employee-name"><?= e((string)$r['full_name']) ?></div>
                                    <small class="employee-code"><?= e((string)$r['employee_code']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Department"><span class="badge <?= e($deptBadge) ?>"><?= e($dept !== '' ? $dept : 'General') ?></span></td>
                        <td data-label="Designation"><?= e((string)($r['designation'] ?? '-')) ?></td>
                        <td data-label="Type"><span class="badge <?= e($typeBadge) ?>"><?= e($type) ?></span></td>
                        <td data-label="Salary">INR <?= e(number_format((float)($r['basic_salary'] ?? 0), 2)) ?></td>
                        <td data-label="Status"><span class="badge <?= (($r['status'] ?? 'active') === 'active') ? 'bg-success' : 'bg-secondary' ?>"><?= e((string)($r['status'] ?? 'active')) ?></span></td>
                        <td data-label="Actions" class="table-actions">
                            <div class="action-pills d-none d-md-flex">
                                <button type="button" class="btn action-pill action-view" data-bs-toggle="modal" data-bs-target="#viewEmp<?= (int)$r['id'] ?>" title="View">
                                    <i class="bi bi-eye"></i><span>View</span>
                                </button>
                                <button type="button" class="btn action-pill action-edit" data-bs-toggle="modal" data-bs-target="#editEmp<?= (int)$r['id'] ?>" title="Edit">
                                    <i class="bi bi-pencil"></i><span>Edit</span>
                                </button>
                                <button type="button" class="btn action-pill action-increment" data-bs-toggle="modal" data-bs-target="#incEmp<?= (int)$r['id'] ?>" title="Increment">
                                    <i class="bi bi-graph-up-arrow"></i><span>Increment</span>
                                </button>
                            </div>
                            <div class="dropdown d-md-none">
                                <button class="btn btn-action-compact dropdown-toggle" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewEmp<?= (int)$r['id'] ?>"><i class="bi bi-eye me-2"></i>View</button></li>
                                    <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editEmp<?= (int)$r['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</button></li>
                                    <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#incEmp<?= (int)$r['id'] ?>"><i class="bi bi-graph-up-arrow me-2"></i>Increment</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" onsubmit="return confirm('Delete employee?')">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
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
    </div>

    <nav><ul class="pagination pagination-sm">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>"><a class="page-link" href="<?= e(route_url('employees/list') . '&q=' . urlencode($search) . '&p=' . $i) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
    </ul></nav>

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
                    <div class="col-md-6"><strong>Department:</strong> <?= e((string)$r['department']) ?></div>
                    <div class="col-md-6"><strong>Designation:</strong> <?= e((string)($r['designation'] ?? '-')) ?></div>
                    <div class="col-md-6"><strong>Type:</strong> <?= e((string)($r['employee_type'] ?? 'Staff')) ?></div>
                    <div class="col-md-6"><strong>Role:</strong> <?= e((string)($r['role'] ?? 'Employee')) ?></div>
                    <div class="col-md-6"><strong>Shift:</strong> <?= e((string)($r['shift_timing'] ?? '-')) ?></div>
                    <div class="col-md-6"><strong>Linked User:</strong> <?= e((string)($r['user_email'] ?? 'Not linked')) ?></div>
                    <div class="col-md-12"><strong>Address:</strong> <?= e((string)($r['address'] ?? '-')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEmp<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <form method="post" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                    <div class="card mb-3">
                        <div class="card-header section-title">Basic Information</div>
                        <div class="card-body row g-3">
                            <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="full_name" value="<?= e((string)$r['full_name']) ?>" required></div>
                            <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="employee_type"><option <?= ($r['employee_type'] ?? 'Staff')==='Staff'?'selected':'' ?>>Staff</option><option <?= ($r['employee_type'] ?? '')==='Worker'?'selected':'' ?>>Worker</option></select></div>
                            <div class="col-md-3"><label class="form-label">Role</label><input class="form-control" name="role" value="<?= e((string)($r['role'] ?? 'Employee')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Department</label><input class="form-control" name="department" value="<?= e((string)$r['department']) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Designation</label><input class="form-control" name="designation" value="<?= e((string)($r['designation'] ?? '')) ?>"></div>
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
                            <div class="col-md-4"><label class="form-label">Basic Salary</label><input class="form-control" type="number" step="0.01" name="basic_salary" value="<?= e((string)($r['basic_salary'] ?? 0)) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= (($r['status'] ?? 'active')==='active')?'selected':'' ?>>active</option><option value="inactive" <?= (($r['status'] ?? '')==='inactive')?'selected':'' ?>>inactive</option></select></div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header section-title">Leave & Linking</div>
                        <div class="card-body row g-3">
                            <div class="col-md-4"><label class="form-label">Paid Leave</label><input class="form-control" type="number" step="0.01" name="paid_leave_limit" value="<?= e((string)($r['paid_leave_limit'] ?? 12)) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Half Paid Leave</label><input class="form-control" type="number" step="0.01" name="half_paid_leave_limit" value="<?= e((string)($r['half_paid_leave_limit'] ?? 6)) ?>"></div>
                            <div class="col-md-4"><label class="form-label">Linked User</label><select class="form-select" name="user_id"><option value="">No linked user</option><?php foreach ($employeeUsers as $employeeUser): ?><option value="<?= (int)$employeeUser['id'] ?>" <?= (int)$employeeUser['id'] === (int)($r['user_id'] ?? 0) ? 'selected' : '' ?>><?= e($employeeUser['full_name'] . ' - ' . $employeeUser['email']) ?></option><?php endforeach; ?></select></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade increment-modal" id="incEmp<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true" data-current-salary="<?= e((string)($r['basic_salary'] ?? 0)) ?>">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form method="post" class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Salary Increment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="increment">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <div class="card mb-3"><div class="card-body small"><div><strong><?= e((string)$r['full_name']) ?></strong></div><div class="text-muted"><?= e((string)$r['department']) ?> | <?= e((string)($r['employee_type'] ?? 'Staff')) ?></div><div class="mt-1">Current Salary: INR <?= e(number_format((float)($r['basic_salary'] ?? 0), 2)) ?></div></div></div>
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

