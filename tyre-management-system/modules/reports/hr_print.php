<?php
declare(strict_types=1);
/** Professional print / PDF layout for HR operational reports. */
$summary = $bundle['summary'];
$totals = $bundle['totals'];
$generated = date('d M Y, h:i A');
$periodLabel = $filters['from'] . ' to ' . $filters['to'];
$deptFilter = $filters['department_id'] > 0 ? 'Selected department' : 'All departments';
$typeFilter = $filters['employee_type'] !== '' ? $filters['employee_type'] : 'All types';
$autoPrint = ($_GET['export'] ?? '') === 'pdf';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HR Report — <?= e($periodLabel) ?></title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 16px;
            line-height: 1.4;
        }
        .doc-header {
            border-bottom: 2px solid #b91c1c;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .doc-header h1 {
            font-size: 16px;
            margin: 0 0 2px;
            color: #991b1b;
            font-weight: 700;
        }
        .doc-header .company { font-size: 13px; font-weight: 600; margin: 0 0 6px; }
        .meta { font-size: 10px; color: #64748b; }
        .meta span { display: inline-block; margin-right: 14px; }
        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .summary-item {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            border-left: 3px solid #b91c1c;
        }
        .summary-item span { display: block; font-size: 9px; text-transform: uppercase; color: #64748b; }
        .summary-item strong { font-size: 14px; }
        h2 {
            font-size: 12px;
            margin: 16px 0 6px;
            color: #1e293b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 10px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #475569;
        }
        td.num, th.num { text-align: right; }
        tr.total td { font-weight: 700; background: #f8fafc; }
        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #64748b;
            display: flex;
            justify-content: space-between;
        }
        .signatures {
            margin-top: 28px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 24px;
            font-size: 10px;
        }
        .signatures .line {
            border-top: 1px solid #94a3b8;
            margin-top: 36px;
            padding-top: 4px;
            color: #64748b;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            h2 { page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="doc-header">
        <p class="company"><?= e($companyName) ?></p>
        <h1>HR Operational Report</h1>
        <div class="meta">
            <span><strong>Period:</strong> <?= e($periodLabel) ?></span>
            <span><strong>Department:</strong> <?= e($deptFilter) ?></span>
            <span><strong>Employee type:</strong> <?= e($typeFilter) ?></span>
            <span><strong>Generated:</strong> <?= e($generated) ?></span>
        </div>
    </div>

    <div class="summary">
        <div class="summary-item"><span>Present %</span><strong><?= e($summary['present_pct']) ?></strong></div>
        <div class="summary-item"><span>Payroll Amount</span><strong><?= e($summary['payroll_amount']) ?></strong></div>
        <div class="summary-item"><span>Leave Requests</span><strong><?= e((string)$summary['leave_requests']) ?></strong></div>
        <div class="summary-item"><span>Overtime Hours</span><strong><?= e($summary['overtime_hours']) ?></strong></div>
    </div>

    <h2>Employee Attendance Report</h2>
    <table>
        <thead>
        <tr>
            <th>Employee ID</th><th>Name</th><th>Department</th>
            <th class="num">Present</th><th class="num">Absent</th><th class="num">Leave</th><th class="num">Late</th><th class="num">OT Hrs</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$bundle['attendance']): ?>
            <tr><td colspan="8">No records</td></tr>
        <?php else: ?>
            <?php foreach ($bundle['attendance'] as $r): ?>
                <tr>
                    <td><?= e((string)$r['employee_code']) ?></td>
                    <td><?= e((string)$r['full_name']) ?></td>
                    <td><?= e((string)$r['department_name']) ?></td>
                    <td class="num"><?= (int)$r['present_days'] ?></td>
                    <td class="num"><?= (int)$r['absent_days'] ?></td>
                    <td class="num"><?= (int)$r['leave_days'] ?></td>
                    <td class="num"><?= (int)$r['late_count'] ?></td>
                    <td class="num"><?= e(number_format((float)$r['ot_hours'], 1)) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total">
                <td colspan="3">Total</td>
                <td class="num"><?= (int)$totals['attendance_present'] ?></td>
                <td class="num"><?= (int)$totals['attendance_absent'] ?></td>
                <td colspan="3"></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Payroll Register</h2>
    <table>
        <thead>
        <tr>
            <th>Employee</th><th>Dept</th><th class="num">Gross</th><th class="num">PF</th><th class="num">ESI</th>
            <th class="num">Deductions</th><th class="num">OT</th><th class="num">Net</th><th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$bundle['payroll']): ?>
            <tr><td colspan="9">No records</td></tr>
        <?php else: ?>
            <?php foreach ($bundle['payroll'] as $r): ?>
                <tr>
                    <td><?= e((string)$r['full_name']) ?></td>
                    <td><?= e((string)$r['department_name']) ?></td>
                    <td class="num"><?= e(number_format((float)$r['gross_salary'], 2)) ?></td>
                    <td class="num"><?= e(number_format((float)$r['pf_amount'], 2)) ?></td>
                    <td class="num"><?= e(number_format((float)$r['esi_amount'], 2)) ?></td>
                    <td class="num"><?= e(number_format((float)$r['deductions'], 2)) ?></td>
                    <td class="num"><?= e(number_format((float)$r['ot_amount'], 2)) ?></td>
                    <td class="num"><?= e(number_format((float)$r['net_salary'], 2)) ?></td>
                    <td><?= e(ucfirst((string)$r['payment_status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="num"><?= e(number_format((float)$totals['payroll_gross'], 2)) ?></td>
                <td colspan="3"></td>
                <td class="num"><?= e(number_format((float)$totals['payroll_net'], 2)) ?></td>
                <td></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Leave Report</h2>
    <table>
        <thead>
        <tr>
            <th>Employee</th><th>Department</th><th class="num">Days</th><th class="num">Paid</th>
            <th class="num">Half</th><th class="num">Unpaid</th><th class="num">Pending</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$bundle['leave']): ?>
            <tr><td colspan="7">No records</td></tr>
        <?php else: ?>
            <?php foreach ($bundle['leave'] as $r): ?>
                <tr>
                    <td><?= e((string)$r['full_name']) ?></td>
                    <td><?= e((string)$r['department_name']) ?></td>
                    <td class="num"><?= e(number_format((float)$r['leave_days'], 1)) ?></td>
                    <td class="num"><?= e(number_format((float)$r['paid_leave'], 1)) ?></td>
                    <td class="num"><?= e(number_format((float)$r['half_paid'], 1)) ?></td>
                    <td class="num"><?= e(number_format((float)$r['unpaid'], 1)) ?></td>
                    <td class="num"><?= (int)$r['pending_requests'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Department Summary</h2>
    <table>
        <thead>
        <tr>
            <th>Department</th><th class="num">Employees</th><th class="num">Present %</th>
            <th class="num">Leave %</th><th class="num">OT Hrs</th><th class="num">Payroll</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($bundle['departments'] as $d): ?>
            <tr>
                <td><?= e((string)$d['department']) ?></td>
                <td class="num"><?= (int)$d['employees'] ?></td>
                <td class="num"><?= (int)$d['present_pct'] ?>%</td>
                <td class="num"><?= (int)$d['leave_pct'] ?>%</td>
                <td class="num"><?= e(number_format((float)$d['ot_hours'], 1)) ?></td>
                <td class="num"><?= e(number_format((float)$d['payroll_cost'], 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="signatures">
        <div><div class="line">Prepared by (HR)</div></div>
        <div><div class="line">Verified by</div></div>
        <div><div class="line">Approved by</div></div>
    </div>

    <div class="footer">
        <span><?= e($companyName) ?> — Confidential</span>
        <span>Generated <?= e($generated) ?></span>
    </div>

    <?php if ($autoPrint): ?>
    <script>window.onload = function () { window.print(); };</script>
    <?php endif; ?>
</body>
</html>
