<?php
declare(strict_types=1);
/** @var string $workflowStep order|stock|dispatch|invoice|payment */
$workflowStep = $workflowStep ?? 'order';
$workflowHelper = $workflowHelper ?? '';
$steps = [
    'order' => ['label' => 'Sales Order', 'icon' => 'bi-cart-check'],
    'stock' => ['label' => 'Stock Check', 'icon' => 'bi-boxes'],
    'dispatch' => ['label' => 'Dispatch', 'icon' => 'bi-truck'],
    'invoice' => ['label' => 'Invoice', 'icon' => 'bi-receipt'],
    'payment' => ['label' => 'Payment', 'icon' => 'bi-cash-stack'],
];
?>
<nav class="so-workflow" aria-label="Sales order workflow">
    <?php
    $keys = array_keys($steps);
    $currentIdx = array_search($workflowStep, $keys, true);
    if ($currentIdx === false) {
        $currentIdx = 0;
    }
    foreach ($keys as $i => $key):
        $meta = $steps[$key];
        $isCurrent = $key === $workflowStep;
        $isPast = $i < $currentIdx;
    ?>
        <?php if ($i > 0): ?><span class="so-workflow__sep" aria-hidden="true"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
        <span class="so-workflow__step <?= $isCurrent ? 'is-current' : '' ?> <?= $isPast ? 'is-done' : '' ?>">
            <i class="bi <?= e($meta['icon']) ?>"></i>
            <span><?= e($meta['label']) ?></span>
        </span>
    <?php endforeach; ?>
</nav>
<?php if ($workflowHelper !== ''): ?>
    <p class="so-workflow-hint"><i class="bi bi-info-circle me-1"></i><?= e($workflowHelper) ?></p>
<?php endif; ?>
