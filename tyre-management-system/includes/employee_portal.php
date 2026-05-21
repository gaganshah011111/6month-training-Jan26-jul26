<?php
declare(strict_types=1);

require_once __DIR__ . '/attendance_leave_bridge.php';
require_once __DIR__ . '/leave_service.php';

/** @return list<array<string, mixed>> */
function emp_portal_upcoming_holidays(PDO $pdo, int $limit = 5): array
{
    $rows = [];
    $today = date('Y-m-d');
    foreach (['hr_holidays', 'company_holidays'] as $table) {
        try {
            if ($table === 'company_holidays') {
                $st = $pdo->prepare('SELECT holiday_date AS d, label AS title FROM company_holidays WHERE holiday_date >= :t ORDER BY holiday_date ASC LIMIT :lim');
            } else {
                $st = $pdo->prepare('SELECT holiday_date AS d, holiday_name AS title FROM hr_holidays WHERE holiday_date >= :t ORDER BY holiday_date ASC LIMIT :lim');
            }
            $st->bindValue(':t', $today);
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[] = ['date' => (string)$r['d'], 'title' => (string)$r['title']];
            }
        } catch (Throwable $e) {
            // table may not exist
        }
    }
    usort($rows, static fn($a, $b) => strcmp($a['date'], $b['date']));
    $seen = [];
    $out = [];
    foreach ($rows as $r) {
        if (isset($seen[$r['date']])) {
            continue;
        }
        $seen[$r['date']] = true;
        $out[] = $r;
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * Last 4 weeks present-day counts (Mon–Sun buckets ending current week).
 *
 * @return list<array{label: string, present: int, total: int, pct: int}>
 */
function emp_portal_weekly_attendance_trend(PDO $pdo, int $employeeId): array
{
    $trend = [];
    for ($w = 3; $w >= 0; $w--) {
        $weekEnd = strtotime('sunday this week -' . ($w * 7) . ' days');
        if ($weekEnd === false) {
            continue;
        }
        $weekStart = strtotime('-6 days', $weekEnd);
        $from = date('Y-m-d', $weekStart);
        $to = date('Y-m-d', $weekEnd);
        $label = date('d M', $weekStart) . '–' . date('d M', $weekEnd);
        $st = $pdo->prepare("SELECT status FROM attendance WHERE employee_id = :e AND attendance_date BETWEEN :f AND :t");
        $st->execute(['e' => $employeeId, 'f' => $from, 't' => $to]);
        $present = 0;
        $total = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stt = (string)($row['status'] ?? '');
            if ($stt === '' || $stt === 'In Progress') {
                continue;
            }
            $total++;
            if (in_array($stt, ['Present', 'Late', 'Emergency Duty'], true)) {
                $present++;
            }
        }
        $pct = $total > 0 ? (int)round(($present / $total) * 100) : 0;
        $trend[] = ['label' => $label, 'present' => $present, 'total' => $total, 'pct' => $pct];
    }

    return $trend;
}

/** @return array{present: int, absent: int, half: int, late: int, leave: int, ot: float, marked: int, working_days: int, pct: int} */
function emp_portal_month_attendance_kpis(PDO $pdo, int $employeeId, string $monthYyyyMm): array
{
    attendance_leave_reconcile($pdo, $employeeId, $monthYyyyMm);
    $stats = attendance_month_summary_live($pdo, $employeeId, $monthYyyyMm);
    $daysInMonth = (int)date('t', strtotime($monthYyyyMm . '-01'));
    $sundays = 0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        if ((int)date('w', strtotime($monthYyyyMm . '-' . sprintf('%02d', $d))) === 0) {
            $sundays++;
        }
    }
    $workingDays = max(1, $daysInMonth - $sundays);
    $marked = (int)($stats['total_marked'] ?? 0);
    $present = (int)($stats['present'] ?? 0);
    $pct = (int)min(100, round(($present / $workingDays) * 100));

    $lateSt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = :e AND DATE_FORMAT(attendance_date, '%Y-%m') = :m AND (status = 'Late' OR is_late = 1)");
    $lateSt->execute(['e' => $employeeId, 'm' => $monthYyyyMm]);
    $late = (int)$lateSt->fetchColumn();

    $absSt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = :e AND DATE_FORMAT(attendance_date, '%Y-%m') = :m AND status = 'Absent'");
    $absSt->execute(['e' => $employeeId, 'm' => $monthYyyyMm]);
    $absent = (int)$absSt->fetchColumn();

    return [
        'present' => $present,
        'absent' => $absent,
        'half' => (int)($stats['half_days'] ?? 0),
        'late' => $late,
        'leave' => (int)($stats['leave_days'] ?? 0),
        'ot' => (float)($stats['overtime_hours'] ?? 0),
        'marked' => $marked,
        'working_days' => $workingDays,
        'pct' => $pct,
    ];
}

/** @return array{pending: int, approved: int, rejected: int} */
function emp_portal_leave_request_counts(PDO $pdo, int $employeeId): array
{
    $st = $pdo->prepare("SELECT status, COUNT(*) AS c FROM leaves WHERE employee_id = :e GROUP BY status");
    $st->execute(['e' => $employeeId]);
    $out = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $s = (string)($row['status'] ?? '');
        $c = (int)($row['c'] ?? 0);
        if (in_array($s, ['Pending', 'Applied'], true)) {
            $out['pending'] += $c;
        } elseif ($s === 'Approved') {
            $out['approved'] += $c;
        } elseif ($s === 'Rejected') {
            $out['rejected'] += $c;
        }
    }

    return $out;
}

/** Unified notification feed for employee navbar + dashboard. */
function emp_notifications_payload(PDO $pdo, int $employeeId): array
{
    $items = [];
    $unread = 0;

    if (erp_table_exists_leave($pdo, 'leave_notifications')) {
        $st = $pdo->prepare('SELECT * FROM leave_notifications WHERE employee_id = :e AND audience = :a ORDER BY id DESC LIMIT 40');
        $st->execute(['e' => $employeeId, 'a' => 'employee']);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
            $read = (int)($n['is_read'] ?? 0) === 1;
            if (!$read) {
                $unread++;
            }
            $type = (string)($n['notice_type'] ?? 'info');
            $items[] = [
                'id' => 'ln-' . (int)$n['id'],
                'db_id' => (int)$n['id'],
                'source' => 'leave_notifications',
                'title' => emp_notice_title($type),
                'message' => (string)($n['message'] ?? ''),
                'created_at' => (string)($n['created_at'] ?? ''),
                'read' => $read,
                'url' => route_url('employee/leave'),
                'icon' => emp_notice_icon($type),
                'color' => emp_notice_color($type),
            ];
        }
    }

    $salSt = $pdo->prepare('SELECT id, month_year, net_salary, payment_status, generated_at, created_at FROM salaries WHERE employee_id = :e ORDER BY id DESC LIMIT 3');
    $salSt->execute(['e' => $employeeId]);
    foreach ($salSt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $ts = (string)($s['generated_at'] ?? $s['created_at'] ?? '');
        $items[] = [
            'id' => 'sal-' . (int)$s['id'],
            'db_id' => 0,
            'source' => 'salary',
            'title' => 'Salary generated',
            'message' => ($s['month_year'] ?? '') . ' — Net ₹' . number_format((float)($s['net_salary'] ?? 0), 2) . ' (' . ($s['payment_status'] ?? 'Pending') . ')',
            'created_at' => $ts,
            'read' => true,
            'url' => route_url('employee/salary') . '&slip=' . (int)$s['id'],
            'icon' => 'bi-cash-stack',
            'color' => 'success',
        ];
    }

    $warnSt = $pdo->prepare("SELECT attendance_date, status, remarks FROM attendance WHERE employee_id = :e AND status IN ('Absent', 'Pending Verification') AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) ORDER BY attendance_date DESC LIMIT 3");
    $warnSt->execute(['e' => $employeeId]);
    foreach ($warnSt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $items[] = [
            'id' => 'att-' . (string)$a['attendance_date'],
            'db_id' => 0,
            'source' => 'attendance',
            'title' => 'Attendance: ' . (string)$a['status'],
            'message' => (string)$a['attendance_date'] . ' — ' . (string)($a['remarks'] ?? 'Review your attendance'),
            'created_at' => (string)$a['attendance_date'] . ' 09:00:00',
            'read' => false,
            'url' => route_url('employee/attendance'),
            'icon' => 'bi-exclamation-triangle',
            'color' => 'warning',
        ];
        $unread++;
    }

    usort($items, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    $items = array_slice($items, 0, 25);

    return ['items' => $items, 'unread' => min($unread, 99)];
}

function emp_notice_title(string $type): string
{
    return match ($type) {
        'approved' => 'Leave approved',
        'rejected' => 'Leave rejected',
        'pending' => 'Leave submitted',
        'converted_unpaid' => 'Leave converted',
        'balance_low' => 'Leave balance low',
        'hr_staffing' => 'HR notice',
        default => 'Notification',
    };
}

function emp_notice_icon(string $type): string
{
    return match ($type) {
        'approved' => 'bi-check-circle',
        'rejected' => 'bi-x-circle',
        'pending' => 'bi-hourglass-split',
        'converted_unpaid' => 'bi-arrow-repeat',
        'balance_low' => 'bi-info-circle',
        default => 'bi-bell',
    };
}

function emp_notice_color(string $type): string
{
    return match ($type) {
        'approved' => 'success',
        'rejected' => 'danger',
        'pending' => 'primary',
        'converted_unpaid', 'balance_low' => 'warning',
        default => 'secondary',
    };
}

/** @param list<string> $ids */
function emp_notifications_mark_read(PDO $pdo, int $employeeId, array $ids): void
{
    if (!erp_table_exists_leave($pdo, 'leave_notifications')) {
        return;
    }
    foreach ($ids as $id) {
        if (!str_starts_with((string)$id, 'ln-')) {
            continue;
        }
        $dbId = (int)substr((string)$id, 3);
        if ($dbId < 1) {
            continue;
        }
        $st = $pdo->prepare('UPDATE leave_notifications SET is_read = 1 WHERE id = :id AND employee_id = :e');
        $st->execute(['id' => $dbId, 'e' => $employeeId]);
    }
}

function emp_notifications_mark_all_read(PDO $pdo, int $employeeId): void
{
    if (!erp_table_exists_leave($pdo, 'leave_notifications')) {
        return;
    }
    $st = $pdo->prepare('UPDATE leave_notifications SET is_read = 1 WHERE employee_id = :e AND audience = :a');
    $st->execute(['e' => $employeeId, 'a' => 'employee']);
}

function emp_notifications_clear_all(PDO $pdo, int $employeeId): void
{
    if (!erp_table_exists_leave($pdo, 'leave_notifications')) {
        return;
    }
    $st = $pdo->prepare('DELETE FROM leave_notifications WHERE employee_id = :e AND audience = :a');
    $st->execute(['e' => $employeeId, 'a' => 'employee']);
}
