<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attendance_workflow.php';
require_once __DIR__ . '/../../includes/attendance_policy.php';
require_once __DIR__ . '/../../includes/attendance_leave_bridge.php';
require_once __DIR__ . '/../../includes/leave_service.php';
require_once __DIR__ . '/../../includes/employee_portal.php';

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dash_punch'])) {
    verify_csrf();
    try {
        $emp = require_employee_record($pdo);
        if (($emp['employee_type'] ?? 'Staff') !== 'Staff') {
            throw new RuntimeException('Punch is only for staff.');
        }
        $today = date('Y-m-d');
        $punchPerm = staff_punch_ui_state($pdo, (int)$emp['id'], $today);
        if ($_POST['dash_punch'] === 'in') {
            if (!$punchPerm['can_punch_in']) {
                throw new RuntimeException($punchPerm['locked'] ? 'Attendance completed for today.' : 'Punch in is not available right now.');
            }
            staff_record_punch_in($pdo, $emp, $today);
            set_flash('success', 'Punch in recorded.');
        } elseif ($_POST['dash_punch'] === 'out') {
            if (!$punchPerm['can_punch_out']) {
                throw new RuntimeException($punchPerm['locked'] ? 'Attendance completed for today.' : 'Punch out is not available. Punch in first.');
            }
            $warn = staff_record_punch_out($pdo, $emp, $today);
            set_flash($warn ? 'warning' : 'success', $warn ?: 'Punch out recorded. Attendance completed for today.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('employee/dashboard');
}

$error = '';
$employee = null;
$currentMonth = date('Y-m');
$kpis = [];
$balance = [];
$leaveCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$latestSalary = null;
$holidays = [];
$weeklyTrend = [];
$notifyPayload = ['items' => [], 'unread' => 0];
$punchUi = null;
$isStaff = false;
$shiftDisplay = '09:00 – 18:00';
$recentLeaves = [];

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];
    $isStaff = ($employee['employee_type'] ?? 'Staff') === 'Staff';
    [$shiftStart, $shiftEnd] = employee_shift_clock_bounds($employee);
    $shiftDisplay = substr($shiftStart, 0, 5) . ' – ' . substr($shiftEnd, 0, 5);

    attendance_leave_reconcile($pdo, $employeeId, $currentMonth);
    $kpis = emp_portal_month_attendance_kpis($pdo, $employeeId, $currentMonth);
    $balance = leave_get_balance($pdo, $employeeId, (int)date('Y'));
    $leaveCounts = emp_portal_leave_request_counts($pdo, $employeeId);
    $holidays = emp_portal_upcoming_holidays($pdo, 5);
    $weeklyTrend = emp_portal_weekly_attendance_trend($pdo, $employeeId);
    $notifyPayload = emp_notifications_payload($pdo, $employeeId);

    if ($isStaff) {
        $punchUi = staff_punch_ui_state($pdo, $employeeId, date('Y-m-d'));
    }

    $salaryStmt = $pdo->prepare('SELECT * FROM salaries WHERE employee_id = :e ORDER BY month_year DESC, id DESC LIMIT 1');
    $salaryStmt->execute(['e' => $employeeId]);
    $latestSalary = $salaryStmt->fetch() ?: null;

    $leaveAlertStmt = $pdo->prepare('SELECT from_date, to_date, status, rejection_reason, reason, created_at FROM leaves WHERE employee_id = :e ORDER BY id DESC LIMIT 8');
    $leaveAlertStmt->execute(['e' => $employeeId]);
    $recentLeaves = $leaveAlertStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}
?>

<div class="emp-dash">
    <header class="emp-dash__header">
        <div class="emp-dash__header-text">
            <h1 class="emp-dash__title">Employee Dashboard</h1>
            <p class="emp-dash__subtitle"><?= e($_SESSION['user']['name'] ?? 'Employee') ?> · <?= e((string)($employee['employee_code'] ?? '')) ?> · <?= e(date('l, d M Y')) ?></p>
        </div>
        <nav class="emp-dash__nav" aria-label="Quick links">
            <a href="<?= e(route_url('employee/leave')) ?>">Apply leave</a>
            <a href="<?= e(route_url('employee/attendance')) ?>">Attendance</a>
            <a href="<?= e(route_url('employee/salary')) ?>">Salary</a>
            <a href="<?= e(route_url('employee/profile')) ?>">Profile</a>
        </nav>
    </header>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php else: ?>

    <div class="row g-3 emp-dash__kpi-row">
        <div class="col-6 col-md-4 col-lg-2">
            <article class="emp-dash-kpi emp-dash-kpi--present">
                <span class="emp-dash-kpi__label">Present</span>
                <span class="emp-dash-kpi__value"><?= e((string)($kpis['present'] ?? 0)) ?></span>
                <span class="emp-dash-kpi__hint"><?= e((string)($kpis['pct'] ?? 0)) ?>% this month</span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="emp-dash-kpi emp-dash-kpi--absent">
                <span class="emp-dash-kpi__label">Absent</span>
                <span class="emp-dash-kpi__value"><?= e((string)($kpis['absent'] ?? 0)) ?></span>
                <span class="emp-dash-kpi__hint"><?= e(date('M Y')) ?></span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="emp-dash-kpi emp-dash-kpi--leave">
                <span class="emp-dash-kpi__label">Leave balance</span>
                <span class="emp-dash-kpi__value"><?= e(number_format((float)($balance['paid_remaining'] ?? 0), 0)) ?></span>
                <span class="emp-dash-kpi__hint">Paid days left</span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="emp-dash-kpi emp-dash-kpi--pending">
                <span class="emp-dash-kpi__label">Pending leave</span>
                <span class="emp-dash-kpi__value"><?= e((string)($leaveCounts['pending'] ?? 0)) ?></span>
                <span class="emp-dash-kpi__hint">Awaiting HR</span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="emp-dash-kpi emp-dash-kpi--ot">
                <span class="emp-dash-kpi__label">OT hours</span>
                <span class="emp-dash-kpi__value"><?= e(number_format((float)($kpis['ot'] ?? 0), 1)) ?></span>
                <span class="emp-dash-kpi__hint">Month total</span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="emp-dash-kpi emp-dash-kpi--salary">
                <span class="emp-dash-kpi__label">Latest salary</span>
                <span class="emp-dash-kpi__value emp-dash-kpi__value--sm"><?= $latestSalary ? '₹' . e(number_format((float)$latestSalary['net_salary'], 0)) : '—' ?></span>
                <span class="emp-dash-kpi__hint"><?= e($latestSalary['month_year'] ?? 'Not generated') ?></span>
            </article>
        </div>
    </div>

    <div class="row g-3 emp-dash__main align-items-stretch">
        <div class="col-lg-7 d-flex flex-column gap-3">
            <?php if ($isStaff && $punchUi): ?>
            <div class="emp-dash-card emp-dash-card--punch flex-grow-0">
                <?php
                $punchFieldName = 'dash_punch';
                $punchFormIn = 'in';
                $punchFormOut = 'out';
                $punchCompact = true;
                require __DIR__ . '/../../includes/employee_punch_panel.php';
                ?>
            </div>
            <?php endif; ?>

            <div class="row g-3 flex-grow-1">
                <div class="col-md-6 d-flex">
                    <section class="emp-dash-card w-100">
                        <div class="emp-dash-card__head">
                            <h2 class="emp-dash-card__title">Attendance summary</h2>
                            <span class="emp-dash-chip"><?= e((string)($kpis['pct'] ?? 0)) ?>%</span>
                        </div>
                        <div class="emp-dash-card__body">
                            <div class="emp-dash-progress" role="progressbar" aria-valuenow="<?= e((string)min(100, (int)($kpis['pct'] ?? 0))) ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="emp-dash-progress__bar" style="width:<?= e((string)min(100, (int)($kpis['pct'] ?? 0))) ?>%"></div>
                            </div>
                            <ul class="emp-dash-metrics">
                                <li><span>Present</span><strong><?= e((string)($kpis['present'] ?? 0)) ?></strong></li>
                                <li><span>Half days</span><strong><?= e((string)($kpis['half'] ?? 0)) ?></strong></li>
                                <li><span>Leave days</span><strong><?= e((string)($kpis['leave'] ?? 0)) ?></strong></li>
                                <li><span>Late</span><strong><?= e((string)($kpis['late'] ?? 0)) ?></strong></li>
                            </ul>
                        </div>
                    </section>
                </div>
                <div class="col-md-6 d-flex">
                    <section class="emp-dash-card w-100">
                        <div class="emp-dash-card__head">
                            <h2 class="emp-dash-card__title">Weekly trend</h2>
                        </div>
                        <div class="emp-dash-card__body d-flex flex-column justify-content-end">
                            <div class="emp-dash-bars">
                                <?php foreach ($weeklyTrend as $w): ?>
                                    <div class="emp-dash-bars__col" title="<?= e($w['label']) ?>">
                                        <div class="emp-dash-bars__bar" style="height:<?= e((string)max(12, min(100, $w['pct']))) ?>%"></div>
                                        <span class="emp-dash-bars__pct"><?= e((string)$w['pct']) ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="col-lg-5 d-flex flex-column gap-3">
            <section class="emp-dash-card flex-grow-1">
                <div class="emp-dash-card__head">
                    <h2 class="emp-dash-card__title">Notifications</h2>
                    <?php if (($notifyPayload['unread'] ?? 0) > 0): ?>
                        <span class="emp-dash-chip emp-dash-chip--alert"><?= e((string)$notifyPayload['unread']) ?> new</span>
                    <?php endif; ?>
                </div>
                <div class="emp-dash-card__body emp-dash-notify-list">
                    <?php if (empty($notifyPayload['items'])): ?>
                        <p class="emp-dash-empty">No notifications.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($notifyPayload['items'], 0, 5) as $n): ?>
                            <article class="emp-dash-notify<?= empty($n['read']) ? ' emp-dash-notify--unread' : '' ?>">
                                <div class="emp-dash-notify__title"><?= e((string)($n['title'] ?? 'Notice')) ?></div>
                                <p class="emp-dash-notify__msg"><?= e((string)($n['message'] ?? '')) ?></p>
                                <?php if (!empty($n['created_at'])): ?>
                                    <time class="emp-dash-notify__time"><?= e(date('d M, h:i A', strtotime((string)$n['created_at'])) ?: (string)$n['created_at']) ?></time>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <p class="emp-dash-helper mb-0">Use the bell icon above for full list and mark-as-read.</p>
                </div>
            </section>

            <section class="emp-dash-card">
                <div class="emp-dash-card__head">
                    <h2 class="emp-dash-card__title">Upcoming holidays</h2>
                </div>
                <div class="emp-dash-card__body">
                    <?php if (!$holidays): ?>
                        <p class="emp-dash-empty">No upcoming holidays listed.</p>
                    <?php else: ?>
                        <ul class="emp-dash-holidays">
                            <?php foreach ($holidays as $h): ?>
                                <li><time><?= e(date('d M', strtotime($h['date']))) ?></time><span><?= e($h['title']) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="emp-dash-helper">Weekly off: Sunday</p>
                </div>
            </section>
        </div>
    </div>

    <section class="emp-dash-card emp-dash-card--table">
        <div class="emp-dash-card__head">
            <h2 class="emp-dash-card__title">Recent leave requests</h2>
            <a class="emp-dash-card__link" href="<?= e(route_url('employee/leave')) ?>">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover emp-dash-table mb-0">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Reason / HR note</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recentLeaves): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No leave requests yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentLeaves as $lv): ?>
                        <?php
                        $st = (string)($lv['status'] ?? '');
                        $badge = $st === 'Approved' ? 'success' : ($st === 'Rejected' ? 'danger' : 'warning');
                        $note = trim((string)($lv['rejection_reason'] ?? ''));
                        if ($note === '') {
                            $note = trim((string)($lv['reason'] ?? '')) ?: '—';
                        }
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= e($lv['from_date']) ?></td>
                            <td class="text-nowrap"><?= e($lv['to_date']) ?></td>
                            <td><span class="badge text-bg-<?= e($badge) ?>"><?= e(leave_display_status($st)) ?></span></td>
                            <td><?= e($note) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="emp-dash-card__foot">
            <a href="<?= e(route_url('employee/attendance')) ?>">Attendance calendar</a>
            <span class="emp-dash-card__foot-sep">·</span>
            <a href="<?= e(route_url('employee/salary')) ?>">Salary history</a>
            <span class="emp-dash-card__foot-sep">·</span>
            <a href="<?= e(route_url('employee/export')) ?>&type=attendance&month=<?= e(urlencode($currentMonth)) ?>">Export attendance</a>
        </div>
    </section>

    <?php endif; ?>
</div>
