<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/leave_service.php';

function hr_notifications_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS hr_notification_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        notification_key VARCHAR(120) NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        dismissed_at TIMESTAMP NULL,
        UNIQUE KEY uq_hr_notif_user_key (user_id, notification_key),
        INDEX idx_hr_notif_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function hr_notifications_current_user_id(): int
{
    return (int)(($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0));
}

/** @return array{icon:string,color:string}> */
function hr_notification_type_meta(string $type): array
{
    static $map = [
        'payroll' => ['icon' => 'bi-cash-stack', 'color' => 'danger'],
        'leave' => ['icon' => 'bi-calendar-minus', 'color' => 'warning'],
        'attendance' => ['icon' => 'bi-calendar-check', 'color' => 'success'],
        'employee' => ['icon' => 'bi-person-badge', 'color' => 'info'],
        'holiday' => ['icon' => 'bi-calendar-event', 'color' => 'primary'],
        'alert' => ['icon' => 'bi-exclamation-triangle', 'color' => 'danger'],
    ];
    return $map[$type] ?? ['icon' => 'bi-bell', 'color' => 'secondary'];
}

/** @return list<string> */
function hr_notifications_read_keys(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    hr_notifications_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT notification_key FROM hr_notification_reads WHERE user_id = :u AND dismissed_at IS NULL');
    $st->execute(['u' => $userId]);
    return array_map(static fn ($r) => (string)$r['notification_key'], $st->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * @return list<array{id:string,type:string,title:string,message:string,url:string,created_at:string,read:bool}>
 */
function hr_notifications_fetch(PDO $pdo, ?int $userId = null): array
{
    $userId = $userId ?? hr_notifications_current_user_id();
    $readKeys = hr_notifications_read_keys($pdo, $userId);
    $items = [];
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $month = date('Y-m');

    $pendingLeave = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status IN ('Pending','Applied')")->fetchColumn();
    if ($pendingLeave > 0) {
        $items[] = [
            'id' => 'leave_pending',
            'type' => 'leave',
            'title' => 'Leave approvals',
            'message' => $pendingLeave . ' leave request' . ($pendingLeave === 1 ? '' : 's') . ' pending approval',
            'url' => route_url('leave/list') . '&status=Pending',
            'created_at' => $now,
        ];
    }

    $lateStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = :d AND (status = 'Late' OR is_late = 1)");
    $lateStmt->execute(['d' => $today]);
    $lateCount = (int)$lateStmt->fetchColumn();
    if ($lateCount > 0) {
        $items[] = [
            'id' => 'late_' . $today,
            'type' => 'attendance',
            'title' => 'Late attendance',
            'message' => $lateCount . ' employee' . ($lateCount === 1 ? ' was' : 's were') . ' marked late today',
            'url' => route_url('attendance/list') . '&att_section=register&reg_search=1&reg_mode=daily&reg_date=' . $today,
            'created_at' => $now,
        ];
    }

    $absentStmt = $pdo->prepare("SELECT COUNT(*) FROM employees e WHERE e.status = 'active' AND NOT EXISTS (
        SELECT 1 FROM attendance a WHERE a.employee_id = e.id AND a.attendance_date = :d
    )");
    $absentStmt->execute(['d' => $today]);
    $notMarked = (int)$absentStmt->fetchColumn();
    $totalActive = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
    if ($notMarked > 0 && $notMarked < $totalActive) {
        $items[] = [
            'id' => 'att_unmarked_' . $today,
            'type' => 'attendance',
            'title' => 'Attendance pending',
            'message' => $notMarked . ' employee' . ($notMarked === 1 ? '' : 's') . ' not marked for today',
            'url' => route_url('attendance/list') . '&att_section=mark&search=1&att_date=' . $today,
            'created_at' => $now,
        ];
    } elseif ($notMarked > 0 && $notMarked === $totalActive && $totalActive > 0) {
        $items[] = [
            'id' => 'att_none_' . $today,
            'type' => 'attendance',
            'title' => 'Mark attendance',
            'message' => 'No attendance marked for today (' . $totalActive . ' employees)',
            'url' => route_url('attendance/list') . '&att_section=mark&search=1&att_date=' . $today,
            'created_at' => $now,
        ];
    }

    if (file_exists(__DIR__ . '/payroll_service.php')) {
        require_once __DIR__ . '/payroll_service.php';
        if (function_exists('payroll_list_employees')) {
            $pendingPayroll = count(payroll_list_employees($pdo, $month, ['salary_status' => 'pending']));
            if ($pendingPayroll > 0) {
                $items[] = [
                    'id' => 'payroll_pending_' . $month,
                    'type' => 'payroll',
                    'title' => 'Payroll pending',
                    'message' => 'Payroll pending for ' . $pendingPayroll . ' employee' . ($pendingPayroll === 1 ? '' : 's') . ' (' . date('F Y') . ')',
                    'url' => route_url('payroll/list') . '&month=' . urlencode($month),
                    'created_at' => $now,
                ];
            }
        }
    }

    try {
        $holStmt = $pdo->prepare('SELECT holiday_name, holiday_date FROM hr_holidays WHERE holiday_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY holiday_date DESC LIMIT 3');
        $holStmt->execute();
        foreach ($holStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $hd = (string)$h['holiday_date'];
            $items[] = [
                'id' => 'holiday_' . $hd,
                'type' => 'holiday',
                'title' => 'Holiday added',
                'message' => (string)$h['holiday_name'] . ' on ' . date('d M Y', strtotime($hd)),
                'url' => route_url('attendance/list') . '&att_section=mark&att_date=' . $hd,
                'created_at' => $hd . ' 09:00:00',
            ];
        }
    } catch (Throwable) {
        // hr_holidays may not exist on old DB
    }

    $credStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE status = 'active' AND password_hash IS NOT NULL AND password_hash != '' AND joining_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)");
    $credStmt->execute();
    $newCreds = (int)$credStmt->fetchColumn();
    if ($newCreds > 0) {
        $items[] = [
            'id' => 'emp_login_' . date('Y-m'),
            'type' => 'employee',
            'title' => 'Employee logins',
            'message' => $newCreds . ' new employee' . ($newCreds === 1 ? '' : 's') . ' with login credentials',
            'url' => route_url('employees/list'),
            'created_at' => $now,
        ];
    }

    if (erp_table_exists_leave($pdo, 'leave_notifications')) {
        $ln = $pdo->prepare("SELECT id, message, created_at FROM leave_notifications WHERE audience = 'hr' AND is_read = 0 ORDER BY id DESC LIMIT 5");
        $ln->execute();
        foreach ($ln->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'id' => 'leave_notice_' . (int)$row['id'],
                'type' => 'leave',
                'title' => 'Leave notice',
                'message' => (string)$row['message'],
                'url' => route_url('leave/list'),
                'created_at' => (string)($row['created_at'] ?? $now),
            ];
        }
    }

    usort($items, static fn ($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));

    foreach ($items as &$it) {
        $it['read'] = in_array((string)$it['id'], $readKeys, true);
        $meta = hr_notification_type_meta((string)($it['type'] ?? ''));
        $it['icon'] = $meta['icon'];
        $it['color'] = $meta['color'];
    }
    unset($it);

    return $items;
}

/** @return array{items:list<array>,unread:int}> */
function hr_notifications_payload(PDO $pdo, ?int $userId = null): array
{
    $items = hr_notifications_fetch($pdo, $userId);
    $unread = 0;
    foreach ($items as $it) {
        if (empty($it['read'])) {
            $unread++;
        }
    }

    return ['items' => $items, 'unread' => $unread];
}

function hr_notifications_mark_read(PDO $pdo, array $keys, ?int $userId = null): void
{
    $userId = $userId ?? hr_notifications_current_user_id();
    if ($userId <= 0 || $keys === []) {
        return;
    }
    hr_notifications_ensure_schema($pdo);
    $ins = $pdo->prepare('INSERT INTO hr_notification_reads (user_id, notification_key, read_at) VALUES (:u, :k, NOW())
        ON DUPLICATE KEY UPDATE read_at = NOW(), dismissed_at = NULL');
    foreach ($keys as $key) {
        $key = trim((string)$key);
        if ($key === '') {
            continue;
        }
        $ins->execute(['u' => $userId, 'k' => $key]);
    }
}

function hr_notifications_mark_all_read(PDO $pdo, ?int $userId = null): void
{
    $payload = hr_notifications_fetch($pdo, $userId);
    $keys = array_map(static fn ($it) => (string)$it['id'], $payload);
    hr_notifications_mark_read($pdo, $keys, $userId);
}

function hr_notifications_clear_all(PDO $pdo, ?int $userId = null): void
{
    $userId = $userId ?? hr_notifications_current_user_id();
    if ($userId <= 0) {
        return;
    }
    hr_notifications_ensure_schema($pdo);
    $items = hr_notifications_fetch($pdo, $userId);
    $ins = $pdo->prepare('INSERT INTO hr_notification_reads (user_id, notification_key, read_at, dismissed_at) VALUES (:u, :k, NOW(), NOW())
        ON DUPLICATE KEY UPDATE read_at = NOW(), dismissed_at = NOW()');
    foreach ($items as $it) {
        $ins->execute(['u' => $userId, 'k' => (string)$it['id']]);
    }
}
