<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/hr_dashboard_service.php';
require_once __DIR__ . '/../../includes/payroll_service.php';

if (!has_role(['HR Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$kpis = hr_dashboard_kpis($pdo);
$attSnap = hr_dashboard_attendance_snapshot($pdo);
$todayStatus = hr_dashboard_today_status($pdo);
$activity = hr_dashboard_activity($pdo, 10);
$month = date('Y-m');
$payrollPending = count(payroll_list_employees($pdo, $month, ['salary_status' => 'pending']));

$kpiCards = [
    [
        'slug' => 'employees',
        'label' => 'Total Employees',
        'value' => (string)($kpis['total_employees']['value'] ?? 0),
        'sub' => 'Active workforce',
        'trend' => $kpis['total_employees']['trend'] ?? [],
        'icon' => 'bi-people',
        'url' => route_url('employees/list'),
    ],
    [
        'slug' => 'present',
        'label' => 'Present Today',
        'value' => (string)($kpis['present_today']['value'] ?? 0),
        'sub' => 'Checked in today',
        'trend' => $kpis['present_today']['trend'] ?? [],
        'icon' => 'bi-person-check',
        'url' => route_url('attendance/list'),
    ],
    [
        'slug' => 'absent',
        'label' => 'Absent',
        'value' => (string)($kpis['absent_today']['value'] ?? 0),
        'sub' => 'Marked absent',
        'trend' => $kpis['absent_today']['trend'] ?? [],
        'icon' => 'bi-person-x',
        'url' => route_url('attendance/list') . '&att_section=register',
    ],
    [
        'slug' => 'leave',
        'label' => 'Pending Leave',
        'value' => (string)($kpis['pending_leave']['value'] ?? 0),
        'sub' => 'Awaiting approval',
        'trend' => $kpis['pending_leave']['trend'] ?? [],
        'icon' => 'bi-hourglass-split',
        'url' => route_url('leave/list'),
    ],
    [
        'slug' => 'payroll-pending',
        'label' => 'Payroll Pending',
        'value' => (string)$payrollPending,
        'sub' => date('F Y'),
        'trend' => ['text' => $payrollPending > 0 ? 'Action needed' : 'Clear', 'class' => $payrollPending > 0 ? 'warn' : 'up'],
        'icon' => 'bi-cash-coin',
        'url' => route_url('payroll/list'),
    ],
    [
        'slug' => 'payroll-month',
        'label' => 'Monthly Payroll',
        'value' => (string)($kpis['payroll_month']['value'] ?? '₹0'),
        'sub' => 'Net payout',
        'trend' => $kpis['payroll_month']['trend'] ?? [],
        'icon' => 'bi-cash-stack',
        'url' => route_url('payroll/list'),
    ],
];

$quickActions = [
    ['icon' => 'bi-calendar-check', 'label' => 'Mark Attendance', 'url' => route_url('attendance/list')],
    ['icon' => 'bi-person-plus', 'label' => 'Add Employee', 'url' => route_url('employees/create')],
    ['icon' => 'bi-check2-square', 'label' => 'Approve Leave', 'url' => route_url('leave/list')],
    ['icon' => 'bi-lightning', 'label' => 'Generate Payroll', 'url' => route_url('payroll/list')],
    ['icon' => 'bi-file-earmark-bar-graph', 'label' => 'HR Reports', 'url' => route_url('reports/hr')],
    ['icon' => 'bi-calendar-event', 'label' => 'Add Holiday', 'url' => route_url('attendance/list')],
];

?>
<div class="hr-page hr-cc module-shell">
    <header class="hr-cc__hero">
        <div>
            <h1>HR Command Center</h1>
            <p>Operational overview · attendance, leave & payroll</p>
        </div>
        <span class="hr-cc__hero-date"><i class="bi bi-calendar3 me-1"></i><?= e(date('l, d F Y')) ?></span>
    </header>

    <div class="hr-cc__kpis">
        <?php foreach ($kpiCards as $card):
            $tr = $card['trend'];
            $trClass = (string)($tr['class'] ?? 'neutral');
            ?>
            <a href="<?= e((string)$card['url']) ?>" class="hr-cc__kpi hr-cc__kpi--<?= e((string)$card['slug']) ?>">
                <span class="hr-cc__kpi-icon"><i class="bi <?= e((string)$card['icon']) ?>"></i></span>
                <span class="hr-cc__kpi-body">
                    <span class="hr-cc__kpi-label"><?= e((string)$card['label']) ?></span>
                    <span class="hr-cc__kpi-value"><?= e((string)$card['value']) ?></span>
                    <span class="hr-cc__kpi-sub"><?= e((string)$card['sub']) ?> · <span class="hr-cc__kpi-trend hr-cc__kpi-trend--<?= e($trClass) ?>"><?= e((string)($tr['text'] ?? '')) ?></span></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="hr-cc__grid">
        <section class="hr-cc__card hr-cc__col-att">
            <div class="hr-cc__card-head">
                <h2>Attendance Overview</h2>
                <a href="<?= e(route_url('attendance/list')) ?>" class="small text-decoration-none">Open</a>
            </div>
            <div class="hr-cc__card-body">
                <div class="hr-cc__att-row">
                    <div class="hr-cc__meter">
                        <label><span>Present</span><strong><?= (int)$attSnap['present_pct'] ?>%</strong></label>
                        <div class="hr-cc__meter-bar"><div class="hr-cc__meter-fill hr-cc__meter-fill--ok" style="width:<?= (int)$attSnap['present_pct'] ?>%"></div></div>
                    </div>
                    <div class="hr-cc__meter">
                        <label><span>Absent</span><strong><?= (int)$attSnap['absent_pct'] ?>%</strong></label>
                        <div class="hr-cc__meter-bar"><div class="hr-cc__meter-fill hr-cc__meter-fill--bad" style="width:<?= (int)$attSnap['absent_pct'] ?>%"></div></div>
                    </div>
                </div>
                <div class="hr-cc__att-stats">
                    <div class="hr-cc__mini-stat"><span>Late employees</span><strong><?= (int)$attSnap['late'] ?></strong></div>
                    <div class="hr-cc__mini-stat"><span>OT workers today</span><strong><?= (int)$attSnap['ot_workers'] ?></strong></div>
                    <div class="hr-cc__mini-stat"><span>OT hours today</span><strong><?= e((string)($attSnap['ot_hours'] ?? 0)) ?>h</strong></div>
                    <div class="hr-cc__mini-stat"><span>Active staff</span><strong><?= (int)$attSnap['total'] ?></strong></div>
                </div>
            </div>
        </section>

        <section class="hr-cc__card hr-cc__col-actions">
            <div class="hr-cc__card-head"><h2>Quick Actions</h2></div>
            <div class="hr-cc__card-body">
                <div class="hr-cc__actions">
                    <?php foreach ($quickActions as $qa): ?>
                        <a href="<?= e((string)$qa['url']) ?>" class="hr-cc__action">
                            <i class="bi <?= e((string)$qa['icon']) ?>"></i>
                            <span><?= e((string)$qa['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <div class="hr-cc__col-side">
            <section class="hr-cc__card">
                <div class="hr-cc__card-head"><h2>Today Status</h2></div>
                <div class="hr-cc__card-body">
                    <ul class="hr-cc__today-list">
                        <li><span>Employees present</span><strong><?= (int)$todayStatus['present'] ?></strong></li>
                        <li><span>On leave</span><strong><?= (int)$todayStatus['on_leave'] ?></strong></li>
                        <li><span>Late employees</span><strong><?= (int)$todayStatus['late'] ?></strong></li>
                        <li><span>Missing punch out</span><strong><?= (int)$todayStatus['missing_punch_out'] ?></strong></li>
                    </ul>
                </div>
            </section>
            <section class="hr-cc__card hr-cc__col-activity">
                <div class="hr-cc__card-head"><h2>Recent Activity</h2></div>
                <div class="hr-cc__card-body">
                    <?php if (!$activity): ?>
                        <p class="small text-muted mb-0">No activity recorded today.</p>
                    <?php else: ?>
                        <ul class="hr-cc__timeline">
                            <?php foreach ($activity as $act): ?>
                                <li>
                                    <span class="hr-cc__tl-icon"><i class="bi <?= e((string)$act['icon']) ?>"></i></span>
                                    <div class="hr-cc__tl-body">
                                        <p><?= e((string)$act['text']) ?></p>
                                        <time><?= e((string)$act['time']) ?></time>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
