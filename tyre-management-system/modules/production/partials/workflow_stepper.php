<?php
/**
 * Horizontal workflow stepper.
 * @var array<string, mixed> $order
 * @var list<array<string, mixed>> $stages
 */
$order = $order ?? [];
$stages = $stages ?? [];
?>
<div class="pw-stepper pw-stepper--horizontal">
    <?php foreach (production_stage_names() as $i => $stageName): ?>
        <?php
        $stage = production_find_stage_by_name($stages, $stageName) ?? ['stage_name' => $stageName, 'status' => 'Pending', 'rejected_qty' => 0];
        $meta = production_stage_visual_meta($stage, $order, $stages);
        ?>
        <?php if ($i > 0): ?><span class="pw-stepper__arrow-h" aria-hidden="true"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
        <div class="pw-stepper__node pw-stepper__node--h <?= e($meta['pill_class']) ?> <?= $meta['is_current'] ? 'is-current' : '' ?>">
            <span class="pw-stepper__bubble"><i class="bi <?= e($meta['icon']) ?>"></i></span>
            <span class="pw-stepper__name"><?= e($stageName) ?></span>
            <span class="pw-stepper__state"><?= e($meta['label']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
