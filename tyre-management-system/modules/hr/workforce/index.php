<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/leave_service.php';

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();

$staffDate = (string)($_GET['staff_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $staffDate)) {
    $staffDate = date('Y-m-d');
}

$staffingRows = leave_department_staffing_overview($pdo, $staffDate);
$summary = leave_hr_dashboard_summary($pdo);
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$calendarEvents = leave_calendar_events($pdo, $month);

$cssPath = __DIR__ . '/../../../assets/css/leave-dashboard.css';
$cssVer = is_file($cssPath) ? (int)filemtime($cssPath) : time();
?>

<link href="assets/css/leave-dashboard.css?v=<?= e((string)$cssVer) ?>" rel="stylesheet">

<div class="leave-erp leave-erp--wf module-shell">
    <header class="leave-erp__header leave-erp__header--hr">
        <div>
            <h1 class="leave-erp__title">Workforce Overview</h1>
            <p class="leave-erp__subtitle">Department staffing · leave load · shortage analytics</p>
        </div>
        <form method="get" class="leave-erp__toolbar">
            <input type="hidden" name="page" value="hr/workforce">
            <label class="leave-field__label mb-0">Date</label>
            <input type="date" name="staff_date" class="form-control form-control-sm" value="<?= e($staffDate) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
        </form>
    </header>

    <div class="leave-erp__stats leave-erp__stats--compact">
        <div class="leave-kpi">
            <span class="leave-kpi__label">On leave today</span>
            <strong class="leave-kpi__value"><?= e((string)$summary['on_leave_today']) ?></strong>
        </div>
        <div class="leave-kpi leave-kpi--alert">
            <span class="leave-kpi__label">Critical departments</span>
            <strong class="leave-kpi__value"><?= e((string)$summary['critical_depts']) ?></strong>
        </div>
        <div class="leave-kpi leave-kpi--pending">
            <span class="leave-kpi__label">Pending leave</span>
            <strong class="leave-kpi__value"><?= e((string)$summary['pending']) ?></strong>
        </div>
        <div class="leave-kpi leave-kpi--ok">
            <span class="leave-kpi__label">Approved today</span>
            <strong class="leave-kpi__value"><?= e((string)$summary['approved_today']) ?></strong>
        </div>
    </div>

    <section class="leave-panel">
        <div class="leave-panel__head">
            <h2 class="leave-panel__title">Department staffing</h2>
            <span class="text-muted small"><?= e($staffDate) ?></span>
        </div>
        <div class="table-responsive leave-table-wrap">
            <table class="table leave-table mb-0">
                <thead>
                <tr>
                    <th>Department</th>
                    <th>Total</th>
                    <th>Present</th>
                    <th>On leave</th>
                    <th>Absent</th>
                    <th>Min required</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$staffingRows): ?>
                    <tr><td colspan="7" class="text-muted text-center py-3">No departments configured.</td></tr>
                <?php else: ?>
                    <?php foreach ($staffingRows as $sr): ?>
                        <?php $sb = leave_risk_badge((string)$sr['status']); ?>
                        <tr>
                            <td><strong><?= e((string)$sr['department_name']) ?></strong></td>
                            <td><?= (int)$sr['total'] ?></td>
                            <td><?= (int)$sr['present'] ?></td>
                            <td><?= (int)$sr['on_leave'] ?></td>
                            <td><?= (int)($sr['absent'] ?? 0) ?></td>
                            <td><?= (int)$sr['min_required'] ?></td>
                            <td><span class="<?= e($sb['class']) ?>"><?= e($sb['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="leave-panel">
        <div class="leave-panel__head">
            <h2 class="leave-panel__title">Leave load — <?= e($month) ?></h2>
            <form method="get" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="page" value="hr/workforce">
                <input type="hidden" name="staff_date" value="<?= e($staffDate) ?>">
                <input type="month" name="month" class="form-control form-control-sm" value="<?= e($month) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Go</button>
            </form>
        </div>
        <div class="table-responsive leave-table-wrap">
            <table class="table leave-table mb-0">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Allocation</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$calendarEvents): ?>
                    <tr><td colspan="6" class="text-muted text-center py-3">No leave in this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($calendarEvents as $ev): ?>
                        <?php
                        $lf = (string)($ev['from_date'] ?? $ev['start_date'] ?? '');
                        $lt = (string)($ev['to_date'] ?? $ev['end_date'] ?? '');
                        $cat = leave_category_badge((string)($ev['leave_category'] ?? 'Paid'));
                        $st = leave_status_badge(leave_display_status((string)($ev['status'] ?? '')));
                        ?>
                        <tr>
                            <td><?= e((string)$ev['full_name']) ?></td>
                            <td><?= e((string)($ev['department_name'] ?? '—')) ?></td>
                            <td class="text-nowrap"><?= e($lf === $lt ? $lf : $lf . ' → ' . $lt) ?></td>
                            <td><?= e(number_format((float)($ev['total_days'] ?? 0), 0)) ?></td>
                            <td><span class="<?= e($cat['class']) ?>"><?= e($cat['label']) ?></span></td>
                            <td><span class="<?= e($st['class']) ?>"><?= e($st['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <p class="text-muted small mb-0">
        <a href="<?= e(route_url('leave/list')) ?>">← Back to Leave Management</a>
    </p>
</div>
