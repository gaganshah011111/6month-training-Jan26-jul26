<?php
declare(strict_types=1);
/** @var array<string, mixed> $it */
/** @var int $lineIndex */
$lineIndex = $lineIndex ?? 1;
?>
<article class="so-line-card sales-order-line" data-line-index="<?= (int)$lineIndex ?>">
    <header class="so-line-card__head">
        <span class="so-line-card__num">Line <?= (int)$lineIndex ?></span>
        <button type="button" class="btn btn-sm btn-outline-danger sales-remove-line" title="Remove line" aria-label="Remove line">
            <i class="bi bi-trash"></i>
        </button>
    </header>
    <div class="row g-2 so-line-card__grid">
        <div class="col-12 col-md-6 col-xl-4">
            <label class="so-field-label">Tyre type <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm so-tyre-select" name="tyre_type[]" data-line-tyre required data-placeholder="Search tyre type…">
                <option value="">Select tyre type…</option>
                <?php foreach (TYRE_TYPES as $t): ?>
                    <option value="<?= e($t) ?>" <?= ($it['tyre_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="so-field-label">Available stock</label>
            <div class="so-avail-display" data-line-avail>—</div>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="so-field-label">Quantity <span class="text-danger">*</span></label>
            <input type="number" class="form-control form-control-sm" name="qty[]" data-line-qty min="1" step="1" value="<?= e((string)($it['qty_ordered'] ?? '')) ?>" required>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="so-field-label">Rate (₹)</label>
            <input type="number" class="form-control form-control-sm" name="rate[]" data-line-rate min="0" step="0.01" value="<?= e((string)($it['rate'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="so-field-label">GST %</label>
            <input type="number" class="form-control form-control-sm" name="gst_percent[]" data-line-gst min="0" max="100" step="0.01" value="<?= e((string)($it['gst_percent'] ?? '18')) ?>">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="so-field-label">Discount (₹)</label>
            <input type="number" class="form-control form-control-sm" name="line_discount[]" data-line-disc min="0" step="0.01" value="<?= e((string)($it['discount_amount'] ?? '0')) ?>">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="so-field-label">Line total</label>
            <div class="so-line-total-display" data-line-total>₹0.00</div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <label class="so-field-label">Stock status</label>
            <div class="so-stock-display" data-line-stock><span class="so-stock-muted">Select tyre and quantity</span></div>
        </div>
    </div>
</article>
