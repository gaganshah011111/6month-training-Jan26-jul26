<?php
declare(strict_types=1);
/** @var array $inv */
/** @var string $company */
/** @var float $pending */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice <?= e($inv['invoice_no']) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; font-size: 12px; color: #1e293b; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; }
        th { background: #f8fafc; text-align: left; }
        .text-end { text-align: right; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div style="display:flex;justify-content:space-between">
        <div><h1><?= e($company) ?></h1><p style="margin:0;color:#64748b">Sales tax invoice</p></div>
        <div class="text-end"><strong style="font-size:16px"><?= e($inv['invoice_no']) ?></strong><br>Date: <?= e($inv['invoice_date']) ?></div>
    </div>
    <p><strong>Customer:</strong> <?= e($inv['company_name']) ?> (<?= e((string)($inv['customer_code'] ?? '')) ?>)</p>
    <table>
        <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($inv['items'] as $it): ?>
            <tr><td><?= e($it['tyre_type']) ?></td><td class="text-end"><?= e((string)$it['qty']) ?></td><td class="text-end"><?= e(number_format((float)$it['rate'], 2)) ?></td><td class="text-end"><?= e(number_format((float)$it['line_total'], 2)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="text-end" style="margin-top:16px"><strong>Grand total: ₹<?= e(number_format((float)$inv['total_amount'], 2)) ?></strong></p>
</body>
</html>
