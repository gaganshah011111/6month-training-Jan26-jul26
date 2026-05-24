<?php
declare(strict_types=1);
/** @var string $flowActive dispatch|dashboard */
$flowActive = $flowActive ?? 'dispatch';
?>
<nav class="dsp-flow" aria-label="Dispatch workflow">
    <span class="dsp-flow__step">Sales Order</span>
    <i class="bi bi-chevron-right dsp-flow__sep" aria-hidden="true"></i>
    <span class="dsp-flow__step">Stock Check</span>
    <i class="bi bi-chevron-right dsp-flow__sep" aria-hidden="true"></i>
    <span class="dsp-flow__step <?= $flowActive === 'dispatch' ? 'is-active' : '' ?>">Dispatch</span>
    <i class="bi bi-chevron-right dsp-flow__sep" aria-hidden="true"></i>
    <span class="dsp-flow__step">Invoice</span>
</nav>
