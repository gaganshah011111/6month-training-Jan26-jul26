<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0) ?: null;
    try {
        sales_save_customer($pdo, $_POST, $id);
        set_flash('success', $id ? 'Customer updated.' : 'Customer added.');
    } catch (Throwable $e) {
        sales_log_exception($e, 'save_customer');
        set_flash('danger', 'Unable to save customer. Please check the form and try again.');
    }
    redirect('sales/customers');
}

$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$type = (string)($_GET['type'] ?? '');
$loadError = false;
$rows = [];
try {
    $rows = sales_list_customers($pdo, ['q' => $q, 'status' => $status, 'type' => $type]);
} catch (Throwable $e) {
    sales_log_exception($e, 'customers_list');
    $loadError = true;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Code', 'Company', 'Contact', 'Phone', 'GST', 'Credit Limit', 'Pending', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['customer_code'], $r['company_name'], $r['contact_person'], $r['phone'], $r['gst_number'], $r['credit_limit'], $r['pending_amount'], $r['status']]);
    }
    fclose($out);
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId > 0 ? sales_get_customer($pdo, $editId) : null;
$filterQs = array_filter(['q' => $q, 'status' => $status, 'type' => $type]);
?>

<div class="sales-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Customer Master</h1>
            <p class="prod-page__sub">Dealers, distributors, and industrial buyers.</p>
        </div>
    </header>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($loadError): ?><?= sales_error_alert('Unable to load customers.') ?><?php endif; ?>

    <form method="get" class="sales-filter-bar">
        <input type="hidden" name="page" value="sales/customers">
        <div class="sales-filter-bar__grid">
            <div class="sales-filter-bar__field"><label>Search</label><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Name, code, phone, GST"></div>
            <div class="sales-filter-bar__field"><label>Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach (SALES_CUSTOMER_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
            <div class="sales-filter-bar__field"><label>Type</label><select class="form-select form-select-sm" name="type"><option value="">All</option><?php foreach (SALES_CUSTOMER_TYPES as $t): ?><option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="sales-filter-bar__actions">
            <button class="btn btn-primary btn-sm" type="submit">Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/customers')) ?>">Reset</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/customers', array_merge($filterQs, ['export' => 'csv']))) ?>">Excel</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title"><?= $edit ? 'Edit customer' : 'Add customer' ?></h2></div>
                <div class="sales-card__body">
                    <form method="post" class="vstack gap-2 small">
                        <?= csrf_input() ?>
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
                        <div><label class="form-label">Company name</label><input class="form-control form-control-sm" name="company_name" required value="<?= e((string)($edit['company_name'] ?? '')) ?>"></div>
                        <div><label class="form-label">Customer type</label><select class="form-select form-select-sm" name="customer_type"><?php foreach (SALES_CUSTOMER_TYPES as $t): ?><option <?= ($edit['customer_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Contact person</label><input class="form-control form-control-sm" name="contact_person" value="<?= e((string)($edit['contact_person'] ?? '')) ?>"></div>
                        <div class="row g-2"><div class="col-6"><label class="form-label">Phone</label><input class="form-control form-control-sm" name="phone" value="<?= e((string)($edit['phone'] ?? '')) ?>"></div><div class="col-6"><label class="form-label">Email</label><input class="form-control form-control-sm" name="email" type="email" value="<?= e((string)($edit['email'] ?? '')) ?>"></div></div>
                        <div class="row g-2"><div class="col-6"><label class="form-label">GST</label><input class="form-control form-control-sm" name="gst_number" value="<?= e((string)($edit['gst_number'] ?? '')) ?>"></div><div class="col-6"><label class="form-label">PAN</label><input class="form-control form-control-sm" name="pan_number" value="<?= e((string)($edit['pan_number'] ?? '')) ?>"></div></div>
                        <div><label class="form-label">Billing address</label><textarea class="form-control form-control-sm" name="billing_address" rows="2"><?= e((string)($edit['billing_address'] ?? '')) ?></textarea></div>
                        <div><label class="form-label">Shipping address</label><textarea class="form-control form-control-sm" name="shipping_address" rows="2"><?= e((string)($edit['shipping_address'] ?? '')) ?></textarea></div>
                        <div class="row g-2"><div class="col-4"><label class="form-label">City</label><input class="form-control form-control-sm" name="city" value="<?= e((string)($edit['city'] ?? '')) ?>"></div><div class="col-4"><label class="form-label">State</label><input class="form-control form-control-sm" name="state" value="<?= e((string)($edit['state'] ?? '')) ?>"></div><div class="col-4"><label class="form-label">Pincode</label><input class="form-control form-control-sm" name="pincode" value="<?= e((string)($edit['pincode'] ?? '')) ?>"></div></div>
                        <div class="row g-2"><div class="col-6"><label class="form-label">Credit limit</label><input type="number" class="form-control form-control-sm" name="credit_limit" min="0" step="0.01" value="<?= e((string)($edit['credit_limit'] ?? '0')) ?>"></div><div class="col-6"><label class="form-label">Payment terms</label><input class="form-control form-control-sm" name="payment_terms" value="<?= e((string)($edit['payment_terms'] ?? '')) ?>"></div></div>
                        <div><label class="form-label">Status</label><select class="form-select form-select-sm" name="status"><?php foreach (SALES_CUSTOMER_STATUSES as $s): ?><option <?= ($edit['status'] ?? 'Active') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Remarks</label><input class="form-control form-control-sm" name="remarks" value="<?= e((string)($edit['remarks'] ?? '')) ?>"></div>
                        <button class="btn btn-primary btn-sm w-100" type="submit"><?= $edit ? 'Update' : 'Add' ?> customer</button>
                        <?php if ($edit): ?><a class="btn btn-link btn-sm" href="<?= e(route_url('sales/customers')) ?>">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Customers</h2><span class="small text-muted"><?= count($rows) ?> row(s)</span></div>
                <div class="sales-table-wrap">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Code</th><th>Company</th><th>Contact</th><th>Phone</th><th>GST</th><th class="text-end">Credit</th><th class="text-end">Pending</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php $sb = sales_status_badge((string)$r['status']); ?>
                            <tr>
                                <td><strong><?= e($r['customer_code']) ?></strong></td>
                                <td><a href="<?= e(route_url('sales/customer', ['id' => (int)$r['id']])) ?>"><?= e($r['company_name']) ?></a></td>
                                <td><?= e((string)($r['contact_person'] ?? '—')) ?></td>
                                <td><?= e((string)($r['phone'] ?? '—')) ?></td>
                                <td><?= e((string)($r['gst_number'] ?? '—')) ?></td>
                                <td class="text-end"><?= e(sales_format_money((float)$r['credit_limit'])) ?></td>
                                <td class="text-end"><?= e(sales_format_money((float)$r['pending_amount'])) ?></td>
                                <td><span class="<?= e($sb['class']) ?>"><?= e($sb['label']) ?></span></td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('sales/customers', ['edit' => (int)$r['id']])) ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No customers found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
