<?php
declare(strict_types=1);
/**
 * Recent production entries table with client-side search and scroll.
 *
 * @var string $recentTitle
 * @var list<array<string, mixed>> $recentRows
 * @var list<array{key: string, label: string, class?: string}> $recentColumns
 */
$recentTitle = $recentTitle ?? 'Recent entries';
$recentRows = $recentRows ?? [];
$recentColumns = $recentColumns ?? [];
$colCount = count($recentColumns);
?>
<section class="prod-card prod-card--table prod-entry-recent">
    <div class="prod-card__head d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="prod-card__title mb-0"><?= e($recentTitle) ?></h2>
        <span class="prod-entry-table-count small text-muted" data-prod-entry-count><?= e((string)count($recentRows)) ?> shown</span>
    </div>
    <div class="prod-entry-table-toolbar">
        <label class="prod-entry-table-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search"
                   class="form-control form-control-sm prod-entry-table-search__input"
                   placeholder="Search date, shift, tyre, machine, operator…"
                   data-prod-entry-search
                   autocomplete="off"
                   aria-label="Search recent entries">
        </label>
    </div>
    <div class="prod-entry-table-scroll-hint" data-prod-entry-scroll-hint>
        <i class="bi bi-arrows-expand" aria-hidden="true"></i> Scroll to view all columns
    </div>
    <div class="prod-entry-table-wrap" tabindex="0" data-prod-entry-scroll>
        <table class="table table-sm prod-table mb-0 prod-entry-recent-table">
            <thead>
                <tr>
                    <?php foreach ($recentColumns as $col): ?>
                        <th class="<?= e((string)($col['class'] ?? '')) ?>"><?= e($col['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody data-prod-entry-tbody>
            <?php foreach ($recentRows as $r): ?>
                <?php
                $searchBits = [];
                foreach ($recentColumns as $col) {
                    $searchBits[] = (string)($r[$col['key']] ?? '');
                }
                $searchText = strtolower(implode(' ', $searchBits));
                ?>
                <tr data-prod-entry-row data-search="<?= e($searchText) ?>">
                    <?php foreach ($recentColumns as $col): ?>
                        <?php
                        $val = $r[$col['key']] ?? '—';
                        if ($val === '' || $val === null) {
                            $val = '—';
                        }
                        ?>
                        <td class="<?= e((string)($col['class'] ?? '')) ?>"><?= e((string)$val) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentRows): ?>
                <tr data-prod-entry-empty>
                    <td colspan="<?= $colCount ?>" class="text-center text-muted py-4">No production entries found.</td>
                </tr>
            <?php endif; ?>
            <tr data-prod-entry-no-match class="d-none">
                <td colspan="<?= $colCount ?>" class="text-center text-muted py-4">No entries match your search.</td>
            </tr>
            </tbody>
        </table>
    </div>
</section>
