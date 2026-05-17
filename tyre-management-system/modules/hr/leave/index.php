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
verify_csrf();

$approverId = (int)(current_user()['id'] ?? $_SESSION['user_id'] ?? 0);
$staffDate = (string)($_GET['staff_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $staffDate)) {
    $staffDate = date('Y-m-d');
}
$tab = (string)($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'requests'], true)) {
    $tab = 'overview';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $employeeId = post_int('employee_id');
        $from = (string)($_POST['from_date'] ?? '');
        $to = (string)($_POST['to_date'] ?? '');
        if ($to === '' && $from !== '') {
            $to = $from;
        }
        $reason = trim((string)($_POST['reason'] ?? ''));
        $isEmergency = !empty($_POST['is_emergency']);
        $res = leave_apply($pdo, $employeeId, $from, $to, $reason !== '' ? $reason : 'HR entry', $isEmergency);
        set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
    } elseif ($action === 'approve') {
        $res = leave_approve($pdo, post_int('id'), $approverId);
        set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
    } elseif ($action === 'reject') {
        $res = leave_reject($pdo, post_int('id'), $approverId, post_string('rejection_reason', 255));
        set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
    }
    redirect('leave/list');
}

$summary = leave_hr_dashboard_summary($pdo);
$staffingRows = leave_department_staffing_overview($pdo, $staffDate);
$pending = leave_pending_requests($pdo, 50);
$emps = $pdo->query("SELECT id, full_name, employee_code FROM employees WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$rows = $pdo->query("SELECT l.*, e.full_name, e.employee_code, d.department_name, u.full_name AS approver_name,
    COALESCE(l.from_date, l.start_date) AS leave_from,
    COALESCE(l.to_date, l.end_date) AS leave_to
    FROM leaves l
    JOIN employees e ON e.id = l.employee_id
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN users u ON u.id = l.approved_by
    ORDER BY FIELD(l.status, 'Pending', 'Applied', 'Approved', 'Rejected'), l.id DESC
    LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

$cssPath = __DIR__ . '/../../../assets/css/leave-dashboard.css';
$cssVer = is_file($cssPath) ? (int)filemtime($cssPath) : time();
$jsPath = __DIR__ . '/../../../assets/js/leave-dashboard.js';
$jsVer = is_file($jsPath) ? (int)filemtime($jsPath) : time();
$baseUrl = route_url('leave/list');
?>

<link href="assets/css/leave-dashboard.css?v=<?= e((string)$cssVer) ?>" rel="stylesheet">

<div class="leave-erp module-shell">
    <header class="leave-erp__header leave-erp__header--hr">
        <div>
            <h1 class="leave-erp__title">Leave & workforce</h1>
            <p class="leave-erp__subtitle">Staffing-aware approvals · payroll-linked leave types</p>
        </div>
        <form method="get" class="leave-erp__toolbar">
            <input type="hidden" name="page" value="leave/list">
            <label class="leave-field__label mb-0">Staffing date</label>
            <input type="date" name="staff_date" class="form-control form-control-sm" value="<?= e($staffDate) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
        </form>
    </header>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> py-2 mb-3"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="leave-erp__stats leave-erp__stats--hr">
        <div class="leave-stat leave-stat--pending">
            <span class="leave-stat__k">Pending</span>
            <strong><?= e((string)$summary['pending']) ?></strong>
        </div>
        <div class="leave-stat">
            <span class="leave-stat__k">Approved today</span>
            <strong><?= e((string)$summary['approved_today']) ?></strong>
        </div>
        <div class="leave-stat">
            <span class="leave-stat__k">On leave today</span>
            <strong><?= e((string)$summary['on_leave_today']) ?></strong>
        </div>
        <div class="leave-stat leave-stat--critical">
            <span class="leave-stat__k">Critical depts</span>
            <strong><?= e((string)$summary['critical_depts']) ?></strong>
            <?php if ((int)($summary['warning_depts'] ?? 0) > 0): ?>
                <small class="text-warning-emphasis"><?= (int)$summary['warning_depts'] ?> warning</small>
            <?php endif; ?>
        </div>
    </div>

    <section class="leave-card leave-card--compact">
        <h2 class="leave-card__title">Record leave (HR)</h2>
        <form method="post" class="leave-apply-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <div class="leave-apply-row">
                <div class="leave-field leave-field--emp">
                    <label class="leave-field__label">Employee</label>
                    <select class="form-select form-select-sm" name="employee_id" required>
                        <?php foreach ($emps as $emp): ?>
                            <option value="<?= (int)$emp['id'] ?>"><?= e((string)$emp['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="leave-field leave-field--type">
                    <label class="leave-field__label" for="hr_leave_duration_mode">Type</label>
                    <select class="form-select form-select-sm leave-duration-select" name="leave_duration_mode" id="hr_leave_duration_mode">
                        <option value="single" selected>Single day</option>
                        <option value="multiple">Multiple days</option>
                    </select>
                </div>
                <div class="leave-field leave-apply-form__from">
                    <label class="leave-field__label leave-apply-form__from-label">Leave date</label>
                    <input class="form-control form-control-sm" type="date" name="from_date" required>
                </div>
                <div class="leave-field leave-apply-form__to is-hidden">
                    <label class="leave-field__label">To date</label>
                    <input class="form-control form-control-sm" type="date" name="to_date">
                </div>
                <div class="leave-field leave-field--grow">
                    <label class="leave-field__label">Reason</label>
                    <input class="form-control form-control-sm" name="reason" placeholder="Reason" required>
                </div>
            </div>
            <div class="leave-apply-row leave-apply-row--actions">
                <label class="leave-check"><input type="checkbox" name="is_emergency" value="1"><span>Emergency</span></label>
                <span class="leave-days-pill is-hidden leave-duration-summary__text" aria-live="polite"></span>
                <button type="submit" class="btn btn-sm btn-danger">Save</button>
            </div>
        </form>
    </section>

    <section class="leave-card">
        <div class="leave-card__head">
            <h2 class="leave-card__title mb-0">Department staffing</h2>
            <span class="text-muted small"><?= e($staffDate) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table leave-erp-table table-sm mb-0">
                <thead>
                <tr>
                    <th>Department</th>
                    <th>Total</th>
                    <th>Present</th>
                    <th>On leave</th>
                    <th>Min required</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$staffingRows): ?>
                    <tr><td colspan="6" class="text-muted text-center py-2">No departments configured.</td></tr>
                <?php else: ?>
                    <?php foreach ($staffingRows as $sr): ?>
                        <?php $sb = leave_risk_badge((string)$sr['status']); ?>
                        <tr>
                            <td><?= e((string)$sr['department_name']) ?></td>
                            <td><?= (int)$sr['total'] ?></td>
                            <td><?= (int)$sr['present'] ?></td>
                            <td><?= (int)$sr['on_leave'] ?></td>
                            <td><?= (int)$sr['min_required'] ?></td>
                            <td><span class="<?= e($sb['class']) ?>"><?= e($sb['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($pending): ?>
    <section class="leave-card">
        <h2 class="leave-card__title">Pending approval <span class="leave-count"><?= count($pending) ?></span></h2>
        <div class="table-responsive">
            <table class="table leave-erp-table table-sm mb-0">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Staffing</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $p): ?>
                    <?php
                    $rb = leave_risk_badge((string)($p['staffing_risk'] ?? 'Safe'));
                    $lf = (string)($p['leave_from'] ?? '');
                    $lt = (string)($p['leave_to'] ?? '');
                    ?>
                    <tr class="<?= !empty($p['is_emergency']) ? 'leave-row--emergency' : '' ?>">
                        <td>
                            <strong><?= e((string)$p['full_name']) ?></strong>
                            <span class="text-muted small d-block"><?= e((string)($p['reason'] ?? '')) ?></span>
                        </td>
                        <td><?= e((string)($p['department_name'] ?? '—')) ?></td>
                        <td class="text-nowrap"><?= e($lf === $lt ? $lf : $lf . ' → ' . $lt) ?></td>
                        <td><?= e(number_format((float)($p['total_days'] ?? 0), 0)) ?></td>
                        <td><span class="<?= e($rb['class']) ?>"><?= e($rb['label']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <form method="post" class="d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-success">Approve</button></form>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#leaveRejectModal" data-leave-id="<?= (int)$p['id'] ?>" data-employee-name="<?= e((string)$p['full_name']) ?>">Reject</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="leave-card">
        <h2 class="leave-card__title">All requests</h2>
        <div class="table-responsive">
            <table class="table leave-erp-table table-sm mb-0">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Type</th>
                    <th>Staffing</th>
                    <th>Status</th>
                    <th>Approved by</th>
                    <th>Payroll</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $st = leave_display_status((string)($r['status'] ?? 'Pending'));
                    $badge = leave_status_badge($st);
                    $rb = leave_risk_badge((string)($r['staffing_risk'] ?? 'Safe'));
                    $isPending = in_array((string)($r['status'] ?? ''), ['Pending', 'Applied'], true);
                    $lf = (string)($r['leave_from'] ?? '');
                    $lt = (string)($r['leave_to'] ?? '');
                    $approver = (string)($r['approver_name'] ?? '');
                    if ($approver === '' && !empty($r['auto_approved'])) {
                        $approver = 'System';
                    }
                    if ($approver === '') {
                        $approver = '—';
                    }
                    ?>
                    <tr>
                        <td><?= e((string)$r['full_name']) ?></td>
                        <td><?= e((string)($r['department_name'] ?? '—')) ?></td>
                        <td class="text-nowrap small"><?= e($lf === $lt ? $lf : $lf . ' → ' . $lt) ?></td>
                        <td><?= e(number_format((float)($r['total_days'] ?? 0), 0)) ?></td>
                        <td><span class="leave-tag"><?= e((string)($r['leave_category'] ?? '—')) ?></span></td>
                        <td><span class="<?= e($rb['class']) ?>"><?= e($rb['label']) ?></span></td>
                        <td><span class="<?= e($badge['class']) ?>"><?= e($badge['label']) ?></span></td>
                        <td class="small text-muted"><?= e($approver) ?></td>
                        <td class="small text-muted"><?= e(leave_payroll_impact($r)) ?></td>
                        <td class="text-end">
                            <?php if ($isPending): ?>
                                <form method="post" class="d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-success py-0">OK</button></form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="leaveRejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="post" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="">
            <div class="modal-header py-2">
                <h6 class="modal-title">Reject — <span class="js-reject-employee"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <textarea class="form-control form-control-sm" name="rejection_reason" rows="2" required placeholder="Staffing / production reason"></textarea>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/leave-dashboard.js?v=<?= e((string)$jsVer) ?>"></script>
