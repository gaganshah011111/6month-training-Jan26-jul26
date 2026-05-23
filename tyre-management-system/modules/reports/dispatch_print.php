<?php
declare(strict_types=1);
$autoPrint = ($export ?? '') === 'pdf';
$periodLabel = ($from ?? '') . ' to ' . ($to ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dispatch Report — <?= e($periodLabel) ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 16px; }
        h1 { font-size: 16px; margin: 0 0 4px; color: #1a2744; }
        .meta { color: #64748b; margin-bottom: 14px; }
        .kpis { display: flex; gap: 24px; margin-bottom: 12px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1a2744; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 5px 8px; }
        .text-end { text-align: right; }
    </style>
    <?php if ($autoPrint): ?><script>window.onload = function () { window.print(); };</script><?php endif; ?>
</head>
<body>
    <h1>Dispatch Report</h1>
    <p class="meta">Period: <?= e($periodLabel) ?> · Generated <?= e(date('d M Y H:i')) ?></p>
    <div class="kpis">
        <span><strong>Orders:</strong> <?= e((string)($sum['total_dispatch'] ?? 0)) ?></span>
        <span><strong>Total qty:</strong> <?= e(dispatch_format_qty((int)($sum['total_qty'] ?? 0))) ?></span>
    </div>
    <table>
        <thead>
            <tr><th>Dispatch ID</th><th>Date</th><th>Customer</th><th>Tyre</th><th class="text-end">Qty</th><th>Invoice</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e((string)($r['dispatch_code'] ?? '')) ?></td>
                <td><?= e((string)$r['dispatch_date']) ?></td>
                <td><?= e((string)$r['customer_name']) ?></td>
                <td><?= e((string)($r['tyre_type'] ?? '')) ?></td>
                <td class="text-end"><?= e(dispatch_format_qty((int)$r['qty'])) ?></td>
                <td><?= e((string)$r['invoice_no']) ?></td>
                <td><?= e((string)$r['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
