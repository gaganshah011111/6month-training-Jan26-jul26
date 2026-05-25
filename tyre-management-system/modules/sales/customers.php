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
$showForm = $edit !== null || isset($_GET['add']);
$filterQs = array_filter(['q' => $q, 'status' => $status, 'type' => $type]);
?>

<div class="sales-page crm-layout crm-customers-page">
    <?= crm_page_header(
        'Customers',
        'Manage dealers, distributors, and buyers.',
        '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#crm-customer-form-panel" aria-expanded="' . ($showForm ? 'true' : 'false') . '">'
        . '<i class="bi bi-plus-lg me-1"></i>' . ($edit ? 'Edit customer' : 'Add customer') . '</button>'
    ) ?>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load customers.') ?><?php endif; ?>

    <?php if ($q !== '' || $status !== ''): ?>
        <div class="crm-active-filters mb-2">
            <?php if ($q !== ''): ?><span class="badge bg-light text-dark border">Search: <?= e($q) ?></span><?php endif; ?>
            <?php if ($status !== ''): ?><span class="badge bg-light text-dark border">Status: <?= e($status) ?></span><?php endif; ?>
            <a class="small ms-1" href="<?= e(route_url('sales/customers')) ?>">Clear all</a>
        </div>
    <?php endif; ?>

    <div class="collapse<?= $showForm ? ' show' : '' ?> mb-3" id="crm-customer-form-panel">
        <section class="crm-section">
            <div class="crm-section__head">
                <h2 class="crm-section__title"><?= $edit ? 'Edit customer' : 'New customer' ?></h2>
            </div>
            <div class="crm-section__body crm-section__body--padded">
                <form method="post" class="crm-customer-form">
                    <?= csrf_input() ?>
                    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company name <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" name="company_name" required value="<?= e((string)($edit['company_name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Customer type</label>
                            <select class="form-select form-select-sm" name="customer_type">
                                <?php foreach (SALES_CUSTOMER_TYPES as $t): ?>
                                    <option value="<?= e($t) ?>" <?= ($edit['customer_type'] ?? 'Dealer') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <?php foreach (SALES_CUSTOMER_STATUSES as $s): ?>
                                    <option value="<?= e($s) ?>" <?= ($edit['status'] ?? 'Active') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact person</label>
                            <input class="form-control form-control-sm" name="contact_person" value="<?= e((string)($edit['contact_person'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input class="form-control form-control-sm" name="phone" value="<?= e((string)($edit['phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input class="form-control form-control-sm" name="email" type="email" value="<?= e((string)($edit['email'] ?? '')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">GST</label>
                            <input class="form-control form-control-sm" name="gst_number" value="<?= e((string)($edit['gst_number'] ?? '')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PAN</label>
                            <input class="form-control form-control-sm" name="pan_number" value="<?= e((string)($edit['pan_number'] ?? '')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Credit limit</label>
                            <input type="number" class="form-control form-control-sm" name="credit_limit" min="0" step="0.01" value="<?= e((string)($edit['credit_limit'] ?? '0')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Payment terms</label>
                            <input class="form-control form-control-sm" name="payment_terms" value="<?= e((string)($edit['payment_terms'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Billing address</label>
                            <textarea class="form-control form-control-sm" name="billing_address" rows="2"><?= e((string)($edit['billing_address'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Shipping address</label>
                            <textarea class="form-control form-control-sm" name="shipping_address" rows="2"><?= e((string)($edit['shipping_address'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input class="form-control form-control-sm" name="city" value="<?= e((string)($edit['city'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input class="form-control form-control-sm" name="state" value="<?= e((string)($edit['state'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pincode</label>
                            <input class="form-control form-control-sm" name="pincode" value="<?= e((string)($edit['pincode'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <input class="form-control form-control-sm" name="remarks" value="<?= e((string)($edit['remarks'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary btn-sm" type="submit"><?= $edit ? 'Update customer' : 'Save customer' ?></button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/customers')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <form method="get" class="crm-filter-inline crm-customers-search-bar" id="crm-customers-filter" action="<?= e(route_url('sales/customers')) ?>">
        <input type="hidden" name="page" value="sales/customers">
        <div class="crm-filter-inline__field crm-customers-search-wrap" style="flex:2 1 220px">
            <label for="crm-customers-search">Search customers</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" name="q" id="crm-customers-search"
                       value="<?= e($q) ?>" placeholder="Name, code, phone, GST, email…" autocomplete="off">
            </div>
            <span class="crm-search-hint">Type to filter — updates automatically</span>
        </div>
        <div class="crm-filter-inline__field">
            <label>Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All statuses</option>
                <?php foreach (SALES_CUSTOMER_STATUSES as $s): ?>
                    <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="crm-filter-inline__field">
            <label>Type</label>
            <select class="form-select form-select-sm" name="type">
                <option value="">All types</option>
                <?php foreach (SALES_CUSTOMER_TYPES as $t): ?>
                    <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="crm-filter-inline__actions">
            <button class="btn btn-primary btn-sm" type="submit">Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/customers')) ?>">Reset</a>
        </div>
    </form>

    <section class="crm-section">
        <div class="crm-section__head">
            <h2 class="crm-section__title">Customer directory</h2>
            <span class="small text-muted" id="crm-customers-count"><?= count($rows) ?> customers</span>
        </div>
        <div class="crm-section__body">
            <?= crm_table_open('crm-customers-table') ?>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Company</th>
                    <th>Type</th>
                    <th>Phone</th>
                    <th class="text-end">Pending</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $sb = sales_status_badge((string)$r['status']);
                $cid = (int)$r['id'];
                $hay = strtolower(implode(' ', [
                    $r['customer_code'],
                    $r['company_name'],
                    $r['customer_type'] ?? '',
                    $r['contact_person'] ?? '',
                    $r['phone'] ?? '',
                    $r['gst_number'] ?? '',
                    $r['email'] ?? '',
                    $r['city'] ?? '',
                ]));
                $actions = [
                    ['label' => 'View', 'url' => route_url('sales/customer', ['id' => $cid]), 'icon' => 'bi-eye'],
                    ['label' => 'Edit', 'url' => route_url('sales/customers', ['edit' => $cid]), 'icon' => 'bi-pencil'],
                    ['label' => 'New order', 'url' => route_url('sales/order', ['customer_id' => $cid]), 'icon' => 'bi-cart-plus'],
                ];
                ?>
                <tr class="crm-customer-row" data-search="<?= e($hay) ?>">
                    <td><code class="crm-code"><?= e($r['customer_code']) ?></code></td>
                    <td>
                        <a href="<?= e(route_url('sales/customer', ['id' => $cid])) ?>" class="crm-customer-name"><?= e($r['company_name']) ?></a>
                        <?php if (!empty($r['contact_person'])): ?>
                            <span class="crm-customer-sub d-block"><?= e($r['contact_person']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string)($r['customer_type'] ?? '—')) ?></td>
                    <td><?= e((string)($r['phone'] ?? '—')) ?></td>
                    <td class="text-end fw-semibold"><?= e(sales_format_money((float)$r['pending_amount'])) ?></td>
                    <td><span class="<?= e($sb['class']) ?>"><?= e($sb['label']) ?></span></td>
                    <td class="text-end"><?= crm_action_icons($actions) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr class="crm-customers-empty"><td colspan="7" class="sales-empty text-center py-4">No customers found. Try a different search or add a new customer.</td></tr>
            <?php endif; ?>
            </tbody>
            <?= crm_table_close() ?>
        </div>
    </section>
</div>
<script src="assets/js/sales-customers.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/sales-customers.js')) ?>"></script>
