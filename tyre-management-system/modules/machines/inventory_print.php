<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $rows */
/** @var ?string $from */
/** @var ?string $to */
/** @var string $export */
$autoPrint = ($export ?? '') === 'pdf';
$periodLabel = ($from || $to) ? (($from ?? '…') . ' to ' . ($to ?? '…')) : 'All dates';
$hasPeriod = $from !== null || $to !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Machine Inventory — <?= e($periodLabel) ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 12px; }
        h1 { font-size: 15px; margin: 0 0 4px; color: #1a2744; }
        .meta { color: #64748b; margin-bottom: 12px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1a2744; color: #fff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; }
        .text-end { text-align: right; }
    </style>
    <?php if ($autoPrint): ?><script>window.onload = function () { window.print(); };</script><?php endif; ?>
</head>
<body>
    <h1>Machine Inventory Report</h1>
    <p class="meta">Period: <?= e($periodLabel) ?> · Generated <?= e(date('d M Y H:i')) ?> · <?= e((string)count($rows)) ?> machine(s)</p>
    <table>
        <thead>
            <tr>
                <th>Code</th><th>Name</th><th>Department</th><th>Section</th>
                <th>Operator</th><th>Status</th><th>Added</th><th>Last production</th>
                <?php if ($hasPeriod): ?><th class="text-end">Entries</th><th>Last in period</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e((string)$r['machine_code']) ?></td>
                <td><?= e((string)$r['machine_name']) ?></td>
                <td><?= e((string)$r['department']) ?></td>
                <td><?= e((string)$r['section']) ?></td>
                <td><?= e((string)$r['operator']) ?></td>
                <td><?= e((string)$r['status']) ?></td>
                <td><?= e((string)$r['added_date']) ?></td>
                <td><?= e((string)$r['last_production']) ?></td>
                <?php if ($hasPeriod): ?>
                    <td class="text-end"><?= e((string)($r['period_entries'] ?? 0)) ?></td>
                    <td><?= e((string)($r['last_in_period'] ?? '—')) ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
