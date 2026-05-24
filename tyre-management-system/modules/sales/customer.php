<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$c = sales_get_customer($pdo, $id);
if (!$c) {
    echo '<p class="text-muted p-3">Customer not found.</p>';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_text'])) {
    verify_csrf();
    try {
        sales_add_note($pdo, $id, (string)$_POST['note_text']);
        set_flash('success', 'Note added.');
    } catch (Throwable $e) {
        sales_log_exception($e, 'customer_note');
        set_flash('danger', 'Unable to save note. Please try again.');
    }
    header('Location: ' . route_url('sales/customer', ['id' => $id]));
    exit;
}

$loadError = false;
$pending = 0.0;
$orders = [];
$invoices = [];
$notes = [];
$payments = [];
$dispatches = [];

try {
    $pending = sales_customer_pending($pdo, $id);
    $orders = sales_list_orders($pdo, ['customer_id' => $id]);
    $invoices = sales_list_invoices($pdo, ['customer_id' => $id]);
    $notes = sales_customer_notes($pdo, $id);
    $paySt = $pdo->prepare('SELECT p.*, i.invoice_no FROM sales_payments p INNER JOIN sales_invoices i ON i.id = p.invoice_id WHERE p.customer_id = :id ORDER BY p.payment_date DESC');
    $paySt->execute(['id' => $id]);
    $payments = $paySt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $dspSt = $pdo->prepare(
        'SELECT d.dispatch_code, d.dispatch_date, d.tyre_type, d.qty, d.invoice_no
         FROM dispatch d
         WHERE d.customer_name LIKE :n
            OR d.customer_id = (SELECT dispatch_customer_id FROM sales_customers WHERE id = :id LIMIT 1)
         ORDER BY d.dispatch_date DESC LIMIT 20'
    );
    $dspSt->execute(['n' => '%' . $c['company_name'] . '%', 'id' => $id]);
    $dispatches = $dspSt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_customer');
    $loadError = true;
}
?>

<div class="sales-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title"><?= e($c['company_name']) ?></h1>
            <p class="prod-page__sub"><?= e($c['customer_code']) ?> · <?= e($c['customer_type']) ?> · Pending <?= e(sales_format_money($pending)) ?></p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('sales/customers', ['edit' => $id])) ?>">Edit</a>
            <button type="button" class="btn btn-link p-0" onclick="window.print()">Print profile</button>
        </nav>
    </header>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($loadError): ?><?= sales_error_alert('Unable to load full customer profile.') ?><?php endif; ?>

    <section class="sales-card">
        <div class="sales-card__head"><h2 class="sales-card__title">Customer details</h2></div>
        <div class="sales-card__body">
            <dl class="sales-profile-grid">
                <div><dt>Contact</dt><dd><?= e((string)($c['contact_person'] ?? '—')) ?></dd></div>
                <div><dt>Phone</dt><dd><?= e((string)($c['phone'] ?? '—')) ?></dd></div>
                <div><dt>Email</dt><dd><?= e((string)($c['email'] ?? '—')) ?></dd></div>
                <div><dt>GST</dt><dd><?= e((string)($c['gst_number'] ?? '—')) ?></dd></div>
                <div><dt>Credit limit</dt><dd><?= e(sales_format_money((float)$c['credit_limit'])) ?></dd></div>
                <div><dt>Payment terms</dt><dd><?= e((string)($c['payment_terms'] ?? '—')) ?></dd></div>
                <div class="col-12"><dt>Billing</dt><dd><?= e((string)($c['billing_address'] ?? '—')) ?>, <?= e((string)($c['city'] ?? '')) ?> <?= e((string)($c['state'] ?? '')) ?></dd></div>
            </dl>
        </div>
    </section>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="sales-card"><div class="sales-card__head"><h2 class="sales-card__title">Order history</h2></div>
                <div class="sales-table-wrap sales-table-wrap--short"><table class="table table-sm mb-0"><thead><tr><th>SO</th><th>Date</th><th>Status</th></tr></thead><tbody>
                <?php foreach (array_slice($orders, 0, 10) as $o): ?><tr><td><a href="<?= e(route_url('sales/order', ['id' => (int)$o['id']])) ?>"><?= e($o['so_number']) ?></a></td><td><?= e($o['order_date']) ?></td><td><?= e($o['status']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div></section>
            <section class="sales-card"><div class="sales-card__head"><h2 class="sales-card__title">Dispatch history</h2></div>
                <div class="sales-table-wrap sales-table-wrap--short"><table class="table table-sm mb-0"><thead><tr><th>Code</th><th>Tyre</th><th class="text-end">Qty</th></tr></thead><tbody>
                <?php foreach ($dispatches as $d): ?><tr><td><?= e($d['dispatch_code']) ?></td><td><?= e($d['tyre_type']) ?></td><td class="text-end"><?= e((string)$d['qty']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div></section>
        </div>
        <div class="col-lg-6">
            <section class="sales-card"><div class="sales-card__head"><h2 class="sales-card__title">Invoices &amp; payments</h2></div>
                <div class="sales-table-wrap sales-table-wrap--short"><table class="table table-sm mb-0"><thead><tr><th>Invoice</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
                <?php foreach (array_slice($invoices, 0, 10) as $inv): ?><tr><td><a href="<?= e(route_url('sales/invoice', ['id' => (int)$inv['id']])) ?>"><?= e($inv['invoice_no']) ?></a></td><td class="text-end"><?= e(sales_format_money((float)$inv['total_amount'])) ?></td><td><?= e($inv['payment_status']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div></section>
            <section class="sales-card"><div class="sales-card__head"><h2 class="sales-card__title">CRM notes</h2></div>
                <div class="sales-card__body">
                    <form method="post" class="mb-3"><?= csrf_input() ?><textarea class="form-control form-control-sm mb-2" name="note_text" rows="2" required placeholder="Follow-up note…"></textarea><button class="btn btn-primary btn-sm">Add note</button></form>
                    <ul class="list-unstyled small mb-0"><?php foreach ($notes as $n): ?><li class="border-bottom py-2"><strong><?= e((string)$n['created_by']) ?></strong> <span class="text-muted"><?= e(substr((string)$n['created_at'], 0, 16)) ?></span><br><?= e($n['note_text']) ?></li><?php endforeach; ?></ul>
                </div></section>
        </div>
    </div>
</div>
