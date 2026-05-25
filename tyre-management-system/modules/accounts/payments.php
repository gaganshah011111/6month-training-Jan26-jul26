<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';

$pdo = Database::connection();
$recent = $pdo->query(
    'SELECT p.*, i.invoice_no, c.company_name FROM sales_payments p
     INNER JOIN sales_invoices i ON i.id = p.invoice_id
     INNER JOIN sales_customers c ON c.id = p.customer_id
     ORDER BY p.payment_date DESC, p.id DESC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Payment history</h1>
            <p class="prod-page__sub">All recorded receipts linked to invoices.</p>
        </div>
        <nav class="prod-page__links"><a href="<?= e(route_url('sales/payments')) ?>">Record payment</a></nav>
    </header>

    <section class="sales-card">
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Customer</th><th>Invoice</th><th>Mode</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $p): ?>
                    <tr>
                        <td><?= e($p['payment_date']) ?></td>
                        <td><?= e($p['company_name']) ?></td>
                        <td><a href="<?= e(route_url('sales/invoice', ['id' => (int)$p['invoice_id']])) ?>"><?= e($p['invoice_no']) ?></a></td>
                        <td><?= e($p['payment_mode']) ?></td>
                        <td><?= e((string)($p['reference_no'] ?? '—')) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$p['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
