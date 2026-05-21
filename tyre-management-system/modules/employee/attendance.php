<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attendance_workflow.php';
require_once __DIR__ . '/../../includes/attendance_policy.php';
require_once __DIR__ . '/../../includes/attendance_leave_bridge.php';

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $employee = require_employee_record($pdo);
        if (($employee['employee_type'] ?? 'Staff') !== 'Staff') {
            throw new RuntimeException('Punch in/out is only for staff.');
        }
        $action = (string)($_POST['action'] ?? '');
        $today = date('Y-m-d');
        $punchPerm = staff_punch_ui_state($pdo, (int)$employee['id'], $today);
        if ($action === 'punch_in') {
            if (!$punchPerm['can_punch_in']) {
                throw new RuntimeException($punchPerm['locked'] ? 'Attendance completed for today.' : 'Punch in is not available right now.');
            }
            staff_record_punch_in($pdo, $employee, $today);
            set_flash('success', 'Punch in recorded.');
        } elseif ($action === 'punch_out') {
            if (!$punchPerm['can_punch_out']) {
                throw new RuntimeException($punchPerm['locked'] ? 'Attendance completed for today.' : 'Punch out is not available. Punch in first.');
            }
            $warn = staff_record_punch_out($pdo, $employee, $today);
            set_flash($warn ? 'warning' : 'success', $warn ?: 'Punch out recorded. Attendance completed for today.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    $month = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['redirect_month'] ?? '')) ? (string)$_POST['redirect_month'] : date('Y-m');
    header('Location: index.php?page=' . rawurlencode('employee/attendance') . '&month=' . rawurlencode($month));
    exit;
}

$error = '';
$calendarMap = [];
$attendanceRows = [];
$selectedMonth = (string)($_GET['month'] ?? date('Y-m'));
$employee = null;
$todayRow = null;
$hasMonthRecords = false;
$monthStats = ['present' => 0, 'half_days' => 0, 'leave_days' => 0, 'absent' => 0, 'overtime_hours' => 0, 'total_marked' => 0];
$shiftStart = '09:00';
$shiftEnd = '18:00';
$isStaff = false;
$hasPunchIn = false;
$hasPunchOut = false;
$canPunchIn = false;
$canPunchOut = false;
$punchUi = null;
$todayStatusLabel = 'Not marked';
$todayHours = null;
$todayOt = 0;

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];
    [$shiftStart, $shiftEnd] = employee_shift_clock_bounds($employee);
    $isStaff = ($employee['employee_type'] ?? 'Staff') === 'Staff';

    if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }

    $prevMonth = date('Y-m', strtotime($selectedMonth . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($selectedMonth . '-01 +1 month'));
    $monthLabel = date('F Y', strtotime($selectedMonth . '-01'));

    $monthView = attendance_fetch_month_view($pdo, $employeeId, $selectedMonth);
    $calendarMap = $monthView['days'];
    $attendanceRows = $monthView['rows'];
    $hasMonthRecords = $monthView['has_records'];
    $monthStats = attendance_month_summary_live($pdo, $employeeId, $selectedMonth);

    if ($isStaff) {
        $todayYmd = date('Y-m-d');
        $punchUi = staff_punch_ui_state($pdo, $employeeId, $todayYmd);
        $todayRow = $punchUi['row'];
        $hasPunchIn = staff_has_punch_in($todayRow);
        $hasPunchOut = staff_has_punch_out($todayRow);
        $canPunchIn = $punchUi['can_punch_in'];
        $canPunchOut = $punchUi['can_punch_out'];
        $todayStatusLabel = attendance_status_display_label(
            $todayRow['status'] ?? null,
            $hasPunchIn,
            $hasPunchOut
        );
        $todayHours = $todayRow['total_hours'] ?? null;
        $todayOt = (float)($todayRow['overtime_hours'] ?? 0);
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$daysInMonth = (int)date('t', strtotime($selectedMonth . '-01'));
$firstDow = (int)date('w', strtotime($selectedMonth . '-01'));
$dowLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$isCurrentMonth = $selectedMonth === date('Y-m');

if (!function_exists('emp_cal_status_short')) {
    function emp_cal_status_short(string $status): string
    {
        return match ($status) {
            'Present' => 'Present',
            'Half Day' => 'Half Day',
            'Late' => 'Late',
            'Absent' => 'Absent',
            'Paid Leave' => 'Paid Leave',
            'Unpaid Leave' => 'Unpaid Leave',
            'Holiday' => 'Holiday',
            'Emergency Duty' => 'Duty',
            ATTENDANCE_STATUS_PENDING_VERIFICATION => 'Pending',
            ATTENDANCE_STATUS_IN_PROGRESS => 'In Progress',
            default => $status,
        };
    }
}

/** @return array<string, mixed> */
function emp_cal_day_payload(string $dateYmd, ?array $row, bool $isToday): array
{
    $status = $row ? (string)($row['status'] ?? '') : '';
    $hasPunch = $row && (!empty($row['punch_in_time']) || !empty($row['punch_out_time']));
    $punchIn = $row && !empty($row['punch_in_time']) ? date('h:i A', strtotime((string)$row['punch_in_time'])) : '';
    $punchOut = $row && !empty($row['punch_out_time']) ? date('h:i A', strtotime((string)$row['punch_out_time'])) : '';
    $timeLine = '';
    if ($punchIn !== '' && $punchOut !== '') {
        $timeLine = $punchIn . ' – ' . $punchOut;
    } elseif ($punchIn !== '') {
        $timeLine = 'In ' . $punchIn;
    } elseif ($punchOut !== '') {
        $timeLine = 'Out ' . $punchOut;
    }

    return [
        'date' => $dateYmd,
        'dateLabel' => date('l, d F Y', strtotime($dateYmd)),
        'status' => $status !== '' ? emp_cal_status_short($status) : 'No record',
        'statusClass' => $status !== '' ? attendance_status_badge_class($status) : 'emp-att--empty',
        'punchIn' => $punchIn !== '' ? $punchIn : '—',
        'punchOut' => $punchOut !== '' ? $punchOut : '—',
        'hours' => ($row && isset($row['total_hours']) && $row['total_hours'] !== null && $row['total_hours'] !== '')
            ? (string)$row['total_hours'] . ' h' : '—',
        'ot' => ($row && (float)($row['overtime_hours'] ?? 0) > 0)
            ? (string)$row['overtime_hours'] . ' h' : '—',
        'late' => ($row && ((int)($row['is_late'] ?? 0) === 1 || $status === 'Late')) ? 'Yes' : 'No',
        'remarks' => trim((string)($row['remarks'] ?? '')) !== '' ? (string)$row['remarks'] : '—',
        'timeLine' => $timeLine,
        'isToday' => $isToday,
        'hasRecord' => $status !== '',
        'hasPunch' => $hasPunch,
    ];
}

?>

<div class="emp-att-page emp-shell">
    <header class="emp-att-page__header">
        <div>
            <h1 class="emp-att-page__title">My Attendance</h1>
            <p class="emp-att-page__subtitle">Track punches, monthly calendar, and attendance history — synced live from HR records.</p>
        </div>
        <div class="emp-att-page__actions">
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('employee/dashboard')) ?>"><i class="bi bi-grid me-1"></i>Dashboard</a>
            <a class="btn btn-outline-primary btn-sm" href="<?= e(route_url('employee/leave')) ?>"><i class="bi bi-calendar-plus me-1"></i>Apply Leave</a>
        </div>
    </header>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 py-3 mb-3">
            <i class="bi bi-exclamation-octagon-fill flex-shrink-0 mt-1"></i>
            <div><strong>Could not load attendance</strong><br><span class="small"><?= e($error) ?></span></div>
        </div>
    <?php endif; ?>

    <?php if (!$error && $employee): ?>

    <div class="emp-att-stats">
        <div class="emp-att-stat emp-att-stat--present">
            <span class="emp-att-stat__k">Present</span>
            <strong><?= e((string)$monthStats['present']) ?></strong>
        </div>
        <div class="emp-att-stat emp-att-stat--half">
            <span class="emp-att-stat__k">Half days</span>
            <strong><?= e((string)$monthStats['half_days']) ?></strong>
        </div>
        <div class="emp-att-stat emp-att-stat--leave">
            <span class="emp-att-stat__k">Leave</span>
            <strong><?= e((string)$monthStats['leave_days']) ?></strong>
        </div>
        <div class="emp-att-stat emp-att-stat--absent">
            <span class="emp-att-stat__k">Absent</span>
            <strong><?= e((string)$monthStats['absent']) ?></strong>
        </div>
        <div class="emp-att-stat emp-att-stat--ot">
            <span class="emp-att-stat__k">OT hours</span>
            <strong><?= e(number_format((float)$monthStats['overtime_hours'], 1)) ?></strong>
        </div>
    </div>

    <?php if ($isStaff): ?>
        <?php
        $shiftDisplay = substr($shiftStart, 0, 5) . ' – ' . substr($shiftEnd, 0, 5);
        $punchRedirectMonth = $selectedMonth;
        require __DIR__ . '/../../includes/employee_punch_panel.php';
        ?>
    <?php else: ?>
    <div class="alert alert-info py-2 mb-3"><i class="bi bi-info-circle me-1"></i>Your attendance is recorded by HR.</div>
    <?php endif; ?>

    <section class="emp-cal-panel card" aria-label="Attendance calendar">
        <div class="emp-cal-panel__toolbar">
            <div class="emp-cal-panel__nav">
                <a class="emp-cal-panel__nav-btn" href="index.php?page=employee/attendance&amp;month=<?= e(urlencode($prevMonth)) ?>" title="Previous month">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <form method="get" class="emp-cal-panel__picker">
                    <input type="hidden" name="page" value="employee/attendance">
                    <input type="month" class="form-control" name="month" value="<?= e($selectedMonth) ?>" onchange="this.form.submit()" aria-label="Select month">
                </form>
                <a class="emp-cal-panel__nav-btn" href="index.php?page=employee/attendance&amp;month=<?= e(urlencode($nextMonth)) ?>" title="Next month">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php if (!$isCurrentMonth): ?>
                    <a class="btn btn-sm btn-outline-danger" href="index.php?page=employee/attendance&amp;month=<?= e(urlencode(date('Y-m'))) ?>">This month</a>
                <?php endif; ?>
            </div>
            <div class="emp-cal-panel__title-wrap">
                <h2 class="emp-cal-panel__title"><?= e($monthLabel ?? '') ?></h2>
                <span class="emp-cal-panel__count"><?= e((string)$monthStats['total_marked']) ?> days with records</span>
            </div>
            <div class="emp-cal-panel__legend" aria-label="Legend">
                <span class="emp-cal-legend__chip"><i class="emp-leg emp-leg--present"></i>Present</span>
                <span class="emp-cal-legend__chip"><i class="emp-leg emp-leg--half"></i>Half Day</span>
                <span class="emp-cal-legend__chip"><i class="emp-leg emp-leg--late"></i>Late</span>
                <span class="emp-cal-legend__chip"><i class="emp-leg emp-leg--absent"></i>Absent</span>
                <span class="emp-cal-legend__chip"><i class="emp-leg emp-leg--paid"></i>Paid Leave</span>
                <span class="emp-cal-legend__chip"><i class="emp-leg emp-leg--unpaid"></i>Unpaid</span>
                <span class="emp-cal-legend__chip"><i class="emp-leg emp-leg--holiday"></i>Holiday</span>
                <span class="emp-cal-legend__chip"><i class="emp-leg emp-leg--pending"></i>Pending</span>
            </div>
        </div>

        <div class="emp-cal-panel__weekhead" role="row">
            <?php foreach ($dowLabels as $lbl): ?>
                <div class="emp-cal-panel__weekday" role="columnheader"><?= e($lbl) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="emp-cal-panel__grid" role="grid">
            <?php for ($blank = 0; $blank < $firstDow; $blank++): ?>
                <div class="emp-cal-day emp-cal-day--pad" aria-hidden="true"></div>
            <?php endfor; ?>
            <?php
            $todayYmd = date('Y-m-d');
            for ($day = 1; $day <= $daysInMonth; $day++):
                $dateValue = $selectedMonth . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                $row = $calendarMap[$dateValue] ?? null;
                $status = $row ? (string)($row['status'] ?? '') : '';
                $stClass = $status !== '' ? attendance_status_badge_class($status) : 'emp-att--empty';
                $isToday = $dateValue === $todayYmd;
                $payload = emp_cal_day_payload($dateValue, $row, $isToday);
                $json = htmlspecialchars(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                ?>
                <button
                    type="button"
                    class="emp-cal-day <?= e($stClass) ?><?= $isToday ? ' emp-cal-day--today' : '' ?><?= $status === '' ? ' emp-cal-day--empty' : '' ?>"
                    data-day-json="<?= $json ?>"
                    aria-label="<?= e('Day ' . $day . ', ' . ($payload['status'] ?? 'no record')) ?>"
                >
                    <?php if ($isToday): ?><span class="emp-cal-day__today">Today</span><?php endif; ?>
                    <span class="emp-cal-day__num"><?= (int)$day ?></span>
                    <?php if ($status !== ''): ?>
                        <span class="emp-cal-day__pill"><?= e(emp_cal_status_short($status)) ?></span>
                    <?php else: ?>
                        <span class="emp-cal-day__pill emp-cal-day__pill--muted">—</span>
                    <?php endif; ?>
                    <?php if ($payload['timeLine'] !== ''): ?>
                        <span class="emp-cal-day__time"><i class="bi bi-clock"></i><?= e($payload['timeLine']) ?></span>
                    <?php elseif ($status !== '' && isset($row['total_hours']) && $row['total_hours'] !== null): ?>
                        <span class="emp-cal-day__time"><?= e((string)$row['total_hours']) ?>h</span>
                    <?php endif; ?>
                </button>
            <?php endfor; ?>
        </div>

        <?php if (!$hasMonthRecords): ?>
            <p class="emp-cal-panel__hint text-center text-muted mb-0 py-3">
                <i class="bi bi-info-circle me-1"></i>No attendance or leave yet for this month — punch in or apply leave to populate the calendar.
            </p>
        <?php else: ?>
            <p class="emp-cal-panel__hint text-muted mb-0"><i class="bi bi-hand-index-thumb me-1"></i>Click any day for full attendance details.</p>
        <?php endif; ?>
    </section>

    <div class="modal fade" id="empCalDayModal" tabindex="-1" aria-labelledby="empCalModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content emp-cal-modal">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title" id="empCalModalTitle">Attendance</h5>
                        <span class="emp-cal-modal__status-pill emp-att--default" id="empCalModalStatus">—</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2" id="empCalModalBody"></div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <section class="emp-att-history card">
        <div class="emp-att-history__head">
            <h2 class="emp-att-history__title">Attendance history</h2>
            <span class="text-muted small"><?= e($monthLabel ?? '') ?></span>
        </div>
        <div class="table-responsive">
            <table class="table emp-att-table mb-0">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>In</th>
                    <th>Out</th>
                    <th class="text-end">Hours</th>
                    <th class="text-end">OT</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$attendanceRows): ?>
                    <tr><td colspan="6" class="emp-att-table__empty">No records for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($attendanceRows as $row): ?>
                        <?php $st = (string)$row['status']; $stClass = attendance_status_badge_class($st); ?>
                        <tr>
                            <td class="fw-medium"><?= e(date('d M Y', strtotime((string)$row['attendance_date']))) ?></td>
                            <td><span class="emp-status-pill <?= e($stClass) ?>"><?= e($st) ?></span></td>
                            <td><?= !empty($row['punch_in_time']) ? e(date('H:i', strtotime((string)$row['punch_in_time']))) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= !empty($row['punch_out_time']) ? e(date('H:i', strtotime((string)$row['punch_out_time']))) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end"><?= isset($row['total_hours']) && $row['total_hours'] !== null ? e((string)$row['total_hours']) : '—' ?></td>
                            <td class="text-end"><?= e((string)($row['overtime_hours'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php endif; ?>
</div>
