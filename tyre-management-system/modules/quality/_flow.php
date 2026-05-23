<?php
declare(strict_types=1);

/** @param 'production'|'qc'|'inventory'|'dispatch' $active */
function qc_render_flow_bar(string $active): void
{
    $steps = [
        'production' => ['label' => 'Production', 'url' => route_url('production/curing'), 'icon' => 'bi-building-gear'],
        'qc' => ['label' => 'QC Inspection', 'url' => route_url('quality/pending'), 'icon' => 'bi-shield-check'],
        'inventory' => ['label' => 'Inventory', 'url' => route_url('inventory/dashboard'), 'icon' => 'bi-boxes'],
        'dispatch' => ['label' => 'Dispatch', 'url' => route_url('dispatch/new'), 'icon' => 'bi-truck'],
    ];
    ?>
    <nav class="qc-flow" aria-label="ERP workflow">
        <?php foreach ($steps as $key => $step): ?>
            <?php $isActive = $key === $active; ?>
            <a href="<?= e($step['url']) ?>" class="qc-flow__step<?= $isActive ? ' qc-flow__step--active' : '' ?>">
                <span class="qc-flow__icon"><i class="bi <?= e($step['icon']) ?>"></i></span>
                <span class="qc-flow__label"><?= e($step['label']) ?></span>
            </a>
            <?php if ($key !== 'dispatch'): ?>
                <span class="qc-flow__arrow" aria-hidden="true">→</span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
}
