<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

if (!is_logged_in() || !has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    echo 'Dispatch not found';
    exit;
}

$pdo = Database::connection();
$row = dispatch_get_by_id($pdo, $id);
if (!$row) {
    http_response_code(404);
    echo 'Dispatch not found';
    exit;
}

$autoPrint = isset($_GET['print']);
$companyName = dispatch_company_name($pdo);
$generatedAt = date('d M Y, H:i');
$gross = isset($row['gross_weight_kg']) ? (float)$row['gross_weight_kg'] : null;
$tare = isset($row['tare_weight_kg']) ? (float)$row['tare_weight_kg'] : null;
$net = isset($row['net_weight_kg']) ? (float)$row['net_weight_kg'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dispatch Slip — <?= e((string)($row['dispatch_code'] ?? '')) ?></title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            background: #f1f5f9;
        }
        .slip {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
        }
        .slip__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 24px;
            border-bottom: 3px solid #1a2744;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        }
        .slip__logo {
            width: 56px;
            height: 56px;
            background: #1a2744;
            color: #fff;
            font-weight: 800;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            letter-spacing: -1px;
        }
        .slip__company { font-size: 15px; font-weight: 700; color: #1a2744; margin: 0 0 4px; }
        .slip__tagline { font-size: 10px; color: #64748b; margin: 0; }
        .slip__title {
            text-align: right;
            font-size: 18px;
            font-weight: 800;
            color: #1a2744;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin: 0;
        }
        .slip__subtitle { text-align: right; font-size: 10px; color: #64748b; margin: 4px 0 0; }
        .slip__body { padding: 20px 24px; }
        .slip__section { margin-bottom: 18px; }
        .slip__section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        .slip__grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
        }
        .slip__label { color: #64748b; font-size: 10px; }
        .slip__value { font-weight: 600; color: #0f172a; }
        .slip__material {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .slip__material th {
            background: #1a2744;
            color: #f8fafc;
            text-align: left;
            padding: 8px 10px;
            font-size: 10px;
            text-transform: uppercase;
        }
        .slip__material td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
        }
        .slip__material .text-end { text-align: right; }
        .slip__footer {
            padding: 16px 24px 24px;
            border-top: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .slip__sign {
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            margin-top: 40px;
            font-size: 10px;
            color: #64748b;
        }
        .slip__meta { font-size: 9px; color: #94a3b8; text-align: right; }
        .no-print {
            text-align: center;
            padding: 12px;
            background: #1a2744;
        }
        .no-print button, .no-print a {
            margin: 0 6px;
            padding: 8px 16px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print { background: #fff; color: #1a2744; font-weight: 600; }
        .btn-back { background: transparent; color: #e2e8f0; border: 1px solid #64748b !important; }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .slip { box-shadow: none; border: none; max-width: none; }
        }
    </style>
    <?php if ($autoPrint): ?>
    <script>window.onload = function () { window.print(); };</script>
    <?php endif; ?>
</head>
<body>
    <div class="no-print">
        <button type="button" class="btn-print" onclick="window.print()">Print / Save as PDF</button>
        <a class="btn-back" href="<?= e(route_url('dispatch/history')) ?>">Back to history</a>
    </div>

    <article class="slip">
        <header class="slip__head">
            <div style="display:flex;gap:14px;align-items:flex-start;">
                <div class="slip__logo">R</div>
                <div>
                    <h1 class="slip__company"><?= e($companyName) ?></h1>
                    <p class="slip__tagline">Finished goods dispatch &amp; logistics</p>
                </div>
            </div>
            <div>
                <h2 class="slip__title">Dispatch Slip</h2>
                <p class="slip__subtitle">Official shipment document</p>
            </div>
        </header>

        <div class="slip__body">
            <section class="slip__section">
                <div class="slip__section-title">Dispatch details</div>
                <div class="slip__grid">
                    <div><span class="slip__label">Dispatch ID</span><br><span class="slip__value"><?= e((string)($row['dispatch_code'] ?? '—')) ?></span></div>
                    <div><span class="slip__label">Invoice number</span><br><span class="slip__value"><?= e((string)($row['invoice_no'] ?? '—')) ?></span></div>
                    <div><span class="slip__label">Dispatch date</span><br><span class="slip__value"><?= e((string)($row['dispatch_date'] ?? '—')) ?></span></div>
                    <div><span class="slip__label">Status</span><br><span class="slip__value"><?= e((string)($row['status'] ?? '—')) ?></span></div>
                    <div style="grid-column:1/-1"><span class="slip__label">Customer</span><br><span class="slip__value"><?= e((string)($row['customer_name'] ?? '—')) ?></span></div>
                </div>
            </section>

            <section class="slip__section">
                <div class="slip__section-title">Transport details</div>
                <div class="slip__grid">
                    <div><span class="slip__label">Driver</span><br><span class="slip__value"><?= e((string)($row['driver_name'] ?? '—')) ?></span></div>
                    <div><span class="slip__label">Vehicle number</span><br><span class="slip__value"><?= e((string)($row['vehicle_no'] ?? '—')) ?></span></div>
                    <div style="grid-column:1/-1"><span class="slip__label">Transport company</span><br><span class="slip__value"><?= e((string)($row['transport_company'] ?? $row['transport_master_name'] ?? '—')) ?></span></div>
                </div>
            </section>

            <section class="slip__section">
                <div class="slip__section-title">Material details</div>
                <table class="slip__material">
                    <thead>
                        <tr>
                            <th>Tyre type</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Gross (kg)</th>
                            <th class="text-end">Tare (kg)</th>
                            <th class="text-end">Net (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= e((string)($row['tyre_type'] ?? '—')) ?></td>
                            <td class="text-end"><?= e(dispatch_format_qty((int)($row['qty'] ?? 0))) ?></td>
                            <td class="text-end"><?= $gross !== null ? e(number_format($gross, 2)) : '—' ?></td>
                            <td class="text-end"><?= $tare !== null ? e(number_format($tare, 2)) : '—' ?></td>
                            <td class="text-end"><strong><?= $net !== null ? e(number_format($net, 2)) : '—' ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <?php if (!empty($row['remarks'])): ?>
            <section class="slip__section">
                <div class="slip__section-title">Remarks</div>
                <p class="slip__value" style="font-weight:400;"><?= e((string)$row['remarks']) ?></p>
            </section>
            <?php endif; ?>
        </div>

        <footer class="slip__footer">
            <div>
                <div class="slip__sign">Authorized signature</div>
            </div>
            <div class="slip__meta">
                Generated: <?= e($generatedAt) ?><br>
                Order ref: <?= e((string)($row['order_no'] ?? '')) ?>
            </div>
        </footer>
    </article>
</body>
</html>
