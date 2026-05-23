<?php
declare(strict_types=1);
/** @var array<string, mixed> $filters */
/** @var list<array<string, string>> $exportRows */
/** @var string $companyName */
/** @var int $totalFiltered */
$autoPrint = ($_GET['export'] ?? '') === 'pdf';
$generated = date('d M Y, h:i A');
$summary = emp_list_filter_summary_label($filters);
$logoUrl = emp_list_company_logo_url();
$activeN = 0;
$inactiveN = 0;
foreach ($exportRows as $r) {
    if (strtolower($r['status']) === 'active') {
        $activeN++;
    } else {
        $inactiveN++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Directory — <?= e($companyName) ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 14px; }
        .doc-header { display: flex; gap: 14px; align-items: flex-start; border-bottom: 2px solid #b91c1c; padding-bottom: 10px; margin-bottom: 12px; }
        .doc-header img { max-height: 48px; max-width: 120px; object-fit: contain; }
        .doc-header h1 { font-size: 16px; margin: 0 0 2px; color: #991b1b; }
        .company { font-size: 13px; font-weight: 600; margin: 0 0 4px; }
        .meta { font-size: 9px; color: #64748b; line-height: 1.5; }
        .summary { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .summary-item { border: 1px solid #e2e8f0; border-left: 3px solid #b91c1c; padding: 6px 10px; min-width: 100px; }
        .summary-item span { display: block; font-size: 8px; text-transform: uppercase; color: #64748b; }
        .summary-item strong { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th { background: #1e293b; color: #f8fafc; text-align: left; padding: 5px 6px; font-size: 8px; text-transform: uppercase; }
        td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; vertical-align: top; }
        td.num { text-align: right; }
        .footer { margin-top: 16px; font-size: 8px; color: #94a3b8; display: flex; justify-content: space-between; }
        @media print { .no-print { display: none !important; } }
    </style>
    <?php if ($autoPrint): ?><script>window.onload = function () { window.print(); };</script><?php endif; ?>
</head>
<body>
    <div class="doc-header">
        <?php if ($logoUrl): ?>
            <img src="<?= e($logoUrl) ?>" alt="Company logo">
        <?php endif; ?>
        <div>
            <p class="company"><?= e($companyName) ?></p>
            <h1>Employee Directory</h1>
            <div class="meta">
                <div><strong>Filters:</strong> <?= e($summary) ?></div>
                <div><strong>Generated:</strong> <?= e($generated) ?></div>
            </div>
        </div>
    </div>

    <div class="summary">
        <div class="summary-item"><span>Total (filtered)</span><strong><?= e((string)$totalFiltered) ?></strong></div>
        <div class="summary-item"><span>Active</span><strong><?= e((string)$activeN) ?></strong></div>
        <div class="summary-item"><span>Inactive</span><strong><?= e((string)$inactiveN) ?></strong></div>
        <div class="summary-item"><span>Staff / Worker</span><strong><?= e((string)count(array_filter($exportRows, static fn($r) => $r['employee_type'] === 'Staff'))) ?> / <?= e((string)count(array_filter($exportRows, static fn($r) => $r['employee_type'] === 'Worker'))) ?></strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Code</th><th>Employee</th><th>Department</th><th>Designation</th>
                <th>Shift</th><th>Type</th><th class="num">Gross (₹)</th><th>Status</th><th>Joined</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$exportRows): ?>
            <tr><td colspan="9" style="text-align:center;color:#94a3b8;">No employees match the selected filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($exportRows as $r): ?>
            <tr>
                <td><?= e($r['employee_code']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['department']) ?></td>
                <td><?= e($r['designation']) ?></td>
                <td><?= e($r['shift']) ?></td>
                <td><?= e($r['employee_type']) ?></td>
                <td class="num"><?= e(number_format((float)$r['gross_salary'], 0)) ?></td>
                <td><?= e($r['status']) ?></td>
                <td><?= e($r['joining_date'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <span><?= e($companyName) ?> · HR Employee Directory</span>
        <span>Printed <?= e($generated) ?></span>
    </div>
    <p class="no-print" style="margin-top:12px;"><button type="button" onclick="window.print()">Print</button></p>
</body>
</html>
