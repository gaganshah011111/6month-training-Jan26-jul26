<?php
declare(strict_types=1);
$autoPrint = ($export ?? '') === 'pdf';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QC Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; padding: 16px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; }
        th { background: #1e293b; color: #fff; }
        .text-end { text-align: right; }
    </style>
    <?php if ($autoPrint): ?><script>window.onload=function(){window.print();}</script><?php endif; ?>
</head>
<body>
    <h1>Quality Control Report</h1>
    <p>Period: <?= e($from) ?> to <?= e($to) ?> · Pass rate: <?= e((string)$sum['pass_pct']) ?>%</p>
    <table>
        <thead>
            <tr><th>Batch</th><th>Date</th><th>Tyre</th><th class="text-end">Inspected</th><th class="text-end">Passed</th><th class="text-end">Reject</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e((string)$r['batch_code']) ?></td>
                <td><?= e((string)$r['inspection_date']) ?></td>
                <td><?= e((string)$r['tyre_type']) ?></td>
                <td class="text-end"><?= e((string)$r['inspected_qty']) ?></td>
                <td class="text-end"><?= e((string)$r['passed_qty']) ?></td>
                <td class="text-end"><?= e((string)$r['rejected_qty']) ?></td>
                <td><?= e((string)$r['qc_status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
