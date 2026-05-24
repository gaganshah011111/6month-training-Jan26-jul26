<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $crmQueueRows */
/** @var bool $showActions */
/** @var bool $compact */
$crmQueueRows = $crmQueueRows ?? [];
$showActions = $showActions ?? true;
$compact = $compact ?? false;
$tableId = $tableId ?? 'dsp-queue-table';
?>
<div class="dsp-queue-scroll <?= $compact ? 'dsp-queue-scroll--compact' : 'dsp-queue-scroll--dashboard' ?>">
    <table class="dsp-table dsp-queue-table" id="<?= e($tableId) ?>">
        <thead>
            <tr>
                <th>SO Number</th>
                <th>Customer</th>
                <th>Tyre Type</th>
                <?php if (!$compact): ?>
                    <th class="text-end">Ordered Qty</th>
                    <th class="text-end">Available Qty</th>
                <?php endif; ?>
                <th class="text-end">Pending Qty</th>
                <th>Stock Status</th>
                <?php if ($showActions): ?><th class="text-end">Action</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($crmQueueRows as $row): ?>
            <?php
            $stockSt = (string)($row['stock_status'] ?? 'PRODUCTION REQUIRED');
            $stockLabel = dispatch_queue_stock_label($stockSt);
            $stockClass = match ($stockSt) {
                'READY' => 'dsp-stock--ready',
                'PARTIAL STOCK' => 'dsp-stock--partial',
                default => 'dsp-stock--production',
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
            <tr class="dsp-queue-row <?= $canDispatch ? 'dsp-queue-row--ready' : '' ?>"
                data-search="<?= e($searchHay) ?>"
                role="button"
                tabindex="0"
                data-order-id="<?= (int)($row['order_id'] ?? $row['sales_order_id']) ?>"
                data-item-id="<?= (int)($row['item_id'] ?? $row['sales_order_item_id']) ?>"
                data-customer-id="<?= (int)($row['customer_id'] ?? 0) ?>"
                data-tyre="<?= e((string)$row['tyre_type']) ?>"
                data-qty="<?= (int)($row['dispatchable_qty'] ?? 0) ?>"
                data-pending-qty="<?= (int)($row['pending_qty'] ?? 0) ?>"
                data-ready-qty="<?= (int)($row['dispatchable_qty'] ?? 0) ?>"
                data-customer="<?= e((string)$row['company_name']) ?>"
                title="<?= $canDispatch ? 'Load into dispatch form' : 'Waiting for stock' ?>">
                <td><span class="dsp-queue-ref"><?= e((string)$row['so_number']) ?></span></td>
                <td class="dsp-queue-customer"><?= e((string)$row['company_name']) ?></td>
                <td><?= e((string)$row['tyre_type']) ?></td>
                <?php if (!$compact): ?>
                    <td class="text-end"><?= e((string)($row['ordered_qty'] ?? $row['qty_ordered'] ?? '')) ?></td>
                    <td class="text-end"><?= e((string)($row['available_qty'] ?? '0')) ?></td>
                <?php endif; ?>
                <td class="text-end"><?= e((string)($row['pending_qty'] ?? '0')) ?></td>
                <td><span class="dsp-stock-pill <?= e($stockClass) ?>"><?= e($stockLabel) ?></span></td>
                <?php if ($showActions): ?>
                <td class="text-end">
                    <?php if ($canDispatch): ?>
                        <a class="btn btn-sm btn-primary"
                           href="<?= e(route_url('dispatch/new', ['order_id' => (int)($row['order_id'] ?? $row['sales_order_id']), 'item_id' => (int)($row['item_id'] ?? $row['sales_order_item_id'])])) ?>"
                           onclick="event.stopPropagation();">Ship</a>
                    <?php else: ?>
                        <span class="erp-badge erp-badge--waiting">Waiting</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if ($crmQueueRows === []): ?>
            <?php
            $colspan = 5 + ($compact ? 0 : 2) + ($showActions ? 1 : 0);
            ?>
            <tr class="dsp-table-empty-row"><td colspan="<?= $colspan ?>" class="dsp-empty">No pending deliveries in queue.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
