<?php
declare(strict_types=1);
/** @var array<string,mixed> $profile */
/** @var array<string,mixed> $filters */
$e = $profile['employee'];
$att = $profile['attendance'];
$leave = $profile['leave'];
$increments = $profile['increments'];
$empId = (int)($e['id'] ?? 0);
$initials = strtoupper(substr((string)($e['full_name'] ?? '?'), 0, 1));
$status = strtolower((string)($e['status'] ?? 'active'));
$payrollAuto = employee_payroll_auto_indian($e);
$pdfUrl = emp_profile_print_url($filters, $empId, 'pdf');
$printUrl = emp_profile_print_url($filters, $empId, 'print');
?>
<div class="emp-drawer__hero">
    <span class="employee-avatar employee-avatar--lg"><?= e($initials) ?></span>
    <div class="min-w-0 flex-grow-1">
        <h5 class="emp-drawer__name mb-1"><?= e((string)$e['full_name']) ?></h5>
        <div class="emp-drawer__meta">
            <span class="font-monospace"><?= e((string)$e['employee_code']) ?></span>
            <span class="emp-meta-dot">·</span>
            <span class="badge <?= $status === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= e(ucfirst($status)) ?></span>
            <span class="badge <?= ($e['employee_type'] ?? '') === 'Worker' ? 'badge-worker' : 'badge-staff' ?>"><?= e((string)($e['employee_type'] ?? 'Staff')) ?></span>
        </div>
    </div>
</div>

<div class="emp-drawer__actions d-flex flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editEmp<?= $empId ?>"><i class="bi bi-pencil me-1"></i>Edit employee</button>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($printUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($pdfUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-file-pdf me-1"></i>Download PDF</a>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#incEmp<?= $empId ?>"><i class="bi bi-graph-up-arrow me-1"></i>Increment</button>
</div>

<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Personal information</h6>
    <dl class="emp-drawer-dl">
        <div><dt>Father name</dt><dd><?= e((string)($e['father_name'] ?? '—')) ?></dd></div>
        <div><dt>Date of birth</dt><dd><?= e((string)($e['dob'] ?? '—')) ?></dd></div>
        <div><dt>Aadhaar</dt><dd><?= e(ec_mask_aadhaar($e['aadhaar_number'] ?? null)) ?></dd></div>
        <div><dt>Phone</dt><dd><?= e((string)($e['contact_no'] ?? '—')) ?></dd></div>
        <div class="emp-drawer-dl--full"><dt>Address</dt><dd><?= e((string)($e['address'] ?? '—')) ?></dd></div>
    </dl>
</div>

<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Organization details</h6>
    <dl class="emp-drawer-dl">
        <div><dt>Category</dt><dd><?= e((string)($e['dept_category_name'] ?? '—')) ?></dd></div>
        <div><dt>Department</dt><dd><?= e(emp_list_row_department($e)) ?></dd></div>
        <div><dt>Designation</dt><dd><?= e(emp_list_row_designation($e)) ?></dd></div>
        <div><dt>Job role</dt><dd><?= e((string)($e['role'] ?? '—')) ?></dd></div>
        <div><dt>Employee type</dt><dd><?= e((string)($e['employee_type'] ?? '—')) ?></dd></div>
    </dl>
</div>

<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Shift &amp; work</h6>
    <dl class="emp-drawer-dl">
        <div><dt>Shift</dt><dd><?= e((string)($e['shift_timing'] ?? '—')) ?></dd></div>
        <div><dt>Shift timing</dt><dd><?= e(trim((string)($e['shift_start'] ?? '') . ' – ' . (string)($e['shift_end'] ?? ''), ' –') ?: '—') ?></dd></div>
        <div><dt>Salary type</dt><dd><?= e((string)($e['salary_type'] ?? '—')) ?></dd></div>
        <div><dt>Joining date</dt><dd><?= e((string)($e['joining_date'] ?? '—')) ?></dd></div>
    </dl>
</div>

<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Payroll summary</h6>
    <dl class="emp-drawer-dl">
        <div><dt>Monthly gross</dt><dd class="text-danger fw-semibold">₹<?= e(number_format((float)$profile['gross'], 0)) ?></dd></div>
        <div><dt>Mode</dt><dd><?= $payrollAuto ? 'Auto (Indian split)' : 'Manual' ?></dd></div>
        <div><dt>Basic / DA</dt><dd>₹<?= e(number_format((float)($e['basic_salary'] ?? 0), 0)) ?> / ₹<?= e(number_format((float)($e['dearness_allowance'] ?? 0), 0)) ?></dd></div>
        <div><dt>HRA / Medical</dt><dd>₹<?= e(number_format((float)($e['hra_amount'] ?? 0), 0)) ?> / ₹<?= e(number_format((float)($e['medical_allowance'] ?? 0), 0)) ?></dd></div>
        <div><dt>PF / ESI</dt><dd><?= !empty($e['pf_applicable']) ? 'PF ' . e((string)($e['pf_percentage'] ?? '12')) . '%' : 'No PF' ?> · <?= !empty($e['esi_applicable']) ? 'ESI' : 'No ESI' ?></dd></div>
    </dl>
</div>

<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Attendance summary (<?= e((string)$att['label']) ?>)</h6>
    <dl class="emp-drawer-dl">
        <div><dt>Present days</dt><dd><?= e((string)$att['present_days']) ?> / <?= e((string)$att['total_days']) ?></dd></div>
        <div><dt>Attendance %</dt><dd><strong><?= e((string)$att['present_pct']) ?>%</strong></dd></div>
        <div><dt>Overtime hours</dt><dd><?= e(number_format((float)$att['ot_hours'], 1)) ?> h</dd></div>
    </dl>
</div>

<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Machine assignment</h6>
    <p class="mb-0 small"><?= e((string)$profile['machine']) !== '—' ? (string)$profile['machine'] : 'No active machine assignment.' ?></p>
</div>

<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Leave balance (<?= e(date('F Y')) ?>)</h6>
    <dl class="emp-drawer-dl">
        <div><dt>Paid leave</dt><dd><?= e(number_format((float)$leave['paid_used'], 1)) ?> used · <?= e(number_format((float)$leave['paid_balance'], 1)) ?> left</dd></div>
        <div><dt>Half paid</dt><dd><?= e(number_format((float)$leave['half_paid_used'], 1)) ?> used · <?= e(number_format((float)$leave['half_paid_balance'], 1)) ?> left</dd></div>
    </dl>
</div>

<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Login access</h6>
    <dl class="emp-drawer-dl">
        <div><dt>Username</dt><dd class="font-monospace"><?= e((string)($e['login_username'] ?: '—')) ?></dd></div>
        <div><dt>Email</dt><dd><?= e((string)($e['user_email'] ?? $e['email'] ?? '—')) ?></dd></div>
    </dl>
    <?php if ((int)($e['user_id'] ?? 0) > 0): ?>
    <form method="post" class="mt-2" onsubmit="return confirm('Issue a new temporary password?');">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="reset_employee_login">
        <input type="hidden" name="id" value="<?= $empId ?>">
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-key me-1"></i>Reset login</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($increments): ?>
<div class="emp-drawer-section">
    <h6 class="emp-drawer-section__title">Salary increment history</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 emp-drawer-inc-table">
            <thead><tr><th>Old</th><th>New</th><th>%</th><th>Effective</th></tr></thead>
            <tbody>
            <?php foreach ($increments as $inc): ?>
                <tr>
                    <td><?= e(number_format((float)$inc['old_salary'], 0)) ?></td>
                    <td><?= e(number_format((float)$inc['new_salary'], 0)) ?></td>
                    <td><?= e((string)$inc['increment_percentage']) ?></td>
                    <td><?= e((string)$inc['effective_date']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
