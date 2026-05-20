<?php
declare(strict_types=1);

require_once __DIR__ . '/leave_service.php';

function hr_dash_format_money(float $amount): string
{
    if ($amount >= 100000) {
        return '₹' . number_format($amount / 100000, 1) . 'L';
    }
    if ($amount >= 1000) {
        return '₹' . number_format($amount / 1000, 1) . 'K';
    }

    return '₹' . number_format($amount, 0);
}

function hr_dash_trend_label(float $current, float $previous, bool $higherIsGood = true): array
{
    if ($previous <= 0 && $current <= 0) {
        return ['text' => '—', 'class' => 'neutral'];
    }
    if ($previous <= 0) {
        return ['text' => '+' . (int)$current, 'class' => $higherIsGood ? 'up' : 'down'];
    }
    $pct = round((($current - $previous) / $previous) * 100);
    if ($pct === 0) {
        return ['text' => '0%', 'class' => 'neutral'];
    }
    $good = ($pct > 0 && $higherIsGood) || ($pct < 0 && !$higherIsGood);

    return [
        'text' => ($pct > 0 ? '+' : '') . $pct . '%',
        'class' => $good ? 'up' : 'down',
    ];
}

/** @return array<string,mixed> */
function hr_dashboard_kpis(PDO $pdo): array
{
    $today = date('Y-m-d');
    $month = date('Y-m');
    $prevMonth = date('Y-m', strtotime('-1 month'));
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $totalEmp = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();

    $presentStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND status IN ('Present','Late','Half Day','Emergency Duty')");
    $presentStmt->execute(['d' => $today]);
    $presentToday = (int)$presentStmt->fetchColumn();

    $presentYesterdayStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND status IN ('Present','Late','Half Day','Emergency Duty')");
    $presentYesterdayStmt->execute(['d' => $yesterday]);
    $presentYesterday = (int)$presentYesterdayStmt->fetchColumn();

    $onLeaveStmt = $pdo->prepare("SELECT COUNT(DISTINCT l.employee_id) FROM leaves l
        INNER JOIN employees e ON e.id = l.employee_id AND e.status = 'active'
        WHERE l.status IN ('Approved','Pending') AND :d BETWEEN COALESCE(l.from_date,l.start_date) AND COALESCE(l.to_date,l.end_date)");
    $onLeaveStmt->execute(['d' => $today]);
    $onLeaveToday = (int)$onLeaveStmt->fetchColumn();

    $absentStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND status = 'Absent'");
    $absentStmt->execute(['d' => $today]);
    $absentToday = (int)$absentStmt->fetchColumn();

    $pendingLeave = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status IN ('Pending','Applied')")->fetchColumn();

    $payrollStmt = $pdo->prepare('SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE month_year = :m');
    $payrollStmt->execute(['m' => $month]);
    $payrollMonth = (float)$payrollStmt->fetchColumn();
    $payrollStmt->execute(['m' => $prevMonth]);
    $payrollPrev = (float)$payrollStmt->fetchColumn();

    $otStmt = $pdo->prepare("SELECT COALESCE(SUM(overtime_hours), 0) FROM attendance WHERE DATE_FORMAT(attendance_date, '%Y-%m') = :m");
    $otStmt->execute(['m' => $month]);
    $otMonth = (float)$otStmt->fetchColumn();
    $otStmt->execute(['m' => $prevMonth]);
    $otPrev = (float)$otStmt->fetchColumn();

    $newStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE DATE_FORMAT(joining_date, '%Y-%m') = :m");
    $newStmt->execute(['m' => $month]);
    $newJoined = (int)$newStmt->fetchColumn();
    $newStmt->execute(['m' => $prevMonth]);
    $newPrev = (int)$newStmt->fetchColumn();

    return [
        'total_employees' => ['value' => $totalEmp, 'trend' => hr_dash_trend_label((float)$totalEmp, (float)max(0, $totalEmp - $newJoined), true), 'icon' => 'bi-people'],
        'present_today' => ['value' => $presentToday, 'trend' => hr_dash_trend_label((float)$presentToday, (float)$presentYesterday, true), 'icon' => 'bi-person-check'],
        'on_leave_today' => ['value' => $onLeaveToday, 'trend' => ['text' => 'today', 'class' => 'neutral'], 'icon' => 'bi-calendar-minus'],
        'absent_today' => ['value' => $absentToday, 'trend' => hr_dash_trend_label((float)$absentToday, 0, false), 'icon' => 'bi-person-x'],
        'pending_leave' => ['value' => $pendingLeave, 'trend' => $pendingLeave > 0 ? ['text' => 'action', 'class' => 'warn'] : ['text' => 'clear', 'class' => 'up'], 'icon' => 'bi-hourglass-split'],
        'payroll_month' => ['value' => hr_dash_format_money($payrollMonth), 'raw' => $payrollMonth, 'trend' => hr_dash_trend_label($payrollMonth, $payrollPrev, false), 'icon' => 'bi-cash-stack'],
        'ot_month' => ['value' => number_format($otMonth, 1) . 'h', 'raw' => $otMonth, 'trend' => hr_dash_trend_label($otMonth, $otPrev, false), 'icon' => 'bi-clock-history'],
        'new_joined' => ['value' => $newJoined, 'trend' => hr_dash_trend_label((float)$newJoined, (float)$newPrev, true), 'icon' => 'bi-person-plus'],
    ];
}

/** @return array{labels:list<string>,present:list<int>,absent:list<int>,leave:list<int>} */
function hr_dashboard_attendance_trend(PDO $pdo, int $days = 30): array
{
    $days = max(7, min(30, $days));
    $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $labels = [];
    $present = [];
    $absent = [];
    $leave = [];
    $map = [];

    $st = $pdo->prepare("SELECT attendance_date AS d,
            SUM(CASE WHEN status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS p,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS a,
            SUM(CASE WHEN status IN ('Paid Leave','Unpaid Leave','Half Paid Leave') OR status LIKE '%Leave%' THEN 1 ELSE 0 END) AS l
        FROM attendance WHERE attendance_date >= :s AND attendance_date <= CURDATE()
        GROUP BY attendance_date ORDER BY attendance_date");
    $st->execute(['s' => $start]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string)$row['d']] = [
            'p' => (int)$row['p'],
            'a' => (int)$row['a'],
            'l' => (int)$row['l'],
        ];
    }

    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = date('d M', strtotime($d));
        $row = $map[$d] ?? ['p' => 0, 'a' => 0, 'l' => 0];
        $present[] = $row['p'];
        $absent[] = $row['a'];
        $leave[] = $row['l'];
    }

    return compact('labels', 'present', 'absent', 'leave');
}

/** @return array{labels:list<string>,values:list<int>} */
function hr_dashboard_department_distribution(PDO $pdo): array
{
    $hasDeptId = (bool)$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'employees' AND column_name = 'department_id' LIMIT 1")->fetchColumn();
    if ($hasDeptId) {
        $rows = $pdo->query("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS dept, COUNT(*) AS c
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.status = 'active'
            GROUP BY dept ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = $pdo->query("SELECT COALESCE(NULLIF(department,''), 'Unassigned') AS dept, COUNT(*) AS c
            FROM employees WHERE status = 'active' GROUP BY dept ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'labels' => array_map(static fn ($r) => (string)$r['dept'], $rows),
        'values' => array_map(static fn ($r) => (int)$r['c'], $rows),
    ];
}

/** @return array<string,mixed> */
function hr_dashboard_payroll_monthly(PDO $pdo, int $months = 6): array
{
    $rows = $pdo->query("SELECT month_year,
            COALESCE(SUM(gross_salary), 0) AS gross,
            COALESCE(SUM(overtime_amount), 0) AS ot,
            COALESCE(SUM(total_deduction), 0) AS deductions,
            COALESCE(SUM(net_salary), 0) AS net
        FROM salaries GROUP BY month_year ORDER BY month_year DESC LIMIT " . (int)$months)->fetchAll(PDO::FETCH_ASSOC);
    $rows = array_reverse($rows);
    $labels = [];
    foreach ($rows as $r) {
        $labels[] = date('M Y', strtotime((string)$r['month_year'] . '-01'));
    }

    return [
        'labels' => $labels,
        'gross' => array_map(static fn ($r) => (float)$r['gross'], $rows),
        'ot' => array_map(static fn ($r) => (float)$r['ot'], $rows),
        'deductions' => array_map(static fn ($r) => (float)$r['deductions'], $rows),
        'net' => array_map(static fn ($r) => (float)$r['net'], $rows),
    ];
}

/** @return array<string,int|float> */
function hr_dashboard_leave_analytics(PDO $pdo): array
{
    $month = date('Y-m');
    $st = $pdo->prepare("SELECT
            COALESCE(SUM(paid_days), 0) AS paid,
            COALESCE(SUM(half_paid_days), 0) AS half_paid,
            COALESCE(SUM(unpaid_days), 0) AS unpaid
        FROM leaves
        WHERE status IN ('Approved','Pending')
          AND DATE_FORMAT(COALESCE(from_date, start_date), '%Y-%m') <= :m
          AND DATE_FORMAT(COALESCE(to_date, end_date), '%Y-%m') >= :m2");
    $st->execute(['m' => $month, 'm2' => $month]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status IN ('Pending','Applied')")->fetchColumn();

    return [
        'paid' => (float)($row['paid'] ?? 0),
        'half_paid' => (float)($row['half_paid'] ?? 0),
        'unpaid' => (float)($row['unpaid'] ?? 0),
        'pending' => $pending,
    ];
}

/** @return array{low_staff:list<array>,high_attendance:list<array>,alerts:list<string>} */
function hr_dashboard_workforce_panel(PDO $pdo): array
{
    $today = date('Y-m-d');
    $lowStaff = [];
    $highAtt = [];
    foreach (leave_department_staffing_overview($pdo, $today) as $row) {
        if (in_array((string)$row['status'], ['Critical', 'Warning'], true)) {
            $lowStaff[] = [
                'name' => (string)$row['department_name'],
                'present' => (int)$row['present'],
                'required' => (int)$row['min_required'],
                'status' => (string)$row['status'],
            ];
        }
        $total = (int)$row['total'];
        if ($total > 0) {
            $pct = round(((int)$row['present'] / $total) * 100);
            $highAtt[] = ['name' => (string)$row['department_name'], 'pct' => $pct, 'present' => (int)$row['present'], 'total' => $total];
        }
    }
    usort($highAtt, static fn ($a, $b) => $b['pct'] <=> $a['pct']);
    $highAtt = array_slice($highAtt, 0, 4);

    $alerts = [];
    foreach (array_slice($lowStaff, 0, 3) as $ls) {
        $alerts[] = $ls['name'] . ' below minimum staffing';
    }
    if (!$alerts && leave_top_staffing_notice($pdo)) {
        $alerts[] = leave_top_staffing_notice($pdo);
    }

    return ['low_staff' => array_slice($lowStaff, 0, 5), 'high_attendance' => $highAtt, 'alerts' => $alerts];
}

/** @return list<array{icon:string,text:string,time:string}> */
function hr_dashboard_activity(PDO $pdo, int $limit = 8): array
{
    $items = [];
    $today = date('Y-m-d');

    $checkins = $pdo->prepare("SELECT e.full_name, a.punch_in_time FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.attendance_date = :d AND a.punch_in_time IS NOT NULL
        ORDER BY a.punch_in_time DESC LIMIT 4");
    $checkins->execute(['d' => $today]);
    foreach ($checkins->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $t = $row['punch_in_time'] ? date('g:i A', strtotime((string)$row['punch_in_time'])) : '';
        $items[] = ['icon' => 'bi-box-arrow-in-right', 'text' => (string)$row['full_name'] . ' checked in at ' . $t, 'time' => $t, 'sort' => strtotime((string)$row['punch_in_time']) ?: 0];
    }

    $pending = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status IN ('Pending','Applied')")->fetchColumn();
    if ($pending > 0) {
        $items[] = ['icon' => 'bi-calendar-minus', 'text' => $pending . ' leave request' . ($pending > 1 ? 's' : '') . ' pending approval', 'time' => 'Now', 'sort' => time()];
    }

    $month = date('Y-m');
    $payGen = $pdo->prepare('SELECT COUNT(*) AS c, MAX(generated_at) AS last_at FROM salaries WHERE month_year = :m');
    $payGen->execute(['m' => $month]);
    $pg = $payGen->fetch(PDO::FETCH_ASSOC) ?: [];
    if ((int)($pg['c'] ?? 0) > 0) {
        $items[] = [
            'icon' => 'bi-receipt',
            'text' => 'Payroll generated for ' . (int)$pg['c'] . ' employee' . ((int)$pg['c'] > 1 ? 's' : ''),
            'time' => !empty($pg['last_at']) ? date('d M', strtotime((string)$pg['last_at'])) : $month,
            'sort' => !empty($pg['last_at']) ? strtotime((string)$pg['last_at']) : 0,
        ];
    }

    $absents = $pdo->prepare("SELECT e.full_name FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.attendance_date = :d AND a.status = 'Absent' ORDER BY e.full_name LIMIT 3");
    $absents->execute(['d' => $today]);
    foreach ($absents->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = ['icon' => 'bi-person-x', 'text' => (string)$row['full_name'] . ' absent today', 'time' => 'Today', 'sort' => 0];
    }

    usort($items, static fn ($a, $b) => ($b['sort'] ?? 0) <=> ($a['sort'] ?? 0));

    return array_slice(array_map(static fn ($i) => ['icon' => $i['icon'], 'text' => $i['text'], 'time' => $i['time']], $items), 0, $limit);
}

/** @return array<string,float|int> */
function hr_dashboard_payroll_snapshot(PDO $pdo): array
{
    $month = date('Y-m');
    $st = $pdo->prepare('SELECT
            COALESCE(SUM(net_salary), 0) AS net,
            COALESCE(SUM(pf_amount), 0) AS pf,
            COALESCE(SUM(esi_employee_amount), 0) AS esi,
            COALESCE(SUM(overtime_amount), 0) AS ot,
            COALESCE(SUM(leave_deduction), 0) AS leave_ded
        FROM salaries WHERE month_year = :m');
    $st->execute(['m' => $month]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'net' => (float)($row['net'] ?? 0),
        'pf' => (float)($row['pf'] ?? 0),
        'esi' => (float)($row['esi'] ?? 0),
        'ot' => (float)($row['ot'] ?? 0),
        'leave_ded' => (float)($row['leave_ded'] ?? 0),
    ];
}

/** @return array<string,mixed> */
function hr_dashboard_attendance_snapshot(PDO $pdo): array
{
    $today = date('Y-m-d');
    $total = max(1, (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn());

    $presentStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND status IN ('Present','Late','Half Day','Emergency Duty')");
    $presentStmt->execute(['d' => $today]);
    $present = (int)$presentStmt->fetchColumn();

    $absentStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND status = 'Absent'");
    $absentStmt->execute(['d' => $today]);
    $absent = (int)$absentStmt->fetchColumn();

    $lateStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND (status = 'Late' OR is_late = 1)");
    $lateStmt->execute(['d' => $today]);
    $late = (int)$lateStmt->fetchColumn();

    $otStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND overtime_hours > 0");
    $otStmt->execute(['d' => $today]);
    $otWorkers = (int)$otStmt->fetchColumn();

    $otHoursStmt = $pdo->prepare("SELECT COALESCE(SUM(overtime_hours), 0) FROM attendance WHERE attendance_date = :d");
    $otHoursStmt->execute(['d' => $today]);
    $otHoursToday = (float)$otHoursStmt->fetchColumn();

    return [
        'present_pct' => min(100, round(($present / $total) * 100)),
        'absent_pct' => min(100, round(($absent / $total) * 100)),
        'late' => $late,
        'ot_workers' => $otWorkers,
        'ot_hours' => round($otHoursToday, 1),
        'total' => $total,
        'present' => $present,
        'absent' => $absent,
    ];
}

/** @return array{present:int,on_leave:int,late:int,missing_punch_out:int} */
function hr_dashboard_today_status(PDO $pdo): array
{
    $today = date('Y-m-d');

    $st = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND status IN ('Present','Late','Half Day','Emergency Duty')");
    $st->execute(['d' => $today]);
    $present = (int)$st->fetchColumn();

    $ol = $pdo->prepare("SELECT COUNT(DISTINCT l.employee_id) FROM leaves l
        INNER JOIN employees e ON e.id = l.employee_id AND e.status = 'active'
        WHERE l.status IN ('Approved','Pending','Applied') AND :d BETWEEN COALESCE(l.from_date,l.start_date) AND COALESCE(l.to_date,l.end_date)");
    $ol->execute(['d' => $today]);
    $onLeave = (int)$ol->fetchColumn();

    $lateSt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND (status = 'Late' OR is_late = 1)");
    $lateSt->execute(['d' => $today]);
    $late = (int)$lateSt->fetchColumn();

    $missSt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = :d AND punch_in_time IS NOT NULL AND punch_out_time IS NULL");
    $missSt->execute(['d' => $today]);
    $missingPunch = (int)$missSt->fetchColumn();

    return [
        'present' => $present,
        'on_leave' => $onLeave,
        'late' => $late,
        'missing_punch_out' => $missingPunch,
    ];
}

/** @return list<array{type:string,label:string,date:string}> */
function hr_dashboard_upcoming(PDO $pdo, int $limit = 8): array
{
    $events = [];
    $today = date('Y-m-d');

    $bday = $pdo->query("SELECT full_name, dob FROM employees WHERE status = 'active' AND dob IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bday as $e) {
        $dob = (string)($e['dob'] ?? '');
        if ($dob === '') {
            continue;
        }
        $next = date('Y') . '-' . date('m-d', strtotime($dob));
        if ($next < $today) {
            $next = (date('Y') + 1) . '-' . date('m-d', strtotime($dob));
        }
        $diff = (int)((strtotime($next) - strtotime($today)) / 86400);
        if ($diff >= 0 && $diff <= 30) {
            $events[] = ['type' => 'birthday', 'label' => (string)$e['full_name'] . ' — Birthday', 'date' => date('d M', strtotime($next)), 'sort' => strtotime($next)];
        }
    }

    $join = $pdo->query("SELECT full_name, joining_date FROM employees WHERE status = 'active' AND joining_date IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($join as $e) {
        $jd = (string)($e['joining_date'] ?? '');
        if ($jd === '') {
            continue;
        }
        $ann = date('Y') . '-' . date('m-d', strtotime($jd));
        if ($ann < $today) {
            $ann = (date('Y') + 1) . '-' . date('m-d', strtotime($jd));
        }
        $diff = (int)((strtotime($ann) - strtotime($today)) / 86400);
        if ($diff >= 0 && $diff <= 30) {
            $yrs = (int)date('Y') - (int)date('Y', strtotime($jd));
            $events[] = ['type' => 'anniversary', 'label' => (string)$e['full_name'] . ' — ' . $yrs . ' yr anniversary', 'date' => date('d M', strtotime($ann)), 'sort' => strtotime($ann)];
        }
    }

    if (erp_table_exists_leave($pdo, 'hr_holidays')) {
        $h = $pdo->prepare('SELECT holiday_date, holiday_name FROM hr_holidays WHERE holiday_date >= :d ORDER BY holiday_date LIMIT 5');
        $h->execute(['d' => $today]);
        foreach ($h->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $events[] = ['type' => 'holiday', 'label' => (string)$row['holiday_name'], 'date' => date('d M', strtotime((string)$row['holiday_date'])), 'sort' => strtotime((string)$row['holiday_date'])];
        }
    } else {
        $h = $pdo->prepare('SELECT holiday_date, label FROM company_holidays WHERE holiday_date >= :d ORDER BY holiday_date LIMIT 5');
        $h->execute(['d' => $today]);
        foreach ($h->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $events[] = ['type' => 'holiday', 'label' => (string)$row['label'], 'date' => date('d M', strtotime((string)$row['holiday_date'])), 'sort' => strtotime((string)$row['holiday_date'])];
        }
    }

    $pending = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status IN ('Pending','Applied')")->fetchColumn();
    if ($pending > 0) {
        $events[] = ['type' => 'approval', 'label' => $pending . ' leave approval' . ($pending > 1 ? 's' : '') . ' pending', 'date' => 'Action', 'sort' => strtotime($today)];
    }

    usort($events, static fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

    return array_slice($events, 0, $limit);
}
