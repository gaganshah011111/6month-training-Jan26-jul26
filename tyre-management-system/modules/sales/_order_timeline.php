<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $workflowTimeline */
$workflowTimeline = $workflowTimeline ?? [];
?>
<nav class="so-workflow-timeline" aria-label="Order workflow">
    <ol class="so-workflow-timeline__list">
        <?php foreach ($workflowTimeline as $step): ?>
            <?php
            $state = (string)($step['state'] ?? 'upcoming');
            $cls = 'so-workflow-timeline__step so-workflow-timeline__step--' . $state;
            ?>
            <li class="<?= e($cls) ?>">
                <span class="so-workflow-timeline__dot" aria-hidden="true"></span>
                <span class="so-workflow-timeline__label"><?= e((string)$step['label']) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
