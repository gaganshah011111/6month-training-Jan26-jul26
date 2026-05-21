<?php
/**
 * Workflow execution card (orders board).
 * @var array<string, mixed> $order
 */
$order = $order ?? [];
$stages = $order['stages'] ?? [];
$workflowPct = (int)($order['workflow_pct'] ?? production_order_workflow_pct($order, $stages));
$qtyPct = (int)($order['completion_pct'] ?? production_order_quantity_pct($order, $stages));
$produced = (int)($order['display_produced'] ?? 0);
$rejected = (int)($order['display_rejected'] ?? 0);
$target = (int)($order['target_qty'] ?? 0);
$ob = production_order_status_badge((string)($order['status'] ?? ''));
$detailUrl = route_url('production/order') . '&id=' . (int)$order['id'];
$started = (bool)($order['execution_started'] ?? production_has_execution_started($stages));
$prioClass = match ((string)($order['priority'] ?? '')) {
    'Urgent' => 'pw-card__prio--urgent',
    'High' => 'pw-card__prio--high',
    default => '',
};
?>
<article class="pw-card <?= $prioClass ?>">
    <div class="pw-card__top">
        <div>
            <a class="pw-card__code" href="<?= e($detailUrl) ?>"><?= e($order['order_code']) ?></a>
            <div class="pw-card__tyre"><?= e($order['tyre_type']) ?></div>
            <?php if (!$started): ?>
                <span class="pw-ticket-badge">Job ticket — not started</span>
            <?php endif; ?>
        </div>
        <span class="<?= e($ob['class']) ?>"><?= e($ob['label']) ?></span>
    </div>

    <div class="pw-card__metrics">
        <div><span class="pw-card__mk">Target</span><strong><?= e((string)$target) ?></strong></div>
        <div><span class="pw-card__mk">Produced</span><strong><?= e((string)$produced) ?></strong></div>
        <div><span class="pw-card__mk">Rejected</span><strong class="text-danger"><?= e((string)$rejected) ?></strong></div>
    </div>

    <div class="pw-stepper pw-stepper--vertical">
        <?php foreach (production_stage_names() as $i => $stageName): ?>
            <?php
            $stage = production_find_stage_by_name($stages, $stageName) ?? ['stage_name' => $stageName, 'status' => 'Pending', 'rejected_qty' => 0];
            $meta = production_stage_visual_meta($stage, $order, $stages);
            ?>
            <?php if ($i > 0): ?><span class="pw-stepper__connector" aria-hidden="true"></span><?php endif; ?>
            <div class="pw-stepper__node <?= e($meta['pill_class']) ?> <?= $meta['is_current'] ? 'is-current' : '' ?>">
                <i class="bi <?= e($meta['icon']) ?> pw-stepper__icon"></i>
                <span class="pw-stepper__name"><?= e($stageName) ?></span>
                <span class="pw-stepper__state"><?= e($meta['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="pw-progress">
        <div class="pw-progress__label">
            <span>Workflow <?= e((string)$workflowPct) ?>%</span>
            <span>Output <?= e((string)$qtyPct) ?>%</span>
        </div>
        <div class="pw-progress__track">
            <div class="pw-progress__fill pw-progress__fill--workflow" style="width:<?= $workflowPct ?>%"></div>
        </div>
        <div class="pw-progress__label mt-1">
            <span><?= e((string)$produced) ?> / <?= e((string)$target) ?> units</span>
        </div>
        <div class="pw-progress__track">
            <div class="pw-progress__fill" style="width:<?= $qtyPct ?>%"></div>
        </div>
    </div>

    <div class="pw-card__foot">
        <span class="text-muted small">Stage: <strong><?= e($order['current_stage'] ?? 'Mixing') ?></strong></span>
        <a class="btn btn-sm btn-primary" href="<?= e($detailUrl) ?>">Execute</a>
    </div>
</article>
