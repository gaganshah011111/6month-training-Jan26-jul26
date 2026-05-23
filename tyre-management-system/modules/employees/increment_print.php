<?php
declare(strict_types=1);
/** @var array<string, mixed> $incFilters */
/** @var list<array<string, string>> $incExportRows */
/** @var string $companyName */
/** @var int $incTotal */
$autoPrint = ($_GET['inc_export'] ?? '') === 'pdf';
$generated = date('d M Y, h:i A');
$summary = emp_inc_filter_summary($incFilters);
$logoUrl = emp_list_company_logo_url();
$totalAmount = 0.0;
foreach ($incExportRows as $r) {
    $totalAmount += (float)$r['increment_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Increments — <?= e($companyName) ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 14px; }
        .doc-header { display: flex; gap: 14px; border-bottom: 2px solid #b91c1c; padding-bottom: 10px; margin-bottom: 12px; }
        .doc-header img { max-height: 48px; }
        .company { font-size: 13px; font-weight: 600; margin: 0 0 4px; }
        h1 { font-size: 16px; margin: 0; color: #991b1b; }
        .meta { font-size: 9px; color: #64748b; margin-top: 4px; }
        .summary { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .summary-item { border: 1px solid #e2e8f0; border-left: 3px solid #b91c1c; padding: 6px 10px; }
        .summary-item span { display: block; font-size: 8px; text-transform: uppercase; color: #64748b; }
        .summary-item strong { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th { background: #1e293b; color: #fff; text-align: left; padding: 5px 6px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; }
        td.num { text-align: right; }
        .footer { margin-top: 14px; font-size: 8px; color: #94a3b8; display: flex; justify-content: space-between; }
        @media print { .no-print { display: none !important; } }
    </style>
    <?php if ($autoPrint): ?><script>window.onload = function () { window.print(); };</script><?php endif; ?>
</head>
<body>
    <div class="doc-header">
        <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt="Logo"><?php endif; ?>
        <div>
            <p class="company"><?= e($companyName) ?></p>
            <h1>Salary Increment History</h1>
            <div class="meta"><strong>Filters:</strong> <?= e($summary) ?> · <strong>Generated:</strong> <?= e($generated) ?></div>
        </div>
    </div>
    <div class="summary">
        <div class="summary-item"><span>Records</span><strong><?= e((string)$incTotal) ?></strong></div>
        <div class="summary-item"><span>Total increment (₹)</span><strong><?= e(number_format($totalAmount, 2)) ?></strong></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Code</th><th>Employee</th><th>Department</th>
                <th class="num">Old</th><th class="num">New</th><th class="num">Amount</th>
                <th class="num">%</th><th>Effective</th><th>Reason</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$incExportRows): ?>
            <tr><td colspan="9" style="text-align:center;color:#94a3b8;">No records match filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($incExportRows as $r): ?>
            <tr>
                <td><?= e($r['employee_code']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['department']) ?></td>
                <td class="num"><?= e(number_format((float)$r['old_salary'], 2)) ?></td>
                <td class="num"><?= e(number_format((float)$r['new_salary'], 2)) ?></td>
                <td class="num"><?= e(number_format((float)$r['increment_amount'], 2)) ?></td>
                <td class="num"><?= e($r['increment_percentage']) ?></td>
                <td><?= e($r['effective_date']) ?></td>
                <td><?= e($r['reason'] !== '' ? $r['reason'] : '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="footer">
        <span><?= e($companyName) ?> · HR Salary Increments</span>
        <span><?= e($generated) ?></span>
    </div>
    <p class="no-print" style="margin-top:12px;"><button type="button" onclick="window.print()">Print</button></p>
</body>
</html>
