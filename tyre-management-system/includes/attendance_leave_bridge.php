<?php
declare(strict_types=1);

require_once __DIR__ . '/leave_service.php';
require_once __DIR__ . '/attendance_workflow.php';

/** Statuses written by leave → attendance sync. */
function attendance_is_leave_derived_status(?string $status): bool
{
    if ($status === null || $status === '') {
        return false;
    }
    static $set = [
        'Paid Leave', 'Unpaid Leave', 'Half Paid Leave', 'Leave',
    ];

    return in_array($status, $set, true) || str_contains($status, 'Leave');
}

/**
 * Reconcile attendance with live `leaves` table: remove ghosts, re-apply approved leaves.
 * Call before rendering employee attendance/dashboard/leave UI.
 */
function attendance_leave_reconcile(PDO $pdo, int $employeeId, ?string $monthYyyyMm = null): void
{
    attendance_leave_cleanup_orphans($pdo, $employeeId, $monthYyyyMm);
    attendance_leave_resync_approved($pdo, $employeeId, $monthYyyyMm);
}

/** Remove attendance rows that look like leave but have no backing approved leave. */
function attendance_leave_cleanup_orphans(PDO $pdo, int $employeeId, ?string $monthYyyyMm = null): int
{
    $monthFilter = '';
    $params = ['eid' => $employeeId];
    if ($monthYyyyMm !== null && preg_match('/^\d{4}-\d{2}$/', $monthYyyyMm)) {
        $monthFilter = " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = :month";
        $params['month'] = $monthYyyyMm;
    }

    $leaveStatuses = "'Paid Leave','Unpaid Leave','Half Paid Leave','Leave'";
    $sql = "DELETE a FROM attendance a
        WHERE a.employee_id = :eid
        {$monthFilter}
        AND (
            a.status IN ({$leaveStatuses})
            OR a.remarks LIKE 'Leave #%'
            OR (a.linked_leave_id IS NOT NULL AND a.linked_leave_id > 0)
        )
        AND NOT EXISTS (
            SELECT 1 FROM leaves l
            WHERE l.employee_id = a.employee_id
              AND l.status = 'Approved'
              AND a.attendance_date >= COALESCE(l.from_date, l.start_date)
              AND a.attendance_date <= COALESCE(l.to_date, l.end_date)
        )";

    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
    } catch (Throwable $e) {
        if (!str_contains($e->getMessage(), 'linked_leave_id')) {
            throw $e;
        }
        $sql = str_replace(' OR (a.linked_leave_id IS NOT NULL AND a.linked_leave_id > 0)', '', $sql);
        $st = $pdo->prepare($sql);
        $st->execute($params);
    }

    return (int)$st->rowCount();
}

/** Re-apply attendance for all approved leaves overlapping the scope. */
function attendance_leave_resync_approved(PDO $pdo, int $employeeId, ?string $monthYyyyMm = null): void
{
    $sql = "SELECT id FROM leaves WHERE employee_id = :eid AND status = 'Approved'";
    $params = ['eid' => $employeeId];
    if ($monthYyyyMm !== null && preg_match('/^\d{4}-\d{2}$/', $monthYyyyMm)) {
        $sql .= " AND DATE_FORMAT(COALESCE(from_date, start_date), '%Y-%m') <= :month_end
            AND DATE_FORMAT(COALESCE(to_date, end_date), '%Y-%m') >= :month_start";
        $params['month_end'] = $monthYyyyMm;
        $params['month_start'] = $monthYyyyMm;
    }
    $sql .= ' ORDER BY id ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        leave_sync_attendance($pdo, (int)$row['id']);
    }
}

/**
 * Approved leave days for a month from live `leaves` (not attendance).
 *
 * @return array<string, array{status: string, category: string, leave_id: int}>
 */
function attendance_leave_approved_day_map(PDO $pdo, int $employeeId, string $monthYyyyMm): array
{
    $map = [];
    $st = $pdo->prepare("SELECT id, from_date, to_date, start_date, end_date, leave_category, day_allocation_json
        FROM leaves
        WHERE employee_id = :eid AND status = 'Approved'
          AND DATE_FORMAT(COALESCE(from_date, start_date), '%Y-%m') <= :month_end
          AND DATE_FORMAT(COALESCE(to_date, end_date), '%Y-%m') >= :month_start");
    $st->execute(['eid' => $employeeId, 'month_end' => $monthYyyyMm, 'month_start' => $monthYyyyMm]);
    while ($leave = $st->fetch(PDO::FETCH_ASSOC)) {
        $leaveId = (int)$leave['id'];
        $dayMap = [];
        if (!empty($leave['day_allocation_json'])) {
            $decoded = json_decode((string)$leave['day_allocation_json'], true);
            if (is_array($decoded)) {
                $dayMap = $decoded;
            }
        }
        if (!$dayMap) {
            $from = (string)($leave['from_date'] ?? $leave['start_date'] ?? '');
            $to = (string)($leave['to_date'] ?? $leave['end_date'] ?? '');
            $cat = (string)($leave['leave_category'] ?? 'Paid');
            foreach (leave_date_range($from, $to) as $ymd) {
                $dayMap[$ymd] = $cat;
            }
        }
        foreach ($dayMap as $ymd => $cat) {
            if (!str_starts_with($ymd, $monthYyyyMm)) {
                continue;
            }
            if (in_array($cat, ['Holiday', 'Weekly Off'], true)) {
                continue;
            }
            $map[$ymd] = [
                'status' => leave_attendance_status_for_category((string)$cat),
                'category' => (string)$cat,
                'leave_id' => $leaveId,
            ];
        }
    }

    return $map;
}

/**
 * Live month calendar: attendance (punch/work) + authoritative approved leaves.
 *
 * @return array{
 *   days: array<string, array<string, mixed>>,
 *   rows: list<array<string, mixed>>,
 *   leave_days: int,
 *   has_records: bool
 * }
 */
function attendance_fetch_month_view(PDO $pdo, int $employeeId, string $monthYyyyMm): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $monthYyyyMm)) {
        $monthYyyyMm = date('Y-m');
    }

    attendance_leave_reconcile($pdo, $employeeId, $monthYyyyMm);

    $approvedLeaveDays = attendance_leave_approved_day_map($pdo, $employeeId, $monthYyyyMm);

    $st = $pdo->prepare("SELECT *
        FROM attendance
        WHERE employee_id = :eid AND DATE_FORMAT(attendance_date, '%Y-%m') = :month
        ORDER BY attendance_date ASC");
    $st->execute(['eid' => $employeeId, 'month' => $monthYyyyMm]);
    $attRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $days = [];
    $leaveDayCount = count($approvedLeaveDays);

    foreach ($attRows as $row) {
        $date = (string)$row['attendance_date'];
        $hasPunch = !empty($row['punch_in_time']) || !empty($row['punch_out_time']);
        $status = (string)$row['status'];

        if (attendance_is_leave_derived_status($status) && !$hasPunch && !isset($approvedLeaveDays[$date])) {
            continue;
        }

        $days[$date] = $row;
    }

    foreach ($approvedLeaveDays as $date => $leaveInfo) {
        if (isset($days[$date])) {
            $existing = $days[$date];
            $hasPunch = !empty($existing['punch_in_time']) || !empty($existing['punch_out_time']);
            if (!$hasPunch) {
                $days[$date]['status'] = $leaveInfo['status'];
                $days[$date]['remarks'] = 'Leave #' . $leaveInfo['leave_id'];
                $days[$date]['linked_leave_id'] = $leaveInfo['leave_id'];
            }
        } else {
            $days[$date] = [
                'attendance_date' => $date,
                'shift' => 'Morning',
                'status' => $leaveInfo['status'],
                'remarks' => 'Leave #' . $leaveInfo['leave_id'],
                'linked_leave_id' => $leaveInfo['leave_id'],
                'punch_in_time' => null,
                'punch_out_time' => null,
                'total_hours' => null,
                'overtime_hours' => 0,
                'is_late' => 0,
                'is_early_exit' => 0,
                'needs_verification' => 0,
            ];
        }
    }

    $history = array_values($days);
    usort($history, static fn($a, $b) => strcmp((string)$b['attendance_date'], (string)$a['attendance_date']));

    return [
        'days' => $days,
        'rows' => $history,
        'leave_days' => $leaveDayCount,
        'has_records' => $days !== [],
    ];
}

/**
 * Dashboard / summary stats from live DB after reconcile.
 *
 * @return array{
 *   by_status: array<string, int>,
 *   total_marked: int,
 *   present: int,
 *   half_days: int,
 *   late: int,
 *   absent: int,
 *   leave_days: int,
 *   unpaid_leave_days: int,
 *   overtime_hours: float
 * }
 */
function attendance_month_summary_live(PDO $pdo, int $employeeId, string $monthYyyyMm): array
{
    $view = attendance_fetch_month_view($pdo, $employeeId, $monthYyyyMm);
    $byStatus = [];
    $present = 0;
    $half = 0;
    $late = 0;
    $absent = 0;
    $leave = 0;
    $unpaidLeave = 0;
    $ot = 0.0;

    foreach ($view['days'] as $row) {
        $st = (string)$row['status'];
        $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
        $ot += (float)($row['overtime_hours'] ?? 0);
        if ($st === 'Late') {
            $late++;
            $present++;
        } elseif (in_array($st, ['Present', 'Emergency Duty'], true)) {
            $present++;
        } elseif ($st === 'Half Day') {
            $half++;
        } elseif ($st === 'Absent') {
            $absent++;
        } elseif (attendance_is_leave_derived_status($st)) {
            $leave++;
            if ($st === 'Unpaid Leave') {
                $unpaidLeave++;
            }
        }
    }

    $ust = $pdo->prepare("SELECT COALESCE(SUM(unpaid_days), 0) FROM leaves
        WHERE employee_id = :eid AND status = 'Approved'
        AND DATE_FORMAT(COALESCE(from_date, start_date), '%Y-%m') <= :m_end
        AND DATE_FORMAT(COALESCE(to_date, end_date), '%Y-%m') >= :m_start");
    $ust->execute(['eid' => $employeeId, 'm_end' => $monthYyyyMm, 'm_start' => $monthYyyyMm]);
    $unpaidFromLeaves = (float)$ust->fetchColumn();

    return [
        'by_status' => $byStatus,
        'total_marked' => count($view['days']),
        'present' => $present,
        'half_days' => $half,
        'late' => $late,
        'absent' => $absent,
        'leave_days' => $view['leave_days'],
        'unpaid_leave_days' => max($unpaidLeave, (int)round($unpaidFromLeaves)),
        'overtime_hours' => round($ot, 2),
    ];
}
