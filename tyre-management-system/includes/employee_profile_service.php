<?php
declare(strict_types=1);

require_once __DIR__ . '/employee_list_service.php';
require_once __DIR__ . '/payroll_service.php';
require_once __DIR__ . '/employee_credentials.php';

/** @return array{from:string,to:string,label:string} */
function emp_profile_month_window(): array
{
    $from = date('Y-m-01');
    $to = date('Y-m-t');

    return [
        'from' => $from,
        'to' => $to,
        'label' => date('F Y'),
    ];
}

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function emp_list_enrich_rows(PDO $pdo, array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $ids = array_values(array_filter(array_map(static fn($r) => (int)($r['id'] ?? 0), $rows), static fn(int $id) => $id > 0));
    if ($ids === []) {
        return $rows;
    }

    $win = emp_profile_month_window();
    $machines = emp_profile_batch_machines($pdo, $ids);
    $attendance = emp_profile_batch_attendance($pdo, $ids, $win['from'], $win['to']);

    foreach ($rows as &$r) {
        $id = (int)($r['id'] ?? 0);
        $att = $attendance[$id] ?? ['total' => 0, 'present' => 0, 'ot' => 0.0, 'pct' => 0];
        $r['emp_machine'] = $machines[$id] ?? '—';
        $r['emp_att_pct'] = $att['pct'];
        $r['emp_ot_hours'] = $att['ot'];
        $r['emp_att_present'] = $att['present'];
        $r['emp_att_total'] = $att['total'];
    }
    unset($r);

    return $rows;
}

/** @param list<int> $employeeIds @return array<int, string> */
function emp_profile_batch_machines(PDO $pdo, array $employeeIds): array
{
    try {
        $ph = implode(',', array_fill(0, count($employeeIds), '?'));
        $sql = "SELECT a.employee_id,
                GROUP_CONCAT(CONCAT(m.machine_code, ' · ', m.machine_name) ORDER BY m.machine_code SEPARATOR '; ') AS machines
            FROM machine_operator_assignments a
            INNER JOIN machines m ON m.id = a.machine_id
            WHERE a.is_active = 1 AND a.employee_id IN ({$ph})
            GROUP BY a.employee_id";
        $st = $pdo->prepare($sql);
        $st->execute($employeeIds);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['employee_id']] = (string)$row['machines'];
        }

        return $out;
    } catch (Throwable) {
        return [];
    }
}

/**
 * @param list<int> $employeeIds
 * @return array<int, array{total:int,present:int,ot:float,pct:int}>
 */
function emp_profile_batch_attendance(PDO $pdo, array $employeeIds, string $from, string $to): array
{
    try {
        $ph = implode(',', array_fill(0, count($employeeIds), '?'));
        $sql = "SELECT employee_id,
                COUNT(*) AS total_days,
                SUM(CASE WHEN status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS present_days,
                COALESCE(SUM(overtime_hours), 0) AS ot_hours
            FROM attendance
            WHERE employee_id IN ({$ph}) AND attendance_date BETWEEN ? AND ?
            GROUP BY employee_id";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge($employeeIds, [$from, $to]));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (int)$row['total_days'];
            $present = (int)$row['present_days'];
            $out[(int)$row['employee_id']] = [
                'total' => $total,
                'present' => $present,
                'ot' => (float)$row['ot_hours'],
                'pct' => $total > 0 ? (int)round(($present / $total) * 100) : 0,
            ];
        }

        return $out;
    } catch (Throwable) {
        return [];
    }
}

/** @return array<string, mixed> */
function emp_profile_build(PDO $pdo, array $employeeRow, array $increments = []): array
{
    $empId = (int)($employeeRow['id'] ?? 0);
    $month = date('Y-m');
    $win = emp_profile_month_window();

    $att = [
        'label' => $win['label'],
        'from' => $win['from'],
        'to' => $win['to'],
        'present_days' => (int)($employeeRow['emp_att_present'] ?? 0),
        'total_days' => (int)($employeeRow['emp_att_total'] ?? 0),
        'present_pct' => (int)($employeeRow['emp_att_pct'] ?? 0),
        'ot_hours' => (float)($employeeRow['emp_ot_hours'] ?? 0),
    ];

    if ($att['total_days'] === 0 && $empId > 0) {
        $single = emp_profile_batch_attendance($pdo, [$empId], $win['from'], $win['to']);
        $attRow = $single[$empId] ?? ['total' => 0, 'present' => 0, 'ot' => 0.0, 'pct' => 0];
        $att['present_days'] = $attRow['present'];
        $att['total_days'] = $attRow['total'];
        $att['present_pct'] = $attRow['pct'];
        $att['ot_hours'] = $attRow['ot'];
    }

    $leaveUsed = ['paid_leave_days' => 0.0, 'half_paid_leave_days' => 0.0, 'unpaid_leave_days' => 0.0];
    if ($empId > 0 && function_exists('payroll_fetch_leave_summary')) {
        try {
            $leaveUsed = payroll_fetch_leave_summary($pdo, $empId, $month);
        } catch (Throwable) {
            // ignore
        }
    }

    $paidLimit = (float)($employeeRow['paid_leave_limit'] ?? 0);
    $halfLimit = (float)($employeeRow['half_paid_leave_limit'] ?? 0);

    return [
        'employee' => $employeeRow,
        'increments' => $increments,
        'attendance' => $att,
        'machine' => (string)($employeeRow['emp_machine'] ?? '—'),
        'leave' => [
            'paid_limit' => $paidLimit,
            'paid_used' => (float)($leaveUsed['paid_leave_days'] ?? 0),
            'paid_balance' => max(0, $paidLimit - (float)($leaveUsed['paid_leave_days'] ?? 0)),
            'half_paid_limit' => $halfLimit,
            'half_paid_used' => (float)($leaveUsed['half_paid_leave_days'] ?? 0),
            'half_paid_balance' => max(0, $halfLimit - (float)($leaveUsed['half_paid_leave_days'] ?? 0)),
        ],
        'gross' => emp_list_row_gross($employeeRow),
    ];
}

function emp_profile_print_url(array $filters, int $employeeId, string $mode = 'pdf'): string
{
    return emp_list_build_url($filters, [
        'emp_profile' => (string)$employeeId,
        'profile_export' => $mode,
        'page' => $filters['page'] ?? 1,
    ]);
}

/** @param array<string,mixed> $filters */
function emp_profile_render_export(PDO $pdo, array $filters, int $employeeId, string $export): void
{
    [$where, $params] = emp_list_where_sql($filters);
    $params['eid'] = $employeeId;
    $sql = emp_list_select_sql() . emp_list_join_sql() . $where . ' AND e.id = :eid LIMIT 1';
    $st = $pdo->prepare($sql);
    emp_list_bind_params($st, $params);
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo 'Employee not found.';
        exit;
    }

    $row = emp_list_enrich_rows($pdo, [$row])[0];
    $increments = emp_list_increments_for_employees($pdo, [$employeeId], 10)[$employeeId] ?? [];
    $profile = emp_profile_build($pdo, $row, $increments);
    $companyName = hr_reports_company_name($pdo);

    if ($export === 'pdf' || $export === 'print') {
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../modules/employees/profile_print.php';
        exit;
    }
}
