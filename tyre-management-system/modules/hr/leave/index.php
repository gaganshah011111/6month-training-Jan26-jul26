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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'manual') {
        $employeeId = post_int('employee_id');
        $from = (string)($_POST['from_date'] ?? '');
        $to = (string)($_POST['to_date'] ?? '');
        if ($to === '' && $from !== '') {
            $to = $from;
        }
        $reason = trim((string)($_POST['reason'] ?? ''));
        $isEmergency = !empty($_POST['is_emergency']);
        $res = leave_apply_hr_manual($pdo, $employeeId, $from, $to, $reason !== '' ? $reason : 'Manual HR entry', $approverId, $isEmergency);
        set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
    } elseif ($action === 'approve') {
        $res = leave_approve($pdo, post_int('id'), $approverId);
        set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
    } elseif ($action === 'reject') {
        $res = leave_reject($pdo, post_int('id'), $approverId, post_string('rejection_reason', 255));
        set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
    } elseif ($action === 'convert_unpaid') {
        $res = leave_convert_to_unpaid($pdo, post_int('id'), $approverId);
        set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
    }
    redirect('leave/list');
}

$summary = leave_hr_dashboard_summary($pdo);
$staffingNotice = leave_top_staffing_notice($pdo);

$filterDept = (int)($_GET['department_id'] ?? 0);
$filterStatus = trim((string)($_GET['status'] ?? ''));
$filterDate = (string)($_GET['date'] ?? '');
$filterSearch = trim((string)($_GET['q'] ?? ''));

$filters = array_filter([
    'department_id' => $filterDept > 0 ? $filterDept : null,
    'status' => $filterStatus !== '' ? $filterStatus : null,
    'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate) ? $filterDate : null,
    'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate) ? $filterDate : null,
    'search' => $filterSearch !== '' ? $filterSearch : null,
], static fn ($v) => $v !== null && $v !== '');

$rows = leave_fetch_hr_requests($pdo, $filters, 120);
$emps = $pdo->query("SELECT id, full_name, employee_code FROM employees WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query('SELECT id, department_name FROM departments ORDER BY department_name')->fetchAll(PDO::FETCH_ASSOC);

$jsPath = __DIR__ . '/../../../assets/js/leave-dashboard.js';
$jsVer = is_file($jsPath) ? (int)filemtime($jsPath) : time();
$listUrl = route_url('leave/list');
?>

<div class="hr-page leave-erp leave-erp--hr module-shell">
    <header class="leave-erp__header">
        <div>
            <h1 class="leave-erp__title">Leave Management</h1>
        </div>
    </header>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> py-2 mb-2"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($staffingNotice): ?>
        <div class="leave-notice"><?= e($staffingNotice) ?></div>
    <?php endif; ?>

    <div class="leave-kpis">
        <div class="leave-kpi"><span>Pending</span><strong><?= e((string)$summary['pending']) ?></strong></div>
        <div class="leave-kpi"><span>Approved Today</span><strong><?= e((string)$summary['approved_today']) ?></strong></div>
        <div class="leave-kpi"><span>On Leave Today</span><strong><?= e((string)$summary['on_leave_today']) ?></strong></div>
    </div>

    <div class="leave-toolbar">
        <button type="button" class="btn btn-sm btn-outline-danger" id="toggleManualEntry" aria-expanded="false">+ Manual Leave Entry</button>
    </div>

    <div class="leave-manual-panel is-hidden" id="manualEntryPanel">
        <form method="post" class="leave-apply-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="manual">
            <div class="leave-apply-row">
                <div class="leave-field leave-field--emp">
                    <label class="leave-field__label">Employee</label>
                    <select class="form-select form-select-sm erp-select-search" name="employee_id" required data-placeholder="Search employee…">
                        <option value="">Search employee…</option>
                        <?php foreach ($emps as $emp): ?>
                            <option value="<?= (int)$emp['id'] ?>" data-sub="<?= e((string)($emp['employee_code'] ?? '')) ?>"><?= e((string)$emp['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="leave-field leave-field--type">
                    <label class="leave-field__label" for="hr_leave_duration_mode">Duration</label>
                    <select class="form-select form-select-sm" name="leave_duration_mode" id="hr_leave_duration_mode">
                        <option value="single" selected>Single day</option>
                        <option value="multiple">Multiple days</option>
                    </select>
                </div>
                <div class="leave-field leave-apply-form__from">
                    <label class="leave-field__label leave-apply-form__from-label">Date</label>
                    <input class="form-control form-control-sm" type="date" name="from_date" required>
                </div>
                <div class="leave-field leave-apply-form__to is-hidden">
                    <label class="leave-field__label">To</label>
                    <input class="form-control form-control-sm" type="date" name="to_date">
                </div>
                <div class="leave-field leave-field--grow">
                    <label class="leave-field__label">Reason</label>
                    <input class="form-control form-control-sm" name="reason" required>
                </div>
                <button type="submit" class="btn btn-sm btn-danger">Save</button>
            </div>
        </form>
    </div>

    <section class="leave-main">
        <form method="get" class="leave-filters">
            <input type="hidden" name="page" value="leave/list">
            <select name="department_id" class="form-select form-select-sm erp-select-search" data-placeholder="All departments…">
                <option value="">Department</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $filterDept === (int)$d['id'] ? 'selected' : '' ?>><?= e((string)$d['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select form-select-sm">
                <option value="">Status</option>
                <?php foreach (['Pending', 'Approved', 'Rejected'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" class="form-control form-control-sm" value="<?= e($filterDate) ?>" title="Leave date">
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($filterSearch) ?>" placeholder="Search employee">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
            <?php if ($filters): ?><a href="<?= e($listUrl) ?>" class="btn btn-sm btn-link text-muted px-1">Clear</a><?php endif; ?>
        </form>

        <div class="table-responsive">
            <table class="table leave-table mb-0">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Leave Dates</th>
                    <th>Days</th>
                    <th>Leave Type</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No leave requests found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $st = leave_display_status((string)($r['status'] ?? 'Pending'));
                        $statusBadge = leave_status_badge($st);
                        $catBadge = leave_category_badge((string)($r['leave_category'] ?? 'Paid'));
                        $isPending = in_array((string)($r['status'] ?? ''), ['Pending', 'Applied'], true)
                            && (string)($r['entry_source'] ?? 'employee') === 'employee';
                        $lf = (string)($r['leave_from'] ?? '');
                        $lt = (string)($r['leave_to'] ?? '');
                        $datesLabel = $lf === $lt ? $lf : $lf . ' → ' . $lt;
                        $balance = leave_get_balance($pdo, (int)$r['employee_id'], leave_year_from_date($lf));
                        $canConvert = in_array($st, ['Pending', 'Approved'], true)
                            && (float)($r['unpaid_days'] ?? 0) < (float)($r['total_days'] ?? 1);
                        ?>
                        <tr>
                            <td class="leave-table__emp"><?= e((string)$r['full_name']) ?></td>
                            <td><?= e((string)($r['department_name'] ?? '—')) ?></td>
                            <td class="text-nowrap"><?= e($datesLabel) ?></td>
                            <td><?= e(number_format((float)($r['total_days'] ?? 0), 0)) ?></td>
                            <td><span class="<?= e($catBadge['class']) ?>"><?= e($catBadge['label']) ?></span></td>
                            <td><span class="<?= e($statusBadge['class']) ?>"><?= e($statusBadge['label']) ?></span></td>
                            <td class="text-end">
                                <div class="leave-actions">
                                    <button type="button" class="leave-action-btn" title="View"
                                            data-bs-toggle="modal" data-bs-target="#leaveViewModal"
                                            data-id="<?= (int)$r['id'] ?>"
                                            data-name="<?= e((string)$r['full_name']) ?>"
                                            data-dept="<?= e((string)($r['department_name'] ?? '—')) ?>"
                                            data-dates="<?= e($datesLabel) ?>"
                                            data-days="<?= e(number_format((float)($r['total_days'] ?? 0), 0)) ?>"
                                            data-type="<?= e((string)($r['leave_category'] ?? '')) ?>"
                                            data-status="<?= e($st) ?>"
                                            data-reason="<?= e((string)($r['reason'] ?? '')) ?>"
                                            data-paid-left="<?= e((string)(int)$balance['paid_remaining']) ?>"
                                            data-pending="<?= $isPending ? '1' : '0' ?>"
                                            data-convert="<?= $canConvert ? '1' : '0' ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($isPending): ?>
                                        <form method="post" class="d-inline"><?= csrf_input() ?>
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="leave-action-btn leave-action-btn--ok" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <button type="button" class="leave-action-btn leave-action-btn--no" title="Reject"
                                                data-bs-toggle="modal" data-bs-target="#leaveRejectModal"
                                                data-leave-id="<?= (int)$r['id'] ?>"
                                                data-employee-name="<?= e((string)$r['full_name']) ?>">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="leaveViewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content leave-view-modal">
            <div class="modal-header py-2 border-0">
                <h6 class="modal-title mb-0" id="leaveViewTitle">Leave</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2 pt-0">
                <dl class="leave-view-dl mb-0">
                    <dt>Employee</dt><dd id="lvName">—</dd>
                    <dt>Department</dt><dd id="lvDept">—</dd>
                    <dt>Dates</dt><dd id="lvDates">—</dd>
                    <dt>Days</dt><dd id="lvDays">—</dd>
                    <dt>Leave type</dt><dd id="lvType">—</dd>
                    <dt>Status</dt><dd id="lvStatus">—</dd>
                    <dt>Paid left</dt><dd id="lvBalance">—</dd>
                    <dt>Reason</dt><dd id="lvReason">—</dd>
                </dl>
            </div>
            <div class="modal-footer py-2 border-0 gap-1" id="leaveViewFooter"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="leaveRejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="post" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="">
            <div class="modal-header py-2">
                <h6 class="modal-title">Reject — <span class="js-reject-employee"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <textarea class="form-control form-control-sm" name="rejection_reason" rows="2" required placeholder="Reason"></textarea>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/leave-dashboard.js?v=<?= e((string)$jsVer) ?>"></script>
