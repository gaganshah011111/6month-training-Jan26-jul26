<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/logistics_service.php';
require_once __DIR__ . '/../../includes/production_service.php';
require_once __DIR__ . '/../../includes/sales_service.php';
if (!has_role(['Dispatch Manager', 'Super Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$today = date('Y-m-d');

$logisticsBundle = logistics_dispatch_bundle($pdo);
$preview = dispatch_form_preview($pdo);
$queueLines = sales_dispatch_queue_list($pdo);
$prefillOrderId = (int)($_GET['order_id'] ?? 0);
$prefillItemId = (int)($_GET['item_id'] ?? 0);
$prefillApi = route_url('api/sales-dispatch-prefill');

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
?>

<div class="dsp-entry-page dsp-entry-page--picker">
    <header class="dsp-entry-header dsp-entry-header--compact">
        <div class="dsp-entry-header__text">
            <h1 class="dsp-entry-header__title">New Dispatch</h1>
            <p class="dsp-entry-header__sub">Search and select a sales order, then assign logistics and create the shipment.</p>
        </div>
        <nav class="dsp-entry-header__actions">
            <a href="<?= e(route_url('dispatch/dashboard')) ?>" class="dsp-entry-btn-outline">Dashboard</a>
            <a href="<?= e(route_url('dispatch/history')) ?>" class="dsp-entry-btn-outline">History</a>
        </nav>
    </header>

    <?php $flowActive = 'dispatch';
    require __DIR__ . '/_dispatch_flow.php'; ?>

    <?php if ($logisticsEmpty): ?>
        <div class="dsp-entry-alert dsp-entry-alert--warn">
            <i class="bi bi-exclamation-triangle"></i>
            Configure <a href="<?= e(route_url('dispatch/logistics')) ?>">drivers &amp; vehicles</a> before dispatching.
        </div>
    <?php endif; ?>

    <div id="dsp-form-success" class="dsp-entry-alert dsp-entry-alert--ok d-none" role="status"></div>
    <div id="dsp-form-actions-success" class="d-none mb-0 dsp-pdf-actions"></div>
    <div id="dsp-form-error" class="dsp-entry-alert dsp-entry-alert--danger d-none" role="alert"></div>

    <?php
    $pickerRows = $queueLines;
    require __DIR__ . '/_order_picker.php';
    ?>

    <div class="dsp-dispatch-workspace" id="dsp-dispatch-workspace">
    <form class="dsp-entry-form dsp-entry-form--compact dsp-entry-form--full" id="dsp-dispatch-form"
          data-save-api="index.php?page=api/dispatch-save"
          data-preview-api="index.php?page=api/dispatch-preview"
          data-prefill-api="<?= e($prefillApi) ?>"
          data-prefill-order="<?= $prefillOrderId ?>"
          data-prefill-item="<?= $prefillItemId ?>"
          data-logistics="<?= e(json_encode($logisticsBundle) ?: '{"drivers":[],"vehicles":[]}') ?>"
          data-drivers="<?= e(json_encode($driverOptions) ?: '[]') ?>"
          data-vehicles="<?= e(json_encode($vehicleOptions) ?: '[]') ?>"
          novalidate>
        <?= csrf_input() ?>
        <input type="hidden" name="sales_order_id" id="dsp-sales-order-id" value="">
        <input type="hidden" name="sales_order_item_id" id="dsp-sales-order-item-id" value="">
        <input type="hidden" name="sales_customer_id" id="dsp-sales-customer-id" value="">

        <div id="dsp-so-link-banner" class="dsp-so-link-banner d-none" role="status"></div>

        <section class="dsp-entry-card dsp-entry-card--compact dsp-entry-card--order dsp-dispatch-panel d-none" id="dsp-order-details-section">
            <header class="dsp-entry-card__head dsp-entry-card__head--compact dsp-dispatch-panel__head">
                <h2 class="dsp-entry-card__title dsp-entry-card__title--compact">Shipment details</h2>
                <p class="dsp-entry-card__sub mb-0">Loaded from sales order — quantities are read-only except dispatch qty.</p>
            </header>
            <div class="dsp-entry-card__body dsp-entry-card__body--compact">
                <div id="dsp-order-locked">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="dsp-entry-label">Sales order</label>
                            <input type="text" class="form-control dsp-entry-input dsp-entry-input--sm dsp-entry-input--readonly" id="dsp-so-number" readonly tabindex="-1">
                        </div>
                        <div class="col-md-8">
                            <label class="dsp-entry-label">Customer</label>
                            <input type="text" class="form-control dsp-entry-input dsp-entry-input--sm dsp-entry-input--readonly" id="dsp-customer-display" readonly tabindex="-1">
                            <input type="hidden" name="customer_id" id="dsp-customer-id" value="">
                            <input type="hidden" name="customer_name" id="dsp-customer-name" value="">
                            <input type="hidden" name="tyre_type" id="dsp-tyre-type" value="">
                        </div>
                        <div class="col-md-6">
                            <label class="dsp-entry-label">Tyre type</label>
                            <input type="text" class="form-control dsp-entry-input dsp-entry-input--sm dsp-entry-input--readonly" id="dsp-tyre-display" readonly tabindex="-1">
                        </div>
                        <div class="col-md-2">
                            <label class="dsp-entry-label">Ordered</label>
                            <input type="text" class="form-control dsp-entry-input dsp-entry-input--sm dsp-entry-input--readonly text-end" id="dsp-ordered-qty" readonly tabindex="-1">
                        </div>
                        <div class="col-md-2">
                            <label class="dsp-entry-label">Pending</label>
                            <input type="text" class="form-control dsp-entry-input dsp-entry-input--sm dsp-entry-input--readonly text-end" id="dsp-pending-qty" readonly tabindex="-1">
                        </div>
                        <div class="col-md-2">
                            <label class="dsp-entry-label">Available</label>
                            <input type="text" class="form-control dsp-entry-input dsp-entry-input--sm dsp-entry-input--readonly text-end" id="dsp-available-qty" readonly tabindex="-1">
                        </div>
                        <div class="col-md-4">
                            <label class="dsp-entry-label dsp-entry-label--key">Dispatch qty <span class="dsp-entry-req">*</span></label>
                            <input type="number" class="form-control dsp-entry-input dsp-entry-input--sm" name="qty" id="dsp-qty" min="1" step="1" autocomplete="off" placeholder="0" disabled>
                            <p class="dsp-entry-hint dsp-entry-hint--qty mb-0 d-none" id="dsp-qty-error"></p>
                            <p class="dsp-entry-hint mb-0" id="dsp-stock-hint"></p>
                        </div>
                        <div class="col-md-4">
                            <label class="dsp-entry-label">Dispatch ID</label>
                            <input type="text" class="form-control dsp-entry-doc dsp-entry-input--sm" id="dsp-dispatch-code" readonly value="<?= e((string)$preview['dispatch_code']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="dsp-entry-label">Invoice number</label>
                            <input type="text" class="form-control dsp-entry-doc dsp-entry-input--sm" id="dsp-invoice-preview" readonly value="<?= e((string)$preview['invoice_no']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div id="dsp-shipment-fields" class="dsp-shipment-fields is-locked">
            <section class="dsp-entry-card dsp-entry-card--compact dsp-entry-card--logistics dsp-dispatch-panel">
                <header class="dsp-entry-card__head dsp-entry-card__head--compact dsp-dispatch-panel__head">
                    <h2 class="dsp-entry-card__title dsp-entry-card__title--compact">Logistics</h2>
                </header>
                <div class="dsp-entry-card__body dsp-entry-card__body--compact">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="dsp-entry-label dsp-entry-label--key">Driver <span class="dsp-entry-req">*</span></label>
                            <div class="dsp-combo" id="dsp-combo-driver" data-placeholder="Select driver" <?= $logisticsEmpty ? 'data-disabled="1"' : '' ?>></div>
                            <input type="hidden" name="driver_id" id="dsp-driver-id" value="" required>
                        </div>
                        <div class="col-md-6">
                            <label class="dsp-entry-label dsp-entry-label--key">Vehicle <span class="dsp-entry-req">*</span></label>
                            <div class="dsp-combo" id="dsp-combo-vehicle" data-placeholder="Select vehicle" <?= $logisticsEmpty ? 'data-disabled="1"' : '' ?>></div>
                            <input type="hidden" name="vehicle_id" id="dsp-vehicle-id" value="" required>
                        </div>
                        <div class="col-md-6">
                            <label class="dsp-entry-label">Transport company</label>
                            <input type="text" class="form-control dsp-entry-input dsp-entry-input--sm dsp-entry-input--readonly" id="dsp-transport-display" readonly tabindex="-1" placeholder="From driver / vehicle">
                            <input type="hidden" name="transport_company_id" id="dsp-transport-id" value="">
                        </div>
                        <div class="col-md-6">
                            <label class="dsp-entry-label dsp-entry-label--key">Dispatch date <span class="dsp-entry-req">*</span></label>
                            <input type="date" class="form-control dsp-entry-input dsp-entry-input--sm" name="dispatch_date" value="<?= e($today) ?>" required>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dsp-entry-card dsp-entry-card--compact dsp-entry-card--weight dsp-dispatch-panel">
                <header class="dsp-entry-card__head dsp-entry-card__head--compact dsp-dispatch-panel__head">
                    <h2 class="dsp-entry-card__title dsp-entry-card__title--compact">Vehicle weight</h2>
                </header>
                <div class="dsp-entry-card__body dsp-entry-card__body--compact">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="dsp-entry-label">Gross (kg)</label>
                            <input type="number" class="form-control dsp-entry-input dsp-entry-input--sm dsp-weight dsp-weight--gross" name="gross_weight_kg" id="dsp-gross" min="0" step="0.01" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label class="dsp-entry-label">Tare (kg)</label>
                            <input type="number" class="form-control dsp-entry-input dsp-entry-input--sm dsp-weight dsp-weight--tare" name="tare_weight_kg" id="dsp-tare" min="0" step="0.01" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label class="dsp-entry-label">Net (kg)</label>
                            <input type="text" class="form-control dsp-entry-input dsp-entry-input--sm dsp-weight dsp-weight--net" id="dsp-net" readonly value="—">
                        </div>
                    </div>
                    <p class="dsp-entry-hint mb-0 dsp-field-error d-none" id="dsp-weight-error"></p>
                </div>
            </section>

            <section class="dsp-entry-card dsp-entry-card--compact dsp-entry-card--notes dsp-dispatch-panel">
                <header class="dsp-entry-card__head dsp-entry-card__head--compact dsp-dispatch-panel__head">
                    <h2 class="dsp-entry-card__title dsp-entry-card__title--compact">Remarks</h2>
                </header>
                <div class="dsp-entry-card__body dsp-entry-card__body--compact">
                    <textarea class="form-control dsp-entry-input dsp-entry-input--sm" name="remarks" rows="2" maxlength="500" placeholder="Gate pass, delivery instructions…"></textarea>
                </div>
            </section>

            <footer class="dsp-dispatch-footer">
                <p id="dsp-submit-hint" class="dsp-dispatch-footer__hint">
                    <i class="bi bi-arrow-up-circle me-1" aria-hidden="true"></i>
                    Select CRM sales order before dispatch.
                </p>
                <button type="submit" class="btn dsp-btn-save dsp-btn-save--dispatch" id="dsp-btn-save" disabled aria-describedby="dsp-submit-hint">Create Dispatch</button>
            </footer>
        </div>
    </form>
    </div>
</div>
<script src="assets/js/dispatch-order-picker.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/dispatch-order-picker.js')) ?>"></script>
<script src="assets/js/dispatch-form.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/dispatch-form.js')) ?>"></script>
