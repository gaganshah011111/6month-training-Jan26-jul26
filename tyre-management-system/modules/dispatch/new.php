<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/production_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['submit_action'] ?? 'save');
    $targetStatus = $action === 'deliver' ? DISPATCH_STATUS_DELIVERED : DISPATCH_STATUS_DISPATCHED;
    try {
        $id = dispatch_save($pdo, $_POST, $targetStatus);
        $codeSt = $pdo->prepare('SELECT dispatch_code FROM dispatch WHERE id = :id');
        $codeSt->execute(['id' => $id]);
        $code = (string)($codeSt->fetchColumn() ?: $id);
        $msg = $targetStatus === DISPATCH_STATUS_DELIVERED
            ? 'Dispatch saved and marked delivered. Finished stock updated.'
            : 'Dispatch saved. Finished stock reduced from inventory.';
        set_flash('success', $msg . ' ID: ' . $code);
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('dispatch/new');
}

$customers = dispatch_list_customers($pdo);
$fgStock = dispatch_fg_stock_by_type($pdo);
$pending = dispatch_list($pdo, '', '', '', DISPATCH_STATUS_PENDING);
$pending = array_slice($pending, 0, 8);
?>

<div class="dsp-page">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">New Dispatch</h1>
            <p class="dsp-page__sub">Ship finished tyres to customers — stock is deducted from inventory automatically.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('dispatch/history')) ?>">History</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="dsp-card">
                <div class="dsp-card__head"><h2 class="dsp-card__title">Dispatch entry</h2></div>
                <div class="dsp-card__body">
                    <form method="post" class="dsp-form vstack gap-2">
                        <?= csrf_input() ?>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Customer name</label>
                                <select class="form-select form-select-sm" name="customer_id" id="dsp-customer-select">
                                    <option value="">— Type or select below —</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['customer_name']) ?><?= ($c['company'] ?? '') !== '' ? ' · ' . e((string)$c['company']) : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control form-control-sm mt-1" name="customer_name" id="dsp-customer-text" placeholder="Or enter customer name" maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tyre type</label>
                                <select class="form-select form-select-sm" name="tyre_type" id="dsp-tyre-type" required>
                                    <option value="">Select tyre type</option>
                                    <?php foreach (TYRE_TYPES as $t): ?>
                                        <option value="<?= e($t) ?>" data-stock="<?= (int)($fgStock[$t] ?? 0) ?>"><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control form-control-sm" name="qty" id="dsp-qty" min="1" required>
                                <div class="form-text" id="dsp-stock-hint">Available: —</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Invoice number</label>
                                <input class="form-control form-control-sm" name="invoice_no" maxlength="80" required placeholder="e.g. INV-2026-0142">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vehicle number</label>
                                <input class="form-control form-control-sm" name="vehicle_no" maxlength="40" placeholder="MH-12-AB-1234">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Driver name</label>
                                <input class="form-control form-control-sm" name="driver_name" maxlength="150">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Transport company</label>
                                <input class="form-control form-control-sm" name="transport_company" maxlength="150">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Dispatch date</label>
                                <input type="date" class="form-control form-control-sm" name="dispatch_date" value="<?= e($today) ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control form-control-sm" name="remarks" rows="2" maxlength="500" placeholder="Delivery notes, gate pass, etc."></textarea>
                            </div>
                        </div>
                        <div class="dsp-form-actions">
                            <button type="submit" name="submit_action" value="save" class="btn btn-primary btn-sm">Save dispatch</button>
                            <button type="submit" name="submit_action" value="deliver" class="btn btn-success btn-sm">Mark delivered</button>
                        </div>
                        <p class="small text-muted mb-0">Save dispatch ships stock (status: Dispatched). Mark delivered completes delivery and updates reports.</p>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="dsp-card mb-3">
                <div class="dsp-card__head"><h2 class="dsp-card__title">Finished goods stock</h2></div>
                <div class="dsp-card__body">
                    <p class="small text-muted mb-2">From production → inventory. Dispatch reduces these balances.</p>
                    <ul class="dsp-stock-list">
                        <?php foreach (TYRE_TYPES as $t): ?>
                            <?php $sq = (int)($fgStock[$t] ?? 0); ?>
                            <li>
                                <span><?= e($t) ?></span>
                                <strong class="<?= $sq < 1 ? 'text-danger' : '' ?>"><?= e(dispatch_format_qty($sq)) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>

            <section class="dsp-card">
                <div class="dsp-card__head"><h2 class="dsp-card__title">Pending orders</h2></div>
                <div class="dsp-card__body p-0">
                    <?php if ($pending === []): ?>
                        <p class="dsp-empty m-0">No pending dispatch orders.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="dsp-table mb-0">
                                <thead><tr><th>Order</th><th>Customer</th><th class="text-end">Qty</th></tr></thead>
                                <tbody>
                                <?php foreach ($pending as $p): ?>
                                    <tr>
                                        <td><?= e((string)($p['order_no'] ?? $p['dispatch_code'])) ?></td>
                                        <td><?= e((string)$p['customer_name']) ?></td>
                                        <td class="text-end"><?= e(dispatch_format_qty((int)$p['qty'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
<script>
(function () {
    var sel = document.getElementById('dsp-tyre-type');
    var hint = document.getElementById('dsp-stock-hint');
    var custSel = document.getElementById('dsp-customer-select');
    var custTxt = document.getElementById('dsp-customer-text');
    function updateStock() {
        var opt = sel.options[sel.selectedIndex];
        var s = opt ? parseInt(opt.getAttribute('data-stock') || '0', 10) : 0;
        hint.textContent = 'Available: ' + (s ? s.toLocaleString() : '0');
        hint.className = 'form-text' + (s < 1 ? ' text-danger' : '');
    }
    sel.addEventListener('change', updateStock);
    updateStock();
    custSel.addEventListener('change', function () {
        if (custSel.value) {
            custTxt.value = custSel.options[custSel.selectedIndex].text.split(' · ')[0];
        }
    });
})();
</script>
