<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/logistics_service.php';
require_once __DIR__ . '/../../includes/production_service.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$today = date('Y-m-d');

$customers = dispatch_list_customers($pdo);
$logisticsBundle = logistics_dispatch_bundle($pdo);
$fgStock = dispatch_fg_stock_by_type($pdo);
$recentDispatches = array_slice(dispatch_list($pdo, '', '', '', DISPATCH_STATUS_DELIVERED), 0, 8);
$preview = dispatch_form_preview($pdo);
$dash = dispatch_dashboard($pdo);
$customerOptions = array_map(static fn($c) => [
    'value' => (string)(int)$c['id'],
    'label' => (string)$c['customer_name'],
    'sub' => (string)($c['company'] ?? ''),
], $customers);

$tyreOptions = [];
foreach (TYRE_TYPES as $t) {
    $sq = (int)($fgStock[$t] ?? 0);
    $tyreOptions[] = [
        'value' => $t,
        'label' => $t,
        'sub' => dispatch_format_qty($sq) . ' in stock',
        'stock' => $sq,
    ];
}
usort($tyreOptions, static fn($a, $b) => ($b['stock'] <=> $a['stock']) ?: strcmp($a['label'], $b['label']));

$driverOptions = array_map(static fn($d) => [
    'value' => (string)(int)$d['id'],
    'label' => (string)$d['name'],
    'sub' => (string)($d['vehicle_no'] ?? ''),
], $logisticsBundle['drivers']);

$vehicleOptions = array_map(static fn($v) => [
    'value' => (string)(int)$v['id'],
    'label' => (string)$v['number'],
    'sub' => (string)($v['driver_name'] ?? 'No driver'),
], $logisticsBundle['vehicles']);

$logisticsEmpty = $logisticsBundle['drivers'] === [] && $logisticsBundle['vehicles'] === [];
$totalStock = array_sum($fgStock);
$salesOrders = sales_open_orders_for_dispatch($pdo);
$preSalesOrderId = (int)($_GET['sales_order_id'] ?? 0);
?>

<div class="dsp-entry-page dsp-page--entry">
    <header class="dsp-entry-header">
        <div class="dsp-entry-header__text">
            <h1 class="dsp-entry-header__title">CRM Dispatch Entry</h1>
            <p class="dsp-entry-header__sub">Ship against a sales order — updates fulfilment and CRM invoicing.</p>
        </div>
        <nav class="dsp-entry-header__actions">
            <a href="<?= e(route_url('sales/dispatch')) ?>" class="dsp-entry-btn-outline">Dispatch tracking</a>
        </nav>
    </header>

    <?php if ($logisticsEmpty): ?>
        <div class="dsp-entry-alert dsp-entry-alert--warn">
            <i class="bi bi-exclamation-triangle"></i>
            Drivers and vehicles must be configured by the Dispatch department before you can record a shipment.
        </div>
    <?php endif; ?>

    <div id="dsp-form-success" class="dsp-entry-alert dsp-entry-alert--ok d-none" role="status"></div>
    <div id="dsp-form-actions-success" class="d-none mb-0 dsp-pdf-actions"></div>
    <div id="dsp-form-error" class="dsp-entry-alert dsp-entry-alert--danger d-none" role="alert"></div>

    <div class="dsp-entry-layout">
        <div class="dsp-entry-main">
            <form class="dsp-entry-form" id="dsp-dispatch-form"
                  data-stock="<?= e(json_encode($fgStock) ?: '{}') ?>"
                  data-stock-api="index.php?page=api/dispatch-stock"
                  data-save-api="index.php?page=api/dispatch-save"
                  data-preview-api="index.php?page=api/dispatch-preview"
                  data-prefill-api="<?= e(route_url('api/sales-dispatch-prefill')) ?>"
                  data-logistics="<?= e(json_encode($logisticsBundle) ?: '{"drivers":[],"vehicles":[]}') ?>"
                  data-customers="<?= e(json_encode($customerOptions) ?: '[]') ?>"
                  data-tyres="<?= e(json_encode($tyreOptions) ?: '[]') ?>"
                  data-drivers="<?= e(json_encode($driverOptions) ?: '[]') ?>"
                  data-vehicles="<?= e(json_encode($vehicleOptions) ?: '[]') ?>"
                  novalidate>
                <?= csrf_input() ?>
                <input type="hidden" name="sales_order_id" id="dsp-sales-order-id" value="<?= $preSalesOrderId > 0 ? (int)$preSalesOrderId : '' ?>">
                <input type="hidden" name="sales_order_item_id" id="dsp-sales-order-item-id" value="">
                <input type="hidden" name="sales_customer_id" id="dsp-sales-customer-id" value="">
                <div id="dsp-so-link-banner" class="dsp-so-link-banner d-none" role="status"></div>

                <?php if ($salesOrders !== []): ?>
                <section class="dsp-entry-card">
                    <header class="dsp-entry-card__head">
                        <h2 class="dsp-entry-card__title"><i class="bi bi-receipt dsp-entry-card__ico"></i> Sales order</h2>
                        <p class="dsp-entry-card__sub">Updates order dispatched qty and creates CRM invoice</p>
                    </header>
                    <div class="dsp-entry-card__body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="dsp-entry-label">Sales order</label>
                                <select class="form-select form-select-sm" id="dsp-sales-order-select">
                                    <option value="">— No sales order —</option>
                                    <?php foreach ($salesOrders as $so): ?>
                                        <option value="<?= (int)$so['id'] ?>" <?= $preSalesOrderId === (int)$so['id'] ? 'selected' : '' ?>><?= e($so['so_number']) ?> — <?= e($so['company_name']) ?> (<?= e($so['status']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="dsp-entry-label">Order line</label>
                                <select class="form-select form-select-sm" id="dsp-sales-order-line-select" disabled>
                                    <option value="">Select sales order first</option>
                                </select>
                                <p class="dsp-entry-hint small mb-0" id="dsp-sales-line-hint"></p>
                            </div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <section class="dsp-entry-card dsp-entry-card--order">
                    <header class="dsp-entry-card__head">
                        <span class="dsp-entry-card__kicker">Order details</span>
                        <h2 class="dsp-entry-card__title"><i class="bi bi-building dsp-entry-card__ico"></i> Customer &amp; material</h2>
                        <p class="dsp-entry-card__sub">Who is receiving tyres and what product is shipped</p>
                    </header>
                    <div class="dsp-entry-card__body">
                        <div class="dsp-entry-field-group">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="dsp-entry-label dsp-entry-label--key">Customer <span class="dsp-entry-req">*</span></label>
                                    <div class="dsp-combo" id="dsp-combo-customer" data-placeholder="Search or select customer"></div>
                                    <input type="hidden" name="customer_id" id="dsp-customer-id" value="">
                                    <input type="hidden" name="customer_name" id="dsp-customer-name" value="">
                                </div>
                                <div class="col-md-6">
                                    <label class="dsp-entry-label dsp-entry-label--key">Tyre type <span class="dsp-entry-req">*</span></label>
                                    <div class="dsp-combo" id="dsp-combo-tyre" data-placeholder="Search or select tyre type"></div>
                                    <input type="hidden" name="tyre_type" id="dsp-tyre-type" value="">
                                    <p class="dsp-entry-hint" id="dsp-stock-hint">Select tyre type to see stock</p>
                                </div>
                            </div>
                        </div>
                        <div class="dsp-entry-field-group">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="dsp-entry-label dsp-entry-label--key">Quantity <span class="dsp-entry-req">*</span></label>
                                    <input type="number" class="form-control dsp-entry-input" name="qty" id="dsp-qty" min="1" step="1" required autocomplete="off" placeholder="0">
                                    <p class="dsp-entry-hint dsp-entry-hint--qty d-none" id="dsp-qty-error"></p>
                                </div>
                                <div class="col-md-4">
                                    <label class="dsp-entry-label">Dispatch ID <span class="dsp-entry-badge-auto">Auto</span></label>
                                    <input type="text" class="form-control dsp-entry-doc" id="dsp-dispatch-code" readonly
                                           value="<?= e((string)$preview['dispatch_code']) ?>">
                                    <p class="dsp-entry-hint">Auto-generated reference</p>
                                </div>
                                <div class="col-md-4">
                                    <label class="dsp-entry-label">Invoice number <span class="dsp-entry-badge-auto">Auto</span></label>
                                    <input type="text" class="form-control dsp-entry-doc" id="dsp-invoice-preview" readonly
                                           value="<?= e((string)$preview['invoice_no']) ?>">
                                    <p class="dsp-entry-hint">Invoice auto-generated on save</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="dsp-entry-card dsp-entry-card--logistics">
                    <header class="dsp-entry-card__head">
                        <span class="dsp-entry-card__kicker">Logistics</span>
                        <h2 class="dsp-entry-card__title"><i class="bi bi-truck dsp-entry-card__ico"></i> Logistics details</h2>
                        <p class="dsp-entry-card__sub">Driver, vehicle, and transport — linked from Logistics master</p>
                    </header>
                    <div class="dsp-entry-card__body">
                        <div class="dsp-entry-field-group">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="dsp-entry-label dsp-entry-label--key">Driver <span class="dsp-entry-req">*</span></label>
                                    <div class="dsp-combo" id="dsp-combo-driver" data-placeholder="Search or select driver" <?= $logisticsEmpty ? 'data-disabled="1"' : '' ?>></div>
                                    <input type="hidden" name="driver_id" id="dsp-driver-id" value="" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="dsp-entry-label dsp-entry-label--key">Vehicle <span class="dsp-entry-req">*</span></label>
                                    <div class="dsp-combo" id="dsp-combo-vehicle" data-placeholder="Search or select vehicle" <?= $logisticsEmpty ? 'data-disabled="1"' : '' ?>></div>
                                    <input type="hidden" name="vehicle_id" id="dsp-vehicle-id" value="" required>
                                </div>
                            </div>
                        </div>
                        <div class="dsp-entry-field-group">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="dsp-entry-label">Transport company <span class="dsp-entry-badge-auto">Auto</span></label>
                                    <input type="text" class="form-control dsp-entry-input dsp-entry-input--readonly" id="dsp-transport-display" readonly tabindex="-1" placeholder="Auto from driver / vehicle">
                                    <input type="hidden" name="transport_company_id" id="dsp-transport-id" value="">
                                </div>
                                <div class="col-md-6">
                                    <label class="dsp-entry-label dsp-entry-label--key">Dispatch date <span class="dsp-entry-req">*</span></label>
                                    <input type="date" class="form-control dsp-entry-input dsp-entry-field-red" name="dispatch_date" value="<?= e($today) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="dsp-entry-card dsp-entry-card--weight">
                    <header class="dsp-entry-card__head">
                        <span class="dsp-entry-card__kicker">Weight details</span>
                        <h2 class="dsp-entry-card__title"><i class="bi bi-speedometer2 dsp-entry-card__ico"></i> Vehicle weight</h2>
                        <p class="dsp-entry-card__sub">Net = loaded vehicle weight − empty vehicle weight</p>
                    </header>
                    <div class="dsp-entry-card__body">
                        <div class="dsp-entry-field-group">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="dsp-entry-label">Gross weight (kg)</label>
                                    <input type="number" class="form-control dsp-entry-input dsp-weight dsp-weight--gross" name="gross_weight_kg" id="dsp-gross" min="0" step="0.01" placeholder="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="dsp-entry-label">Tare weight (kg)</label>
                                    <input type="number" class="form-control dsp-entry-input dsp-weight dsp-weight--tare" name="tare_weight_kg" id="dsp-tare" min="0" step="0.01" placeholder="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="dsp-entry-label">Net weight (kg) <span class="dsp-entry-badge-auto">Auto</span></label>
                                    <input type="text" class="form-control dsp-entry-input dsp-weight dsp-weight--net" id="dsp-net" readonly value="—">
                                </div>
                            </div>
                        </div>
                        <p class="dsp-entry-hint mb-0 dsp-field-error d-none" id="dsp-weight-error"></p>
                    </div>
                </section>

                <section class="dsp-entry-card dsp-entry-card--notes">
                    <header class="dsp-entry-card__head">
                        <span class="dsp-entry-card__kicker">Notes</span>
                        <h2 class="dsp-entry-card__title"><i class="bi bi-journal-text dsp-entry-card__ico"></i> Additional notes</h2>
                        <p class="dsp-entry-card__sub">Gate pass, delivery instructions, or internal remarks</p>
                    </header>
                    <div class="dsp-entry-card__body">
                        <textarea class="form-control dsp-entry-input" name="remarks" rows="3" maxlength="500" placeholder="Optional remarks…"></textarea>
                    </div>
                </section>

                <section class="dsp-entry-card dsp-entry-card--actions">
                    <header class="dsp-entry-card__head">
                        <span class="dsp-entry-card__kicker">Final step</span>
                        <h2 class="dsp-entry-card__title"><i class="bi bi-check2-circle dsp-entry-card__ico"></i> Final actions</h2>
                    </header>
                    <div class="dsp-entry-card__body dsp-entry-actions">
                        <p class="dsp-entry-actions__hint">Stock and delivery update automatically.</p>
                        <button type="submit" class="btn dsp-btn-save" id="dsp-btn-save" disabled>Create dispatch</button>
                    </div>
                </section>
            </form>
        </div>

        <aside class="dsp-entry-aside">
            <section class="dsp-entry-widget dsp-entry-widget--stock">
                <header class="dsp-entry-widget__head">
                    <i class="bi bi-boxes dsp-entry-widget__ico"></i>
                    <div>
                        <h3 class="dsp-entry-widget__title">Live stock</h3>
                        <p class="dsp-entry-widget__sub">Finished goods by tyre type</p>
                    </div>
                </header>
                <div class="dsp-entry-table-wrap" id="dsp-stock-panel">
                    <table class="dsp-entry-table">
                        <thead>
                            <tr><th>Tyre type</th><th class="text-end">Qty</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach (TYRE_TYPES as $t): ?>
                            <?php
                            $sq = (int)($fgStock[$t] ?? 0);
                            $pill = $sq < 1 ? 'out' : ($sq < 50 ? 'low' : 'ok');
                            ?>
                            <tr data-tyre="<?= e($t) ?>">
                                <td><?= e($t) ?></td>
                                <td class="text-end">
                                    <span class="dsp-entry-tag dsp-entry-tag--<?= e($pill) ?>"><?= e(dispatch_format_qty($sq)) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dsp-entry-widget dsp-entry-widget--recent">
                <header class="dsp-entry-widget__head">
                    <i class="bi bi-clock-history dsp-entry-widget__ico"></i>
                    <div>
                        <h3 class="dsp-entry-widget__title">Recent dispatch</h3>
                        <p class="dsp-entry-widget__sub">Latest delivered orders</p>
                    </div>
                </header>
                <?php if ($recentDispatches === []): ?>
                    <p class="dsp-entry-empty">No dispatches yet</p>
                <?php else: ?>
                    <div class="dsp-entry-table-wrap">
                        <table class="dsp-entry-table dsp-entry-table--recent">
                            <thead>
                                <tr><th>Ref</th><th>Customer</th><th class="text-end">Qty</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentDispatches as $p): ?>
                                <tr>
                                    <td>
                                        <span class="dsp-entry-ref"><?= e((string)($p['dispatch_code'] ?? $p['order_no'])) ?></span>
                                        <span class="dsp-entry-tag dsp-entry-tag--ok">Delivered</span>
                                    </td>
                                    <td>
                                        <span class="d-block"><?= e((string)$p['customer_name']) ?></span>
                                        <span class="dsp-entry-meta"><?= e((string)($p['tyre_type'] ?? '—')) ?></span>
                                    </td>
                                    <td class="text-end"><?= e(dispatch_format_qty((int)$p['qty'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dsp-entry-widget dsp-entry-widget--summary">
                <header class="dsp-entry-widget__head">
                    <i class="bi bi-bar-chart dsp-entry-widget__ico"></i>
                    <div>
                        <h3 class="dsp-entry-widget__title">Quick summary</h3>
                        <p class="dsp-entry-widget__sub">Today&apos;s dispatch overview</p>
                    </div>
                </header>
                <ul class="dsp-entry-summary">
                    <li><span class="dsp-entry-summary__label">Dispatches today</span><span class="dsp-entry-summary__val"><?= e((string)$dash['dispatches_today']) ?></span></li>
                    <li><span class="dsp-entry-summary__label">Tyres dispatched today</span><span class="dsp-entry-summary__val"><?= e(dispatch_format_qty((int)$dash['today_qty'])) ?></span></li>
                    <li><span class="dsp-entry-summary__label">Vehicles used today</span><span class="dsp-entry-summary__val"><?= e((string)$dash['vehicles_today']) ?></span></li>
                    <li><span class="dsp-entry-summary__label">Total FG stock</span><span class="dsp-entry-summary__val"><?= e(dispatch_format_qty($totalStock)) ?></span></li>
                </ul>
            </section>
        </aside>
    </div>
</div>
<script>
(function () {
  var orderSel = document.getElementById('dsp-sales-order-select');
  var lineSel = document.getElementById('dsp-sales-order-line-select');
  var hidOrder = document.getElementById('dsp-sales-order-id');
  var hidLine = document.getElementById('dsp-sales-order-item-id');
  var hint = document.getElementById('dsp-sales-line-hint');
  var tyreHidden = document.getElementById('dsp-tyre-type');
  var qtyInput = document.getElementById('dsp-qty');
  if (!orderSel || !lineSel) return;
  function loadLines(orderId) {
    lineSel.innerHTML = '<option value="">Loading…</option>';
    lineSel.disabled = true;
    if (!orderId) {
      lineSel.innerHTML = '<option value="">—</option>';
      hidOrder.value = '';
      hidLine.value = '';
      return;
    }
    fetch('index.php?page=api/sales-order-lines&order_id=' + encodeURIComponent(orderId))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        lineSel.innerHTML = '<option value="">Select line</option>';
        (d.lines || []).forEach(function (ln) {
          var opt = document.createElement('option');
          opt.value = ln.id;
          opt.textContent = ln.tyre_type + ' — remaining ' + ln.qty_remaining;
          opt.dataset.tyre = ln.tyre_type;
          opt.dataset.max = ln.qty_remaining;
          lineSel.appendChild(opt);
        });
        lineSel.disabled = false;
        hidOrder.value = orderId;
      });
  }
  orderSel.addEventListener('change', function () { loadLines(orderSel.value); });
  lineSel.addEventListener('change', function () {
    var opt = lineSel.options[lineSel.selectedIndex];
    hidLine.value = opt && opt.value ? opt.value : '';
    if (opt && opt.dataset.tyre) {
      if (hint) hint.textContent = 'Max dispatch qty: ' + opt.dataset.max;
      if (tyreHidden) tyreHidden.value = opt.dataset.tyre;
      if (qtyInput && opt.dataset.max) qtyInput.max = opt.dataset.max;
    }
  });
  if (orderSel.value) loadLines(orderSel.value);
})();
</script>
