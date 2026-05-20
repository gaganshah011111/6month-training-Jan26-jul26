<?php
declare(strict_types=1);

require_once __DIR__ . '/hr_dashboard_service.php';

/** @return array{from:string,to:string,department_id:int,employee_type:string,prev_from:string,prev_to:string} */
function hr_reports_parse_filters(array $input): array
{
    $from = (string)($input['from'] ?? date('Y-m-01'));
    $to = (string)($input['to'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = date('Y-m-d');
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    $deptId = (int)($input['department_id'] ?? 0);
    $empType = trim((string)($input['employee_type'] ?? ''));
    if (!in_array($empType, ['Staff', 'Worker'], true)) {
        $empType = '';
    }

    $days = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
    $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
    $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

    return [
        'from' => $from,
        'to' => $to,
        'department_id' => $deptId,
        'employee_type' => $empType,
        'prev_from' => $prevFrom,
        'prev_to' => $prevTo,
    ];
}

/** @return array{0:string,1:array<string,mixed>} */
function hr_reports_emp_sql(array $filters, string $alias = 'e'): array
{
    $sql = '';
    $params = [];
    if ($filters['department_id'] > 0) {
        $sql .= " AND {$alias}.department_id = :dept_id";
        $params['dept_id'] = $filters['department_id'];
    }
    if ($filters['employee_type'] !== '') {
        $sql .= " AND {$alias}.employee_type = :etype";
        $params['etype'] = $filters['employee_type'];
    }

    return [$sql, $params];
}

/** @return array<string,mixed> */
function hr_reports_summary_kpis(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $from = $filters['from'];
    $to = $filters['to'];
    $pf = $filters['prev_from'];
    $pt = $filters['prev_to'];

    $baseAtt = "FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}";
    $params = array_merge(['from' => $from, 'to' => $to], $empParams);

    $st = $pdo->prepare("SELECT COUNT(*) {$baseAtt}");
    $st->execute($params);
    $attCount = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) {$baseAtt}");
    $st->execute(array_merge(['from' => $pf, 'to' => $pt], $empParams));
    $attPrev = (int)$st->fetchColumn();

    $presentSql = "SELECT COUNT(*) {$baseAtt} AND a.status IN ('Present','Late','Half Day','Emergency Duty')";
    $st = $pdo->prepare($presentSql);
    $st->execute($params);
    $presentN = (int)$st->fetchColumn();
    $presentPct = $attCount > 0 ? round(($presentN / $attCount) * 100) : 0;

    $st = $pdo->prepare($presentSql);
    $st->execute(array_merge(['from' => $pf, 'to' => $pt], $empParams));
    $presentPrevN = (int)$st->fetchColumn();
    $attPrevForPct = max(1, $attPrev);
    $presentPctPrev = round(($presentPrevN / $attPrevForPct) * 100);

    $paySql = "SELECT COALESCE(SUM(s.net_salary),0), COUNT(*) FROM salaries s
        INNER JOIN employees e ON e.id = s.employee_id
        WHERE s.month_year BETWEEN DATE_FORMAT(:from,'%Y-%m') AND DATE_FORMAT(:to,'%Y-%m') {$empSql}";
    $st = $pdo->prepare($paySql);
    $st->execute($params);
    [$payExpense, $payrollGen] = $st->fetch(PDO::FETCH_NUM) ?: [0, 0];
    $payExpense = (float)$payExpense;
    $payrollGen = (int)$payrollGen;

    $st = $pdo->prepare($paySql);
    $st->execute(array_merge(['from' => $pf, 'to' => $pt], $empParams));
    [$payPrev] = $st->fetch(PDO::FETCH_NUM) ?: [0];

    $leaveSql = "SELECT COUNT(*) FROM leaves l INNER JOIN employees e ON e.id = l.employee_id
        WHERE l.status = 'Approved' AND COALESCE(l.from_date,l.start_date) <= :to
        AND COALESCE(l.to_date,l.end_date) >= :from {$empSql}";
    $st = $pdo->prepare($leaveSql);
    $st->execute($params);
    $leavesApproved = (int)$st->fetchColumn();

    $st = $pdo->prepare($leaveSql);
    $st->execute(array_merge(['from' => $pf, 'to' => $pt], $empParams));
    $leavesPrev = (int)$st->fetchColumn();

    $otSql = "SELECT COALESCE(SUM(a.overtime_hours),0) {$baseAtt}";
    $st = $pdo->prepare($otSql);
    $st->execute($params);
    $otHours = (float)$st->fetchColumn();

    $st = $pdo->prepare($otSql);
    $st->execute(array_merge(['from' => $pf, 'to' => $pt], $empParams));
    $otPrev = (float)$st->fetchColumn();

    $absSql = "SELECT COUNT(DISTINCT a.employee_id) {$baseAtt} AND a.status = 'Absent'";
    $st = $pdo->prepare($absSql);
    $st->execute($params);
    $absentEmp = (int)$st->fetchColumn();

    $st = $pdo->prepare($absSql);
    $st->execute(array_merge(['from' => $pf, 'to' => $pt], $empParams));
    $absentPrev = (int)$st->fetchColumn();

    $lateSql = "SELECT COUNT(DISTINCT a.employee_id) {$baseAtt} AND (a.status = 'Late' OR a.is_late = 1)";
    $st = $pdo->prepare($lateSql);
    $st->execute($params);
    $lateEmp = (int)$st->fetchColumn();

    $st = $pdo->prepare($lateSql);
    $st->execute(array_merge(['from' => $pf, 'to' => $pt], $empParams));
    $latePrev = (int)$st->fetchColumn();

    $spark = hr_reports_sparkline_attendance($pdo, $filters, 7);

    return [
        'attendance_records' => ['value' => $attCount, 'trend' => hr_dash_trend_label((float)$attCount, (float)$attPrev, true), 'spark' => $spark['total']],
        'payroll_generated' => ['value' => $payrollGen, 'trend' => hr_dash_trend_label((float)$payrollGen, 0, true), 'spark' => []],
        'present_pct' => ['value' => $presentPct . '%', 'raw' => $presentPct, 'trend' => hr_dash_trend_label((float)$presentPct, (float)$presentPctPrev, true), 'spark' => $spark['present']],
        'approved_leaves' => ['value' => $leavesApproved, 'trend' => hr_dash_trend_label((float)$leavesApproved, (float)$leavesPrev, false), 'spark' => []],
        'overtime_hours' => ['value' => number_format($otHours, 1) . 'h', 'raw' => $otHours, 'trend' => hr_dash_trend_label($otHours, $otPrev, false), 'spark' => $spark['ot']],
        'absent_employees' => ['value' => $absentEmp, 'trend' => hr_dash_trend_label((float)$absentEmp, (float)$absentPrev, false), 'spark' => []],
        'late_employees' => ['value' => $lateEmp, 'trend' => hr_dash_trend_label((float)$lateEmp, (float)$latePrev, false), 'spark' => []],
        'payroll_expense' => ['value' => hr_dash_format_money($payExpense), 'raw' => $payExpense, 'trend' => hr_dash_trend_label($payExpense, (float)$payPrev, false), 'spark' => []],
    ];
}

/** @return array{total:list<int>,present:list<int>,ot:list<int>} */
function hr_reports_sparkline_attendance(PDO $pdo, array $filters, int $days = 7): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $end = $filters['to'];
    $start = date('Y-m-d', strtotime($end . ' -' . ($days - 1) . ' days'));
    if ($start < $filters['from']) {
        $start = $filters['from'];
    }

    $st = $pdo->prepare("SELECT a.attendance_date AS d,
            COUNT(*) AS total,
            SUM(CASE WHEN a.status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS present,
            COALESCE(SUM(a.overtime_hours),0) AS ot
        FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.attendance_date BETWEEN :s AND :e AND e.status = 'active' {$empSql}
        GROUP BY a.attendance_date ORDER BY a.attendance_date");
    $st->execute(array_merge(['s' => $start, 'e' => $end], $empParams));
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string)$row['d']] = $row;
    }

    $total = [];
    $present = [];
    $ot = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime($end . " -{$i} days"));
        $row = $map[$d] ?? ['total' => 0, 'present' => 0, 'ot' => 0];
        $total[] = (int)$row['total'];
        $present[] = (int)$row['present'];
        $ot[] = (int)round((float)$row['ot']);
    }

    return compact('total', 'present', 'ot');
}

/** @return array{labels:list<string>,present:list<int>,absent:list<int>,leave:list<int>,late:list<int>} */
function hr_reports_attendance_trend(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $st = $pdo->prepare("SELECT a.attendance_date AS d,
            SUM(CASE WHEN a.status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN a.status LIKE '%Leave%' OR a.status IN ('Paid Leave','Unpaid Leave','Half Paid Leave') THEN 1 ELSE 0 END) AS leave_n,
            SUM(CASE WHEN a.status = 'Late' OR a.is_late = 1 THEN 1 ELSE 0 END) AS late_n
        FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
        GROUP BY a.attendance_date ORDER BY a.attendance_date");
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));

    $labels = [];
    $present = [];
    $absent = [];
    $leave = [];
    $late = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $labels[] = date('d M', strtotime((string)$row['d']));
        $present[] = (int)$row['present'];
        $absent[] = (int)$row['absent'];
        $leave[] = (int)$row['leave_n'];
        $late[] = (int)$row['late_n'];
    }

    return compact('labels', 'present', 'absent', 'leave', 'late');
}

/** @return array{labels:list<string>,values:list<float>} */
function hr_reports_department_attendance(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters, 'e');
    $st = $pdo->prepare("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS dept,
            COUNT(*) AS total,
            SUM(CASE WHEN a.status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS present
        FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
        GROUP BY dept HAVING total > 0 ORDER BY present DESC");
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));

    $labels = [];
    $values = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $labels[] = (string)$row['dept'];
        $total = max(1, (int)$row['total']);
        $values[] = round(((int)$row['present'] / $total) * 100, 1);
    }

    return compact('labels', 'values');
}

/** @return array<string,mixed> */
function hr_reports_payroll_chart(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $st = $pdo->prepare("SELECT s.month_year,
            COALESCE(SUM(s.gross_salary),0) AS gross,
            COALESCE(SUM(s.overtime_amount),0) AS ot,
            COALESCE(SUM(s.total_deduction),0) AS ded,
            COALESCE(SUM(s.net_salary),0) AS net
        FROM salaries s INNER JOIN employees e ON e.id = s.employee_id
        WHERE s.month_year BETWEEN DATE_FORMAT(:from,'%Y-%m') AND DATE_FORMAT(:to,'%Y-%m') {$empSql}
        GROUP BY s.month_year ORDER BY s.month_year");
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return [
        'labels' => array_map(static fn ($r) => date('M Y', strtotime((string)$r['month_year'] . '-01')), $rows),
        'gross' => array_map(static fn ($r) => (float)$r['gross'], $rows),
        'ot' => array_map(static fn ($r) => (float)$r['ot'], $rows),
        'deductions' => array_map(static fn ($r) => (float)$r['ded'], $rows),
        'net' => array_map(static fn ($r) => (float)$r['net'], $rows),
    ];
}

/** @return array<string,float|int> */
function hr_reports_leave_chart(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $st = $pdo->prepare("SELECT
            COALESCE(SUM(l.paid_days),0) AS paid,
            COALESCE(SUM(l.half_paid_days),0) AS half_paid,
            COALESCE(SUM(l.unpaid_days),0) AS unpaid
        FROM leaves l INNER JOIN employees e ON e.id = l.employee_id
        WHERE l.status = 'Approved' AND COALESCE(l.from_date,l.start_date) <= :to
        AND COALESCE(l.to_date,l.end_date) >= :from {$empSql}");
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $rej = $pdo->prepare("SELECT COUNT(*) FROM leaves l INNER JOIN employees e ON e.id = l.employee_id
        WHERE l.status = 'Rejected' AND COALESCE(l.from_date,l.start_date) <= :to
        AND COALESCE(l.to_date,l.end_date) >= :from {$empSql}");
    $rej->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));

    return [
        'paid' => (float)($row['paid'] ?? 0),
        'half_paid' => (float)($row['half_paid'] ?? 0),
        'unpaid' => (float)($row['unpaid'] ?? 0),
        'rejected' => (int)$rej->fetchColumn(),
    ];
}

/** @return array<string,mixed> */
function hr_reports_overtime_summary(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $st = $pdo->prepare("SELECT COALESCE(SUM(a.overtime_hours),0) AS ot_h,
            COALESCE(SUM(a.overtime_hours * COALESCE(e.overtime_rate, e.hourly_rate, 0)),0) AS ot_cost,
            SUM(CASE WHEN a.is_emergency_duty = 1 OR a.status = 'Emergency Duty' THEN 1 ELSE 0 END) AS emerg
        FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}");
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $deptSt = $pdo->prepare("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS dept,
            COALESCE(SUM(a.overtime_hours),0) AS ot
        FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
        GROUP BY dept ORDER BY ot DESC LIMIT 1");
    $deptSt->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $topDept = $deptSt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_hours' => (float)($row['ot_h'] ?? 0),
        'top_department' => (string)($topDept['dept'] ?? '—'),
        'top_dept_hours' => (float)($topDept['ot'] ?? 0),
        'expense' => (float)($row['ot_cost'] ?? 0),
        'emergency' => (int)($row['emerg'] ?? 0),
    ];
}

/** @return array<string,mixed> */
function hr_reports_insights(PDO $pdo, array $filters): array
{
    $depts = hr_reports_department_attendance($pdo, $filters);
    $labels = $depts['labels'];
    $values = $depts['values'];

    $bestDept = '—';
    $worstLate = '—';
    $worstAbsent = '—';
    if ($labels) {
        $maxIdx = array_keys($values, max($values))[0] ?? 0;
        $bestDept = $labels[$maxIdx] ?? '—';
    }

    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $lateSt = $pdo->prepare("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS dept,
            SUM(CASE WHEN a.status = 'Late' OR a.is_late = 1 THEN 1 ELSE 0 END) AS c
        FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
        GROUP BY dept ORDER BY c DESC LIMIT 1");
    $lateSt->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $lateRow = $lateSt->fetch(PDO::FETCH_ASSOC);
    if ($lateRow) {
        $worstLate = (string)$lateRow['dept'];
    }

    $absSt = $pdo->prepare("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS dept,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS c
        FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
        GROUP BY dept ORDER BY c DESC LIMIT 1");
    $absSt->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $absRow = $absSt->fetch(PDO::FETCH_ASSOC);
    if ($absRow) {
        $worstAbsent = (string)$absRow['dept'];
    }

    $perfectSt = $pdo->prepare("SELECT COUNT(*) FROM (
            SELECT e.id FROM employees e
            INNER JOIN attendance a ON a.employee_id = e.id AND a.attendance_date BETWEEN :from AND :to
            WHERE e.status = 'active' {$empSql}
            GROUP BY e.id
            HAVING SUM(CASE WHEN a.status IN ('Absent','Late') OR a.is_late = 1 THEN 1 ELSE 0 END) = 0
            AND COUNT(*) >= 1
        ) t");
    $perfectSt->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $perfect = (int)$perfectSt->fetchColumn();

    return [
        'best_attendance_dept' => $bestDept,
        'most_late_dept' => $worstLate,
        'highest_absent_dept' => $worstAbsent,
        'perfect_attendance' => $perfect,
    ];
}

/** @return list<array<string,mixed>> */
function hr_reports_top_employees(PDO $pdo, array $filters, string $kind, int $limit = 5): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $sql = match ($kind) {
        'attendance' => "SELECT e.full_name, e.employee_code,
                SUM(CASE WHEN a.status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS score,
                COUNT(*) AS total
            FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
            WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
            GROUP BY e.id ORDER BY score DESC, total DESC LIMIT " . (int)$limit,
        'overtime' => "SELECT e.full_name, e.employee_code, COALESCE(SUM(a.overtime_hours),0) AS score
            FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
            WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
            GROUP BY e.id HAVING score > 0 ORDER BY score DESC LIMIT " . (int)$limit,
        'leave' => "SELECT e.full_name, e.employee_code, COALESCE(SUM(l.total_days),0) AS score
            FROM leaves l INNER JOIN employees e ON e.id = l.employee_id
            WHERE l.status = 'Approved' AND COALESCE(l.from_date,l.start_date) <= :to
            AND COALESCE(l.to_date,l.end_date) >= :from {$empSql}
            GROUP BY e.id ORDER BY score DESC LIMIT " . (int)$limit,
        'late' => "SELECT e.full_name, e.employee_code,
                SUM(CASE WHEN a.status = 'Late' OR a.is_late = 1 THEN 1 ELSE 0 END) AS score
            FROM attendance a INNER JOIN employees e ON e.id = a.employee_id
            WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
            GROUP BY e.id HAVING score > 0 ORDER BY score DESC LIMIT " . (int)$limit,
        default => '',
    };
    if ($sql === '') {
        return [];
    }
    $st = $pdo->prepare($sql);
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        if ($kind === 'attendance' && (int)($r['total'] ?? 0) > 0) {
            $r['display'] = round(((int)$r['score'] / (int)$r['total']) * 100) . '%';
        } elseif ($kind === 'overtime') {
            $r['display'] = number_format((float)$r['score'], 1) . 'h';
        } else {
            $r['display'] = (string)($r['score'] ?? 0);
        }
    }

    return $rows;
}

/** @return list<array<string,mixed>> */
function hr_reports_department_performance(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters, 'e');
    $st = $pdo->prepare("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS dept,
            COUNT(*) AS att_total,
            SUM(CASE WHEN a.status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS present_n,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_n,
            SUM(CASE WHEN a.status LIKE '%Leave%' OR a.status IN ('Paid Leave','Unpaid Leave') THEN 1 ELSE 0 END) AS leave_n,
            COALESCE(SUM(a.overtime_hours),0) AS ot_h
        FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
        GROUP BY dept ORDER BY dept");
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $paySt = $pdo->prepare("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS dept,
            COALESCE(SUM(s.net_salary),0) AS payroll
        FROM salaries s INNER JOIN employees e ON e.id = s.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE s.month_year BETWEEN DATE_FORMAT(:from,'%Y-%m') AND DATE_FORMAT(:to,'%Y-%m') {$empSql}
        GROUP BY dept");
    $paySt->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $payMap = [];
    foreach ($paySt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $payMap[(string)$p['dept']] = (float)$p['payroll'];
    }

    $out = [];
    foreach ($rows as $r) {
        $total = max(1, (int)$r['att_total']);
        $dept = (string)$r['dept'];
        $out[] = [
            'department' => $dept,
            'present_pct' => round(((int)$r['present_n'] / $total) * 100),
            'leave_pct' => round(((int)$r['leave_n'] / $total) * 100),
            'absent_pct' => round(((int)$r['absent_n'] / $total) * 100),
            'ot_hours' => round((float)$r['ot_h'], 1),
            'payroll' => $payMap[$dept] ?? 0,
        ];
    }

    return $out;
}

function hr_reports_company_name(PDO $pdo): string
{
    $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_name' LIMIT 1");
    $st->execute();
    $name = (string)($st->fetchColumn() ?: '');

    return $name !== '' ? $name : 'Tyre ERP';
}

/** @return array{present_pct:string,payroll_amount:string,leave_requests:int,overtime_hours:string} */
function hr_reports_summary_simple(PDO $pdo, array $filters): array
{
    $kpis = hr_reports_summary_kpis($pdo, $filters);
    $leave = hr_reports_leave_chart($pdo, $filters);

    return [
        'present_pct' => (string)($kpis['present_pct']['value'] ?? '0%'),
        'payroll_amount' => (string)($kpis['payroll_expense']['value'] ?? '₹0'),
        'leave_requests' => (int)($leave['pending'] ?? 0) + (int)($kpis['approved_leaves']['value'] ?? 0),
        'overtime_hours' => (string)($kpis['overtime_hours']['value'] ?? '0h'),
    ];
}

/** @return list<array<string,mixed>> */
function hr_reports_attendance_table(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $sql = "SELECT e.employee_code, e.full_name,
            COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), '—') AS department_name,
            SUM(CASE WHEN a.status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS present_days,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_days,
            SUM(CASE WHEN a.status LIKE '%Leave%' OR a.status IN ('Paid Leave','Unpaid Leave','Half Paid Leave') THEN 1 ELSE 0 END) AS leave_days,
            SUM(CASE WHEN a.status = 'Late' OR a.is_late = 1 THEN 1 ELSE 0 END) AS late_count,
            COALESCE(SUM(a.overtime_hours), 0) AS ot_hours
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        INNER JOIN attendance a ON a.employee_id = e.id AND a.attendance_date BETWEEN :from AND :to
        WHERE e.status = 'active' {$empSql}
        GROUP BY e.id, e.employee_code, e.full_name, department_name
        ORDER BY e.full_name";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function hr_reports_payroll_table(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);
    $hasPayStatus = (bool)$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'salaries' AND column_name = 'payment_status' LIMIT 1")->fetchColumn();
    $statusCol = $hasPayStatus ? 'MAX(s.payment_status)' : "'Generated'";

    $sql = "SELECT e.full_name, e.employee_code,
            COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), '—') AS department_name,
            COALESCE(SUM(s.gross_salary), 0) AS gross_salary,
            COALESCE(SUM(s.pf_amount), 0) AS pf_amount,
            COALESCE(SUM(s.esi_employee_amount), 0) AS esi_amount,
            COALESCE(SUM(s.total_deduction), 0) AS deductions,
            COALESCE(SUM(s.overtime_amount), 0) AS ot_amount,
            COALESCE(SUM(s.net_salary), 0) AS net_salary,
            {$statusCol} AS payment_status
        FROM salaries s
        INNER JOIN employees e ON e.id = s.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE s.month_year BETWEEN DATE_FORMAT(:from,'%Y-%m') AND DATE_FORMAT(:to,'%Y-%m') {$empSql}
        GROUP BY e.id, e.full_name, e.employee_code, department_name
        ORDER BY e.full_name";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function hr_reports_leave_table(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters);

    $sql = "SELECT e.full_name, e.employee_code,
            COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), '—') AS department_name,
            COALESCE(SUM(CASE WHEN l.status = 'Approved' THEN l.total_days ELSE 0 END), 0) AS leave_days,
            COALESCE(SUM(CASE WHEN l.status = 'Approved' THEN l.paid_days ELSE 0 END), 0) AS paid_leave,
            COALESCE(SUM(CASE WHEN l.status = 'Approved' THEN l.half_paid_days ELSE 0 END), 0) AS half_paid,
            COALESCE(SUM(CASE WHEN l.status = 'Approved' THEN l.unpaid_days ELSE 0 END), 0) AS unpaid,
            SUM(CASE WHEN l.status IN ('Pending','Applied') THEN 1 ELSE 0 END) AS pending_requests
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN leaves l ON l.employee_id = e.id
            AND COALESCE(l.from_date, l.start_date) <= :to
            AND COALESCE(l.to_date, l.end_date) >= :from
        WHERE e.status = 'active' {$empSql}
        GROUP BY e.id, e.full_name, e.employee_code, department_name
        HAVING leave_days > 0 OR half_paid > 0 OR unpaid > 0 OR pending_requests > 0
        ORDER BY e.full_name";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function hr_reports_department_summary_table(PDO $pdo, array $filters): array
{
    [$empSql, $empParams] = hr_reports_emp_sql($filters, 'e');
    $st = $pdo->prepare("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS department,
            COUNT(DISTINCT e.id) AS employees,
            COUNT(*) AS att_total,
            SUM(CASE WHEN a.status IN ('Present','Late','Half Day','Emergency Duty') THEN 1 ELSE 0 END) AS present_n,
            SUM(CASE WHEN a.status LIKE '%Leave%' OR a.status IN ('Paid Leave','Unpaid Leave') THEN 1 ELSE 0 END) AS leave_n,
            COALESCE(SUM(a.overtime_hours), 0) AS ot_hours
        FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE a.attendance_date BETWEEN :from AND :to AND e.status = 'active' {$empSql}
        GROUP BY department ORDER BY department");
    $st->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $paySt = $pdo->prepare("SELECT COALESCE(NULLIF(d.department_name,''), NULLIF(e.department,''), 'Unassigned') AS department,
            COALESCE(SUM(s.net_salary), 0) AS payroll
        FROM salaries s INNER JOIN employees e ON e.id = s.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE s.month_year BETWEEN DATE_FORMAT(:from,'%Y-%m') AND DATE_FORMAT(:to,'%Y-%m') {$empSql}
        GROUP BY department");
    $paySt->execute(array_merge(['from' => $filters['from'], 'to' => $filters['to']], $empParams));
    $payMap = [];
    foreach ($paySt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $payMap[(string)$p['department']] = (float)$p['payroll'];
    }

    $out = [];
    foreach ($rows as $r) {
        $total = max(1, (int)$r['att_total']);
        $dept = (string)$r['department'];
        $out[] = [
            'department' => $dept,
            'employees' => (int)$r['employees'],
            'present_pct' => (int)round(((int)$r['present_n'] / $total) * 100),
            'leave_pct' => (int)round(((int)$r['leave_n'] / $total) * 100),
            'ot_hours' => round((float)$r['ot_hours'], 1),
            'payroll_cost' => $payMap[$dept] ?? 0.0,
        ];
    }

    return $out;
}

/** @return array<string,mixed> */
function hr_reports_bundle(PDO $pdo, array $filters): array
{
    $attendance = hr_reports_attendance_table($pdo, $filters);
    $payroll = hr_reports_payroll_table($pdo, $filters);

    return [
        'summary' => hr_reports_summary_simple($pdo, $filters),
        'attendance' => $attendance,
        'payroll' => $payroll,
        'leave' => hr_reports_leave_table($pdo, $filters),
        'departments' => hr_reports_department_summary_table($pdo, $filters),
        'totals' => [
            'attendance_present' => array_sum(array_map(static fn ($r) => (int)$r['present_days'], $attendance)),
            'attendance_absent' => array_sum(array_map(static fn ($r) => (int)$r['absent_days'], $attendance)),
            'payroll_net' => array_sum(array_map(static fn ($r) => (float)$r['net_salary'], $payroll)),
            'payroll_gross' => array_sum(array_map(static fn ($r) => (float)$r['gross_salary'], $payroll)),
        ],
    ];
}

function hr_reports_export_csv(array $filters, array $bundle, string $companyName): void
{
    $filename = 'hr-report-' . $filters['from'] . '-to-' . $filters['to'] . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, [$companyName]);
    fputcsv($out, ['HR Operational Report', $filters['from'] . ' to ' . $filters['to']]);
    fputcsv($out, ['Generated', date('Y-m-d H:i')]);
    fputcsv($out, []);

    $s = $bundle['summary'];
    fputcsv($out, ['Summary']);
    fputcsv($out, ['Present %', $s['present_pct']]);
    fputcsv($out, ['Payroll Amount', $s['payroll_amount']]);
    fputcsv($out, ['Leave Requests', $s['leave_requests']]);
    fputcsv($out, ['Overtime Hours', $s['overtime_hours']]);
    fputcsv($out, []);

    fputcsv($out, ['Employee Attendance Report']);
    fputcsv($out, ['Employee ID', 'Employee Name', 'Department', 'Present', 'Absent', 'Leave', 'Late', 'OT Hours']);
    foreach ($bundle['attendance'] as $r) {
        fputcsv($out, [
            $r['employee_code'], $r['full_name'], $r['department_name'],
            $r['present_days'], $r['absent_days'], $r['leave_days'], $r['late_count'],
            number_format((float)$r['ot_hours'], 1),
        ]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Payroll Register']);
    fputcsv($out, ['Employee', 'Gross', 'PF', 'ESI', 'Deductions', 'OT', 'Net', 'Status']);
    foreach ($bundle['payroll'] as $r) {
        fputcsv($out, [
            $r['full_name'], $r['gross_salary'], $r['pf_amount'], $r['esi_amount'],
            $r['deductions'], $r['ot_amount'], $r['net_salary'], $r['payment_status'],
        ]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Leave Report']);
    fputcsv($out, ['Employee', 'Department', 'Leave Days', 'Paid', 'Half Paid', 'Unpaid', 'Pending']);
    foreach ($bundle['leave'] as $r) {
        fputcsv($out, [
            $r['full_name'], $r['department_name'], $r['leave_days'],
            $r['paid_leave'], $r['half_paid'], $r['unpaid'], $r['pending_requests'],
        ]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Department Summary']);
    fputcsv($out, ['Department', 'Employees', 'Present %', 'Leave %', 'OT Hours', 'Payroll']);
    foreach ($bundle['departments'] as $d) {
        fputcsv($out, [
            $d['department'], $d['employees'], $d['present_pct'], $d['leave_pct'],
            $d['ot_hours'], $d['payroll_cost'],
        ]);
    }

    fclose($out);
}
