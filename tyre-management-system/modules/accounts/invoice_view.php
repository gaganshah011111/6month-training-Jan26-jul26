<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$inv = sales_get_invoice($pdo, $id);
if (!$inv) {
    echo '<div class="alert alert-warning">Invoice not found.</div>';
    return;
}

$total = (float)$inv['total_amount'];
$paid = (float)$inv['amount_paid'];
$remaining = max(0.0, $total - $paid);
$status = 'Pending';
if ($remaining <= 0.01) {
    $status = 'Paid';
} elseif ($paid > 0.01) {
    $status = 'Partial';
} elseif ((string)($inv['due_date'] ?? '') !== '' && (string)$inv['due_date'] < date('Y-m-d')) {
    $status = 'Overdue';
}
$statusClass = $status === 'Paid'
    ? 'crm-track crm-track--paid'
    : ($status === 'Overdue'
        ? 'crm-track crm-track--overdue'
        : ($status === 'Partial' ? 'crm-track crm-track--partial-pay' : 'crm-track crm-track--unpaid'));

$st = $pdo->prepare(
    "SELECT id, payment_date, amount, payment_mode, reference_no, remarks
     FROM sales_payments
     WHERE invoice_id = :iid
     ORDER BY payment_date DESC, id DESC"
);
$st->execute(['iid' => $id]);
$payments = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
$paidPct = $total > 0 ? min(100.0, max(0.0, ($paid / $total) * 100.0)) : 0.0;
$paidPctLabel = number_format($paidPct, 0);
$invoiceDate = (string)($inv['invoice_date'] ?? '');
$dueDate = (string)($inv['due_date'] ?? '');
$invoiceDateFmt = $invoiceDate !== '' ? date('d M Y', strtotime($invoiceDate)) : '—';
$dueDateFmt = $dueDate !== '' ? date('d M Y', strtotime($dueDate)) : '—';
$lastPayment = $payments[0] ?? null;
$lastPaymentDate = $lastPayment ? (string)($lastPayment['payment_date'] ?? '') : '';
$lastPaymentMode = $lastPayment ? (string)($lastPayment['payment_mode'] ?? '') : '';
$lastPaymentDateFmt = $lastPaymentDate !== '' ? date('d M Y', strtotime($lastPaymentDate)) : '—';
?>

<div class="accounts-page">
    <section class="sales-card mb-3">
        <div class="sales-card__body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/receivables')) ?>">
                    <i class="bi bi-arrow-left-short"></i> Back
                </a>
                <h1 class="h4 mb-0">Invoice #<?= e((string)$inv['invoice_no']) ?></h1>
                <span class="<?= e($statusClass) ?>"><?= e(strtoupper($status)) ?></span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-primary" href="<?= e(route_url('accounts/invoice-print', ['id' => $id])) ?>" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i>Download PDF</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="accShareInvoiceBtn" data-link="<?= e(route_url('accounts/invoice-view', ['id' => $id])) ?>"><i class="bi bi-share me-1"></i>Share</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/invoice-print', ['id' => $id, 'print' => 1])) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
                <?php if ($lastPayment): ?>
                    <a class="btn btn-sm btn-outline-success" href="<?= e(route_url('accounts/payment-receipt', ['id' => (int)$lastPayment['id']])) ?>" target="_blank" rel="noopener"><i class="bi bi-receipt me-1"></i>Latest Receipt</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="sales-kpis mb-3" style="grid-template-columns:repeat(4,minmax(150px,1fr));">
        <article class="sales-kpi">
            <span class="sales-kpi__label">Invoice Total</span>
            <div class="sales-kpi__value"><i class="bi bi-receipt me-1 text-primary"></i><?= e(sales_format_money($total)) ?></div>
        </article>
        <article class="sales-kpi sales-kpi--ok">
            <span class="sales-kpi__label">Paid Amount</span>
            <div class="sales-kpi__value text-success"><i class="bi bi-check-circle me-1"></i><?= e(sales_format_money($paid)) ?></div>
        </article>
        <article class="sales-kpi <?= $remaining > 0.01 ? 'sales-kpi--danger' : 'sales-kpi--primary' ?>">
            <span class="sales-kpi__label">Pending</span>
            <div class="sales-kpi__value <?= $remaining > 0.01 ? 'text-danger' : 'text-success' ?>"><i class="bi bi-hourglass-split me-1"></i><?= e(sales_format_money($remaining)) ?></div>
        </article>
        <article class="sales-kpi">
            <span class="sales-kpi__label">Due Date</span>
            <div class="sales-kpi__value"><i class="bi bi-calendar-event me-1 text-secondary"></i><?= e($dueDateFmt) ?></div>
        </article>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <section class="sales-card h-100">
                <div class="sales-card__head">
                    <h2 class="sales-card__title mb-0">Invoice Information</h2>
                </div>
                <div class="sales-card__body">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="small text-muted text-uppercase">Customer</div><div class="fw-semibold"><?= e((string)$inv['company_name']) ?></div></div>
                        <div class="col-md-6"><div class="small text-muted text-uppercase">Customer code</div><div><?= e((string)($inv['customer_code'] ?? '—')) ?></div></div>
                        <div class="col-md-6"><div class="small text-muted text-uppercase">Sales order</div><div><?= e((string)($inv['so_number'] ?? '—')) ?></div></div>
                        <div class="col-md-6"><div class="small text-muted text-uppercase">Invoice date</div><div><?= e($invoiceDateFmt) ?></div></div>
                        <div class="col-md-6"><div class="small text-muted text-uppercase">Contact person</div><div><?= e((string)($inv['contact_person'] ?: '—')) ?></div></div>
                        <div class="col-md-6"><div class="small text-muted text-uppercase">Phone</div><div><?= e((string)($inv['phone'] ?: '—')) ?></div></div>
                        <div class="col-md-12"><div class="small text-muted text-uppercase">Status</div><div><span class="<?= e($statusClass) ?>"><?= e(strtoupper($status === 'Partial' ? 'PARTIAL PAID' : $status)) ?></span></div></div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="sales-card h-100">
                <div class="sales-card__head">
                    <h2 class="sales-card__title mb-0">Actions</h2>
                </div>
                <div class="sales-card__body d-grid gap-2">
                    <a class="btn btn-sm btn-primary" href="<?= e(route_url('accounts/receivables')) ?>"><i class="bi bi-cash-coin me-1"></i>Record Payment</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/invoice-print', ['id' => $id])) ?>" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i>Download Invoice</a>
                    <?php if ($lastPayment): ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= e(route_url('accounts/payment-receipt', ['id' => (int)$lastPayment['id']])) ?>" target="_blank" rel="noopener"><i class="bi bi-receipt me-1"></i>View Receipt</a>
                    <?php endif; ?>
                    <hr class="my-2">
                    <div class="small text-muted">Last payment date</div>
                    <div class="fw-semibold"><?= e($lastPaymentDateFmt) ?></div>
                    <div class="small text-muted mt-1">Last payment mode</div>
                    <div class="fw-semibold"><?= e($lastPaymentMode !== '' ? $lastPaymentMode : '—') ?></div>
                    <div class="small text-muted mt-1">Remaining balance</div>
                    <div class="fw-semibold text-danger"><?= e(sales_format_money($remaining)) ?></div>
                </div>
            </section>
        </div>
    </div>

    <section class="sales-card mb-3">
        <div class="sales-card__body">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="small fw-semibold">Paid <?= e($paidPctLabel) ?>% of invoice</div>
                <div class="small text-muted"><?= e(sales_format_money($paid)) ?> / <?= e(sales_format_money($total)) ?></div>
            </div>
            <div class="progress" style="height:10px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= e((string)$paidPct) ?>%" aria-valuenow="<?= e((string)$paidPct) ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
    </section>

    <section class="sales-card">
        <div class="sales-card__head d-flex justify-content-between align-items-center">
            <h2 class="sales-card__title mb-0">Payment Timeline</h2>
            <?= erp_export_toolbar('acc-invoice-pay-history', 'invoice-payment-history') ?>
        </div>
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-invoice-pay-history">
                <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>Remarks</th><th>Receipt PDF</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= e(date('d M Y', strtotime((string)$p['payment_date']))) ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money((float)$p['amount'])) ?></td>
                        <td><span class="badge text-bg-light border"><?= e((string)$p['payment_mode']) ?></span></td>
                        <td><?= e((string)($p['reference_no'] ?: '—')) ?></td>
                        <td><?= e((string)($p['remarks'] ?: '—')) ?></td>
                        <td><a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/payment-receipt', ['id' => (int)$p['id']])) ?>" target="_blank" rel="noopener">PDF</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($payments === []): ?><tr><td colspan="6" class="sales-empty">No payments recorded for this invoice.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
<script>
(function () {
    const shareBtn = document.getElementById('accShareInvoiceBtn');
    if (!shareBtn) return;
    shareBtn.addEventListener('click', async function () {
        const link = shareBtn.getAttribute('data-link') || '';
        if (!link) return;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(window.location.origin + '/' + link.replace(/^\//, ''));
                shareBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
                setTimeout(() => {
                    shareBtn.innerHTML = '<i class="bi bi-share me-1"></i>Share';
                }, 1200);
                return;
            }
            window.prompt('Copy invoice link', window.location.origin + '/' + link.replace(/^\//, ''));
        } catch (e) {
            window.prompt('Copy invoice link', window.location.origin + '/' + link.replace(/^\//, ''));
        }
    });
})();
</script>
