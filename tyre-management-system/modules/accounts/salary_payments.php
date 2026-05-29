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
acc_salary_sync_batches_from_hr($pdo);

$monthYear = trim((string)($_GET['month_year'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
    $monthYear = date('Y-m');
}
$deptFilter = trim((string)($_GET['department'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['search'] ?? $_GET['q'] ?? $_GET['employee_id'] ?? ''));

$empFilters = array_filter([
    'status' => in_array($statusFilter, ['pending', 'partial', 'paid'], true) ? $statusFilter : null,
    'q' => $search !== '' ? $search : null,
], static fn($v) => $v !== null);

$kpis = acc_salary_dashboard_kpis($pdo, $monthYear);
$employees = acc_salary_list_employees($pdo, $monthYear, $deptFilter, $empFilters);
$deptSummary = acc_salary_department_summary($pdo, $monthYear);
$monthLabel = payroll_format_month_label($monthYear);
$deptOptions = array_values(array_unique(array_map(static fn($d) => (string)$d['department'], $deptSummary)));
sort($deptOptions);

$apiBase = route_url('api/accounts-salary-payment');
$exportQs = array_filter([
    'month_year' => $monthYear,
    'department' => $deptFilter,
    'status' => $statusFilter,
    'search' => $search,
    'export_scope' => 'employees',
], static fn($v) => $v !== '');

$pendingCount = count(array_filter($employees, static fn($e) => (string)($e['pay_status'] ?? '') !== 'paid'));

/**
 * @param array<string, mixed> $emp
 */
function sal_pay_render_actions(array $emp): string
{
    $sid = (int)$emp['id'];
    $paySt = (string)($emp['pay_status'] ?? '');
    $name = (string)($emp['full_name'] ?? '');
    $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 2) ?: 'EM');
    $isPaid = $paySt === 'paid';

    $payBtn = $isPaid
        ? '<span class="sal-btn-paid-done"><i class="bi bi-check-lg"></i> Paid</span>'
        : '<button type="button" class="btn sal-btn-pay js-sal-pay" data-salary-id="' . $sid . '" title="Pay Salary">'
            . '<i class="bi bi-cash-coin"></i><span class="sal-btn-pay-label">Pay Salary</span></button>';

    return '<td class="sal-row-actions">'
        . '<div class="sal-row-actions__inner">'
        . $payBtn
        . '<div class="dropdown sal-act-dropdown">'
        . '<button type="button" class="btn sal-btn-menu" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" title="More actions">'
        . '<i class="bi bi-three-dots-vertical"></i></button>'
        . '<ul class="dropdown-menu dropdown-menu-end sal-act-menu">'
        . '<li><button type="button" class="dropdown-item js-sal-profile" data-salary-id="' . $sid . '" data-initials="' . e($initials) . '">'
        . '<i class="bi bi-person"></i> View Profile</button></li>'
        . '<li><button type="button" class="dropdown-item js-sal-slip" data-salary-id="' . $sid . '">'
        . '<i class="bi bi-file-earmark-pdf"></i> Salary Slip</button></li>'
        . '<li><button type="button" class="dropdown-item js-sal-history" data-salary-id="' . $sid . '" data-employee-name="' . e($name) . '">'
        . '<i class="bi bi-clock-history"></i> Payment History</button></li>'
        . '<li><hr class="dropdown-divider"></li>'
        . '<li><button type="button" class="dropdown-item js-sal-slip-dl" data-salary-id="' . $sid . '">'
        . '<i class="bi bi-download"></i> Download PDF</button></li>'
        . '<li><button type="button" class="dropdown-item js-sal-slip-print" data-salary-id="' . $sid . '">'
        . '<i class="bi bi-printer"></i> Print</button></li>'
        . '</ul></div></div></td>';
}
?>

<div class="accounts-page sal-pay-page">
    <header class="sal-pay-header">
        <div>
            <h1>Salary Payments</h1>
            <p>Manage employee salary payouts received from HR payroll processing.</p>
        </div>
        <div class="sal-pay-header__actions">
            <?= erp_salary_export_toolbar('accounts/salary-payments', $exportQs, 'salary-employees') ?>
        </div>
    </header>

    <div class="sal-pay-kpis" id="salPayKpis">
        <article class="sal-pay-kpi">
            <span class="sal-pay-kpi__icon sal-pay-kpi__icon--total"><i class="bi bi-wallet2"></i></span>
            <div>
                <span class="sal-pay-kpi__label">Total Payroll</span>
                <strong class="sal-pay-kpi__value" id="kpiTotal"><?= e(sales_format_money((float)$kpis['total_payroll'])) ?></strong>
            </div>
        </article>
        <article class="sal-pay-kpi sal-pay-kpi--paid">
            <span class="sal-pay-kpi__icon sal-pay-kpi__icon--paid"><i class="bi bi-check-circle"></i></span>
            <div>
                <span class="sal-pay-kpi__label">Paid Salary</span>
                <strong class="sal-pay-kpi__value" id="kpiPaid"><?= e(sales_format_money((float)$kpis['paid_salary'])) ?></strong>
            </div>
        </article>
        <article class="sal-pay-kpi sal-pay-kpi--pending">
            <span class="sal-pay-kpi__icon sal-pay-kpi__icon--pending"><i class="bi bi-exclamation-circle"></i></span>
            <div>
                <span class="sal-pay-kpi__label">Pending Salary</span>
                <strong class="sal-pay-kpi__value" id="kpiPending"><?= e(sales_format_money((float)$kpis['pending_salary'])) ?></strong>
            </div>
        </article>
        <article class="sal-pay-kpi sal-pay-kpi--paid">
            <span class="sal-pay-kpi__icon sal-pay-kpi__icon--paid"><i class="bi bi-people"></i></span>
            <div>
                <span class="sal-pay-kpi__label">Employees Paid</span>
                <strong class="sal-pay-kpi__value" id="kpiEmpPaid"><?= (int)$kpis['employees_paid'] ?></strong>
            </div>
        </article>
        <article class="sal-pay-kpi sal-pay-kpi--pending">
            <span class="sal-pay-kpi__icon sal-pay-kpi__icon--pending"><i class="bi bi-person-dash"></i></span>
            <div>
                <span class="sal-pay-kpi__label">Employees Pending</span>
                <strong class="sal-pay-kpi__value" id="kpiEmpPending"><?= (int)$kpis['employees_pending'] ?></strong>
            </div>
        </article>
    </div>

    <form method="get" class="sal-pay-filter-sticky" id="salPayFilters">
        <input type="hidden" name="page" value="accounts/salary-payments">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-lg-2">
                <label class="form-label">Payroll Month</label>
                <input type="month" class="form-control form-control-sm" name="month_year" value="<?= e($monthYear) ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Department</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">All departments</option>
                    <?php foreach ($deptOptions as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $deptFilter === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Payment Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Employee Search</label>
                <input type="search" class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Name or employee ID">
            </div>
            <div class="col-auto">
                <label class="form-label d-none d-lg-block">&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </div>
            <div class="col-auto">
                <label class="form-label d-none d-lg-block">&nbsp;</label>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('accounts/salary-payments')) ?>">Reset</a>
            </div>
        </div>
    </form>

    <section class="sal-pay-queue">
        <div class="sal-pay-queue__head">
            <h2>Employee Salary Queue</h2>
            <p class="sal-pay-queue__meta"><?= e($monthLabel) ?> · <?= count($employees) ?> employees · <strong><?= $pendingCount ?></strong> need payment</p>
        </div>
        <div class="table-responsive sal-pay-queue__table-wrap">
            <table class="table table-sm table-hover mb-0 sal-pay-data-table" id="sal-pay-employee-table">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th class="text-end">Salary</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Pending</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($employees === []): ?>
                    <tr class="sal-pay-empty"><td colspan="9">No payroll from HR for selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($employees as $emp):
                    $sid = (int)$emp['id'];
                    $paySt = (string)$emp['pay_status'];
                    $meta = $emp['pay_status_meta'] ?? acc_salary_status_meta($paySt);
                    $badgeKey = (string)($meta['badge'] ?? 'unpaid');
                    if ($badgeKey === 'unpaid') {
                        $badgeKey = 'pending';
                    }
                    $net = (float)$emp['net_salary'];
                    $paid = (float)$emp['amount_paid'];
                    $pct = $net > 0 ? min(100, (int)round(($paid / $net) * 100)) : 0;
                    $progClass = match ($paySt) {
                        'paid' => 'sal-progress__bar--paid',
                        'partial' => 'sal-progress__bar--partial',
                        default => 'sal-progress__bar--pending',
                    };
                    $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)$emp['full_name']), 0, 2) ?: 'EM');
                ?>
                    <tr data-salary-id="<?= $sid ?>" data-pay-status="<?= e($paySt) ?>" data-initials="<?= e($initials) ?>" data-employee-name="<?= e((string)$emp['full_name']) ?>">
                        <td>
                            <div class="sal-emp-cell">
                                <span class="sal-emp-avatar" aria-hidden="true"><?= e($initials) ?></span>
                                <div>
                                    <div class="sal-emp-name"><?= e((string)$emp['full_name']) ?></div>
                                    <div class="sal-emp-code"><?= e((string)$emp['employee_code']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="sal-col-dept"><?= e((string)($emp['dept_label'] ?? '—')) ?></td>
                        <td class="sal-col-desig"><?= e((string)($emp['desig_label'] ?? '—')) ?></td>
                        <td class="text-end sal-col-money"><?= e(sales_format_money($net)) ?></td>
                        <td class="text-end sal-col-money sal-col-paid js-emp-paid"><?= e(sales_format_money($paid)) ?></td>
                        <td class="text-end sal-col-money sal-col-pend js-emp-pending"><?= e(sales_format_money((float)$emp['pending'])) ?></td>
                        <td><span class="sal-badge sal-badge--<?= e($badgeKey) ?> js-emp-status"><?= e((string)$meta['label']) ?></span></td>
                        <td>
                            <div class="sal-progress-wrap">
                                <div class="sal-progress">
                                    <div class="sal-progress__track">
                                        <div class="sal-progress__bar <?= e($progClass) ?> js-emp-progress-bar" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <span class="sal-progress__pct js-emp-progress-pct"><?= $pct ?>%</span>
                                </div>
                            </div>
                        </td>
                        <?= sal_pay_render_actions($emp) ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($deptSummary !== []): ?>
    <div class="accordion sal-pay-dept-collapse" id="salPayDeptAccordion">
        <div class="accordion-item border-0">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#salPayDeptCollapse" aria-expanded="false">
                    <i class="bi bi-chevron-down me-2"></i> Department Payroll Summary
                    <span class="text-muted fw-normal ms-1">(<?= count($deptSummary) ?> departments)</span>
                </button>
            </h2>
            <div id="salPayDeptCollapse" class="accordion-collapse collapse" data-bs-parent="#salPayDeptAccordion">
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 sal-pay-data-table">
                            <thead>
                            <tr>
                                <th>Department</th>
                                <th class="text-center">Employees</th>
                                <th class="text-end">Payroll</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Pending</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($deptSummary as $dept): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string)$dept['department']) ?></td>
                                    <td class="text-center"><?= (int)$dept['employees'] ?></td>
                                    <td class="text-end sal-col-money"><?= e(sales_format_money((float)$dept['total_payroll'])) ?></td>
                                    <td class="text-end sal-col-paid"><?= e(sales_format_money((float)$dept['paid'])) ?></td>
                                    <td class="text-end sal-col-pend"><?= e(sales_format_money((float)$dept['pending'])) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-link btn-sm p-0" href="<?= e(route_url('accounts/salary-payments', ['month_year' => $monthYear, 'department' => (string)$dept['department']])) ?>">Filter queue</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/accounts_salary_pay_ui.php'; ?>
<script>
(function () {
  const API = <?= json_encode($apiBase, JSON_THROW_ON_ERROR) ?>;
  const fmt = (n) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(n || 0));

  function updateKpis(d) {
    if (!d) return;
    const set = (id, v, money) => {
      const el = document.getElementById(id);
      if (el) el.textContent = money ? fmt(v) : String(v);
    };
    set('kpiTotal', d.total_payroll, true);
    set('kpiPaid', d.paid_salary, true);
    set('kpiPending', d.pending_salary, true);
    set('kpiEmpPaid', d.employees_paid, false);
    set('kpiEmpPending', d.employees_pending, false);
  }

  function boot() {
    if (typeof window.initAccountsSalaryPayUi === 'function') {
      window.initAccountsSalaryPayUi(API, { onDashboardUpdate: updateKpis });
    }
  }
  if (document.readyState === 'complete') boot();
  else window.addEventListener('load', boot, { once: true });
})();
</script>
