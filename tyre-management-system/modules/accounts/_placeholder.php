<?php
declare(strict_types=1);
/** @var string $accountsPlaceholderTitle */
/** @var string $accountsPlaceholderHint */
$accountsPlaceholderTitle = $accountsPlaceholderTitle ?? 'Coming soon';
$accountsPlaceholderHint = $accountsPlaceholderHint ?? 'This section will be expanded in a future release.';
?>
<div class="accounts-page">
    <section class="sales-card">
        <div class="sales-card__body text-center py-5">
            <i class="bi bi-tools display-4 text-muted mb-3 d-block"></i>
            <h2 class="h5"><?= e($accountsPlaceholderTitle) ?></h2>
            <p class="text-muted mb-0"><?= e($accountsPlaceholderHint) ?></p>
        </div>
    </section>
</div>
