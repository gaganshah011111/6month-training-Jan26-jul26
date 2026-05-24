<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $pickerRows */
$pickerRows = $pickerRows ?? [];
$readyCount = 0;
$partialCount = 0;
$waitingCount = 0;
foreach ($pickerRows as $pr) {
    $st = strtoupper((string)($pr['stock_status'] ?? ''));
    $can = (int)($pr['dispatchable_qty'] ?? 0) > 0;
    if ($st === 'READY' && $can) {
        $readyCount++;
    } elseif ($st === 'PARTIAL STOCK') {
        $partialCount++;
    } else {
        $waitingCount++;
    }
}
?>
<section class="dsp-order-picker dsp-order-picker--compact" id="dsp-order-picker" aria-label="Select sales order">
    <header class="dsp-order-picker__head">
        <div>
            <h2 class="dsp-order-picker__title">Select sales order</h2>
            <p class="dsp-order-picker__sub">Search, filter, then pick a row — scroll the queue for more lines.</p>
        </div>
        <ul class="dsp-order-picker__stats" aria-hidden="true">
            <li><span class="dsp-order-picker__stat dsp-order-picker__stat--ready"><?= (int)$readyCount ?></span> ready</li>
            <li><span class="dsp-order-picker__stat dsp-order-picker__stat--partial"><?= (int)$partialCount ?></span> partial</li>
            <li><span class="dsp-order-picker__stat dsp-order-picker__stat--wait"><?= (int)$waitingCount ?></span> waiting</li>
        </ul>
    </header>

    <div class="dsp-order-picker__toolbar">
        <label class="dsp-order-picker__search-wrap">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search"
                   class="form-control dsp-order-picker__search"
                   id="dsp-order-search"
                   placeholder="Search SO number, customer, tyre type, pending qty…"
                   autocomplete="off"
                   aria-controls="dsp-order-picker-table">
        </label>
        <div class="dsp-order-picker__filters" role="group" aria-label="Stock status filter">
            <button type="button" class="dsp-filter-chip is-active" data-filter="all">All</button>
            <button type="button" class="dsp-filter-chip" data-filter="READY">Ready to ship</button>
            <button type="button" class="dsp-filter-chip" data-filter="PARTIAL STOCK">Partial stock</button>
            <button type="button" class="dsp-filter-chip" data-filter="WAITING">Waiting stock</button>
        </div>
        <span class="dsp-order-picker__count" id="dsp-order-picker-count" aria-live="polite"></span>
    </div>

    <div class="dsp-order-picker__selected d-none" id="dsp-order-selected-bar">
        <div class="dsp-order-picker__selected-text">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span id="dsp-order-selected-label">—</span>
        </div>
        <div class="dsp-order-picker__selected-actions">
            <button type="button" class="btn btn-sm btn-link dsp-order-picker__expand d-none" id="dsp-order-expand-btn">Expand queue</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="dsp-order-change-btn">Change order</button>
        </div>
    </div>

    <div class="dsp-order-picker__scroll-hint" id="dsp-order-scroll-hint" aria-hidden="true">
        <i class="bi bi-arrows-expand-vertical"></i> Scroll for more orders
    </div>

    <div class="dsp-order-picker__table-wrap dsp-order-picker__scroll" id="dsp-order-picker-body">
        <table class="dsp-table dsp-order-picker-table" id="dsp-order-picker-table">
            <thead>
                <tr>
                    <th>SO number</th>
                    <th>Customer</th>
                    <th>Tyre type</th>
                    <th class="text-end">Ordered</th>
                    <th class="text-end">Pending</th>
                    <th class="text-end">Available</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pickerRows as $row): ?>
                <?php
                $stockSt = (string)($row['stock_status'] ?? 'PRODUCTION REQUIRED');
                $stockLabel = dispatch_queue_stock_label($stockSt);
                $stockClass = match ($stockSt) {
                    'READY' => 'dsp-stock--ready',
                    'PARTIAL STOCK' => 'dsp-stock--partial',
                    default => 'dsp-stock--production',
                };
                $filterKey = match ($stockSt) {
                    'READY' => $canDispatch ? 'READY' : 'WAITING',
                    'PARTIAL STOCK' => 'PARTIAL STOCK',
                    default => 'WAITING',
                };
                $canDispatch = (int)($row['dispatchable_qty'] ?? 0) > 0;
                $searchHay = strtolower(implode(' ', [
                    (string)($row['so_number'] ?? ''),
                    (string)($row['company_name'] ?? ''),
                    (string)($row['tyre_type'] ?? ''),
                    (string)($row['ordered_qty'] ?? $row['qty_ordered'] ?? ''),
                    (string)($row['available_qty'] ?? ''),
                    (string)($row['pending_qty'] ?? ''),
                    $stockLabel,
                ]));
                ?>
                <tr class="dsp-order-picker-row dsp-queue-row <?= $canDispatch ? 'dsp-queue-row--ready' : '' ?>"
                    data-search="<?= e($searchHay) ?>"
                    data-stock-status="<?= e($filterKey) ?>"
                    data-order-id="<?= (int)($row['order_id'] ?? $row['sales_order_id']) ?>"
                    data-item-id="<?= (int)($row['item_id'] ?? $row['sales_order_item_id']) ?>"
                    data-customer-id="<?= (int)($row['customer_id'] ?? 0) ?>"
                    data-tyre="<?= e((string)$row['tyre_type']) ?>"
                    data-qty="<?= (int)($row['dispatchable_qty'] ?? 0) ?>"
                    data-pending-qty="<?= (int)($row['pending_qty'] ?? 0) ?>"
                    data-ready-qty="<?= (int)($row['dispatchable_qty'] ?? 0) ?>"
                    data-customer="<?= e((string)$row['company_name']) ?>"
                    data-so-number="<?= e((string)$row['so_number']) ?>"
                    role="button"
                    tabindex="0"
                    title="<?= $canDispatch ? 'Select to load dispatch form' : 'Waiting for stock — cannot dispatch yet' ?>">
                    <td><span class="dsp-queue-ref"><?= e((string)$row['so_number']) ?></span></td>
                    <td class="dsp-queue-customer"><?= e((string)$row['company_name']) ?></td>
                    <td><?= e((string)$row['tyre_type']) ?></td>
                    <td class="text-end"><?= e(dispatch_format_qty((int)($row['ordered_qty'] ?? $row['qty_ordered'] ?? 0))) ?></td>
                    <td class="text-end"><?= e(dispatch_format_qty((int)($row['pending_qty'] ?? 0))) ?></td>
                    <td class="text-end"><?= e(dispatch_format_qty((int)($row['available_qty'] ?? 0))) ?></td>
                    <td><span class="dsp-stock-pill <?= e($stockClass) ?>"><?= e($stockLabel) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pickerRows === []): ?>
                <tr class="dsp-table-empty-row">
                    <td colspan="7" class="dsp-empty">No pending sales orders in the dispatch queue.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
