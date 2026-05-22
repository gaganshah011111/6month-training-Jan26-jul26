<?php
declare(strict_types=1);
$autoPrint = ($export ?? '') === 'pdf';
$periodLabel = $from . ' to ' . $to;
$tabLabel = match ($tabExport) {
    'history' => 'Transaction history',
    'usage' => 'Stock usage',
    'low' => 'Low stock',
    'inward' => 'Inward history',
    default => 'Stock summary',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Report — <?= e($periodLabel) ?></title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 16px; }
        h1 { font-size: 16px; margin: 0 0 4px; color: #1a2744; }
        .meta { color: #64748b; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #1a2744; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 5px 8px; }
        .text-end { text-align: right; }
    </style>
    <?php if ($autoPrint): ?><script>window.onload = function () { window.print(); };</script><?php endif; ?>
</head>
<body>
    <h1>Inventory Report — <?= e($tabLabel) ?></h1>
    <p class="meta">Period: <?= e($periodLabel) ?> · Generated <?= e(date('d M Y H:i')) ?></p>
    <table>
        <?php if ($tabExport === 'history'): ?>
            <thead><tr><th>Date</th><th>Type</th><th class="text-end">Qty</th><th>Dept</th><th>Operator</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $h): ?>
                <tr>
                    <td><?= e((string)($h['dt'] ?? '')) ?></td>
                    <td><?= e((string)($h['txn_type'] ?? '')) ?></td>
                    <td class="text-end"><?= e((string)($h['qty_signed'] ?? $h['qty'] ?? '')) ?></td>
                    <td><?= e((string)($h['department'] ?? '—')) ?></td>
                    <td><?= e((string)($h['operator_name'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php elseif ($tabExport === 'usage'): ?>
            <thead><tr><th>Date</th><th>Material</th><th class="text-end">Qty</th><th>Department</th></tr></thead>
            <tbody>
            <?php foreach ($report['usage'] as $r): ?>
                <tr>
                    <td><?= e((string)$r['usage_date']) ?></td>
                    <td><?= e((string)$r['material_name']) ?></td>
                    <td class="text-end"><?= e((string)$r['quantity']) ?></td>
                    <td><?= e((string)($r['department'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php else: ?>
            <thead><tr><th>Material</th><th class="text-end">Stock</th><th>Unit</th><th class="text-end">Minimum</th></tr></thead>
            <tbody>
            <?php foreach ($stockRows as $r): ?>
                <tr>
                    <td><?= e((string)$r['material_name']) ?></td>
                    <td class="text-end"><?= e(inv_format_qty((float)$r['current_stock'])) ?></td>
                    <td><?= e((string)$r['unit']) ?></td>
                    <td class="text-end"><?= e(inv_format_qty((float)$r['reorder_level'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php endif; ?>
    </table>
</body>
</html>
