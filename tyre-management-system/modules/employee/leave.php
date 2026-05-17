<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/leave_service.php';

$pdo = Database::connection();
$error = '';
$success = '';
$rows = [];
$balance = [];
$employee = null;
$pageNo = 1;
$totalPages = 1;

$cssPath = __DIR__ . '/../../assets/css/leave-dashboard.css';
$cssVer = is_file($cssPath) ? (int)filemtime($cssPath) : time();
$jsPath = __DIR__ . '/../../assets/js/leave-dashboard.js';
$jsVer = is_file($jsPath) ? (int)filemtime($jsPath) : time();

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];
    $year = (int)date('Y');
    $balance = leave_get_balance($pdo, $employeeId, $year);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $fromDate = (string)($_POST['from_date'] ?? '');
        $toDate = (string)($_POST['to_date'] ?? '');
        if ($toDate === '' && $fromDate !== '') {
            $toDate = $fromDate;
        }
        $reason = trim((string)($_POST['reason'] ?? ''));
        $isEmergency = !empty($_POST['is_emergency']);
        $result = leave_apply($pdo, $employeeId, $fromDate, $toDate, $reason, $isEmergency);
        if ($result['ok']) {
            $success = $result['message'];
            $balance = leave_get_balance($pdo, $employeeId, $year);
        } else {
            $error = $result['message'];
        }
    }

    $pageNo = max(1, (int)($_GET['p'] ?? 1));
    $perPage = 15;
    $offset = ($pageNo - 1) * $perPage;
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM leaves WHERE employee_id = :employee_id');
    $countStmt->execute(['employee_id' => $employeeId]);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    $stmt = $pdo->prepare('SELECT l.*, u.full_name AS approver_name FROM leaves l
        LEFT JOIN users u ON u.id = l.approved_by
        WHERE l.employee_id = :employee_id ORDER BY l.id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}
?>

<link href="assets/css/leave-dashboard.css?v=<?= e((string)$cssVer) ?>" rel="stylesheet">

<div class="leave-erp module-shell">
    <header class="leave-erp__header">
        <div>
            <h1 class="leave-erp__title">Leave</h1>
            <p class="leave-erp__subtitle">Apply with dates and reason — leave type is assigned from your balance and workforce rules.</p>
        </div>
    </header>

    <?php if ($error): ?><div class="alert alert-danger py-2 mb-3"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success py-2 mb-3"><?= e($success) ?></div><?php endif; ?>

    <?php if ($employee): ?>
        <div class="leave-erp__stats">
            <div class="leave-stat"><span class="leave-stat__k">Paid left</span><strong><?= e(number_format($balance['paid_remaining'], 0)) ?></strong><small>/ <?= e(number_format($balance['paid_total'], 0)) ?></small></div>
            <div class="leave-stat leave-stat--half"><span class="leave-stat__k">Half paid</span><strong><?= e(number_format($balance['half_remaining'], 0)) ?></strong><small>/ <?= e(number_format($balance['half_total'], 0)) ?></small></div>
            <div class="leave-stat leave-stat--muted"><span class="leave-stat__k">Unpaid used</span><strong><?= e(number_format($balance['unpaid_used'], 0)) ?></strong></div>
            <div class="leave-stat leave-stat--pending"><span class="leave-stat__k">Pending</span><strong><?= e((string)$balance['pending_count']) ?></strong></div>
        </div>

        <section class="leave-card">
            <h2 class="leave-card__title">Apply for leave</h2>
            <form method="post" class="leave-apply-form" id="employeeLeaveApplyForm">
                <?= csrf_input() ?>
                <div class="leave-apply-row">
                    <div class="leave-field leave-field--type">
                        <label class="leave-field__label" for="leave_duration_mode">Type</label>
                        <select class="form-select form-select-sm" name="leave_duration_mode" id="leave_duration_mode">
                            <option value="single" selected>Single day</option>
                            <option value="multiple">Multiple days</option>
                        </select>
                    </div>
                    <div class="leave-field leave-apply-form__from">
                        <label class="leave-field__label leave-apply-form__from-label" for="leave_from_date">Leave date</label>
                        <input class="form-control form-control-sm" type="date" name="from_date" id="leave_from_date" required>
                    </div>
                    <div class="leave-field leave-apply-form__to is-hidden">
                        <label class="leave-field__label" for="leave_to_date">To date</label>
                        <input class="form-control form-control-sm" type="date" name="to_date" id="leave_to_date">
                    </div>
                    <div class="leave-field leave-field--grow">
                        <label class="leave-field__label" for="leave_reason">Reason</label>
                        <input class="form-control form-control-sm" type="text" name="reason" id="leave_reason" maxlength="255" placeholder="Brief reason" required>
                    </div>
                </div>
                <div class="leave-apply-row leave-apply-row--actions">
                    <label class="leave-check">
                        <input type="checkbox" name="is_emergency" value="1">
                        <span>Emergency</span>
                    </label>
                    <span class="leave-days-pill is-hidden" id="leaveDaysPill" aria-live="polite"></span>
                    <button type="submit" class="btn btn-sm btn-danger leave-btn-submit">Submit request</button>
                </div>
            </form>
        </section>

        <section class="leave-card">
            <h2 class="leave-card__title">My leave history</h2>
            <div class="table-responsive">
                <table class="table leave-erp-table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Approved by</th>
                        <th>Payroll</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No leave records.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $st = leave_display_status((string)($row['status'] ?? 'Pending'));
                            $badge = leave_status_badge($st);
                            $from = (string)($row['from_date'] ?? $row['start_date'] ?? '');
                            $to = (string)($row['to_date'] ?? $row['end_date'] ?? '');
                            $dateLabel = $from === $to ? $from : $from . ' → ' . $to;
                            $approver = (string)($row['approver_name'] ?? '');
                            if ($approver === '' && !empty($row['auto_approved'])) {
                                $approver = 'System';
                            }
                            if ($approver === '') {
                                $approver = '—';
                            }
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= e($dateLabel) ?></td>
                                <td><?= e(number_format((float)($row['total_days'] ?? 0), 0)) ?></td>
                                <td><span class="leave-tag"><?= e((string)($row['leave_category'] ?? '—')) ?></span></td>
                                <td><span class="<?= e($badge['class']) ?>"><?= e($badge['label']) ?></span></td>
                                <td class="text-muted"><?= e($approver) ?></td>
                                <td class="small text-muted"><?= e(leave_payroll_impact($row)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="leave-erp-pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(route_url('employee/leave')) ?>&p=<?= e((string)($pageNo - 1)) ?>">Prev</a>
                        </li>
                        <li class="page-item disabled"><span class="page-link"><?= e((string)$pageNo) ?> / <?= e((string)$totalPages) ?></span></li>
                        <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(route_url('employee/leave')) ?>&p=<?= e((string)($pageNo + 1)) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<script src="assets/js/leave-dashboard.js?v=<?= e((string)$jsVer) ?>"></script>
