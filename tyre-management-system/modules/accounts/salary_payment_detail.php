<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_salary_payroll.php';
require_once __DIR__ . '/../../includes/erp_salary_payroll_print.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin'])) {
    echo '<div class="alert alert-warning m-3">Access denied.</div>';
    return;
}

$pdo = Database::connection();
acc_salary_ensure_schema($pdo);
erp_salary_payroll_handle_export($pdo);

$monthYear = trim((string)($_GET['month_year'] ?? ''));
$department = trim((string)($_GET['department'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}$/', $monthYear) || $department === '') {
    echo '<div class="alert alert-warning m-3">Invalid request. <a href="' . e(route_url('accounts/salary-payments')) . '">Back to Salary Payments</a></div>';
    return;
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$empIdFilter = trim((string)($_GET['employee_id'] ?? ''));
$empFilters = array_filter([
    'status' => in_array($statusFilter, ['pending', 'partial', 'paid'], true) ? $statusFilter : null,
    'q' => $q !== '' ? $q : null,
    'employee_id' => $empIdFilter !== '' ? $empIdFilter : null,
], static fn($v) => $v !== null);

$employees = acc_salary_list_employees($pdo, $monthYear, $department, $empFilters);
$monthLabel = payroll_format_month_label($monthYear);
$totPayroll = 0.0;
$totPaid = 0.0;
foreach ($employees as $e) {
    $totPayroll += (float)$e['net_salary'];
    $totPaid += (float)$e['amount_paid'];
}
$totPending = round(max(0, $totPayroll - $totPaid), 2);
$apiBase = route_url('api/accounts-salary-payment');
$backUrl = route_url('accounts/salary-payments', ['month_year' => $monthYear, 'department' => $department]);
$exportQs = array_filter([
    'month_year' => $monthYear,
    'department' => $department,
    'status' => $statusFilter,
    'q' => $q,
    'employee_id' => $empIdFilter,
    'export_scope' => 'detail',
], static fn($v) => $v !== '');
?>

<div class="accounts-page sal-pay-page">
    <div class="mb-3">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/salary-payments', ['month_year' => $monthYear])) ?>"><i class="bi bi-arrow-left"></i> All employees</a>
    </div>
    <header class="prod-page__head mb-3">
        <h1 class="prod-page__title h4">Payroll details</h1>
        <p class="prod-page__sub mb-0"><?= e($department) ?> · <?= e($monthLabel) ?></p>
    </header>

    <div class="sal-pay-detail-head">
        <div class="sal-pay-detail-card"><span>Department</span><strong><?= e($department) ?></strong></div>
        <div class="sal-pay-detail-card"><span>Payroll month</span><strong><?= e($monthLabel) ?></strong></div>
        <div class="sal-pay-detail-card"><span>Employees</span><strong><?= count($employees) ?></strong></div>
        <div class="sal-pay-detail-card"><span>Total payroll</span><strong><?= e(sales_format_money($totPayroll)) ?></strong></div>
        <div class="sal-pay-detail-card"><span>Paid</span><strong class="text-success"><?= e(sales_format_money($totPaid)) ?></strong></div>
        <div class="sal-pay-detail-card"><span>Pending</span><strong class="text-danger"><?= e(sales_format_money($totPending)) ?></strong></div>
    </div>

    <form method="get" class="sal-pay-filter mb-3">
        <input type="hidden" name="page" value="accounts/salary-payment-detail">
        <input type="hidden" name="month_year" value="<?= e($monthYear) ?>">
        <input type="hidden" name="department" value="<?= e($department) ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="small">Employee name</label><input type="search" class="form-control form-control-sm" name="q" value="<?= e($q) ?>"></div>
            <div class="col-md-2"><label class="small">Employee ID</label><input type="search" class="form-control form-control-sm" name="employee_id" value="<?= e($empIdFilter) ?>"></div>
            <div class="col-md-2"><label class="small">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div class="col-md-5 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-sm" type="submit">Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('accounts/salary-payment-detail', ['month_year' => $monthYear, 'department' => $department])) ?>">Reset</a>
                <?= erp_salary_export_toolbar('accounts/salary-payment-detail', $exportQs, 'payroll-detail-' . $monthYear) ?>
            </div>
        </div>
    </form>

    <div class="sal-pay-table-wrap sal-pay-main-panel">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 sal-pay-data-table" id="sal-pay-emp-table">
                <thead class="table-light">
                <tr>
                    <th>Employee ID</th>
                    <th>Employee name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th class="text-end">Salary</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Pending</th>
                    <th>Status</th>
                    <th class="text-end sal-pay-actions-col">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $emp):
                    $sid = (int)$emp['id'];
                    $meta = $emp['pay_status_meta'] ?? acc_salary_status_meta((string)$emp['pay_status']);
                    $badgeKey = (string)($meta['badge'] ?? 'unpaid');
                    if ($badgeKey === 'unpaid') {
                        $badgeKey = 'pending';
                    }
                    $isPaid = (string)$emp['pay_status'] === 'paid';
                    $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)$emp['full_name']), 0, 2) ?: 'EM');
                ?>
                    <tr data-salary-id="<?= $sid ?>">
                        <td class="font-monospace small"><?= e((string)$emp['employee_code']) ?></td>
                        <td class="fw-semibold"><?= e((string)$emp['full_name']) ?></td>
                        <td><?= e((string)($emp['dept_label'] ?? $department)) ?></td>
                        <td class="small text-muted"><?= e((string)($emp['desig_label'] ?? '—')) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$emp['net_salary'])) ?></td>
                        <td class="text-end text-success js-emp-paid"><?= e(sales_format_money((float)$emp['amount_paid'])) ?></td>
                        <td class="text-end text-danger js-emp-pending"><?= e(sales_format_money((float)$emp['pending'])) ?></td>
                        <td><span class="sal-badge sal-badge--<?= e($badgeKey) ?> js-emp-status"><?= e((string)$meta['label']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <div class="sal-table-actions">
                                <button type="button" class="btn btn-link btn-sm p-0 js-sal-profile" data-salary-id="<?= $sid ?>" data-initials="<?= e($initials) ?>">Profile</button>
                                <button type="button" class="btn btn-link btn-sm p-0 js-sal-slip" data-salary-id="<?= $sid ?>">Slip</button>
                                <button type="button" class="btn btn-link btn-sm p-0 js-sal-history" data-salary-id="<?= $sid ?>" data-employee-name="<?= e((string)$emp['full_name']) ?>">History</button>
                                <?php if (!$isPaid): ?>
                                    <button type="button" class="btn btn-link btn-sm p-0 fw-semibold js-sal-pay sal-act-pay" data-salary-id="<?= $sid ?>">Pay</button>
                                <?php else: ?>
                                    <span class="text-muted small">Paid</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($employees === []): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No employees match filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/accounts_salary_pay_ui.php'; ?>
<script>
(function () {
  const API = <?= json_encode($apiBase, JSON_THROW_ON_ERROR) ?>;
  function boot() {
    if (typeof window.initAccountsSalaryPayUi === 'function') window.initAccountsSalaryPayUi(API);
  }
  if (document.readyState === 'complete') boot();
  else window.addEventListener('load', boot, { once: true });
})();
</script>
