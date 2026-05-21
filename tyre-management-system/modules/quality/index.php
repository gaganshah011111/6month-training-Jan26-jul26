<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_workflow.php';

if (!has_role(['Super Admin', 'Quality Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');
    $orderId = post_int('production_order_id');
    try {
        if ($action === 'approve') {
            production_qc_approve_order(
                $pdo,
                $orderId,
                post_int('passed_qty'),
                post_int('failed_qty'),
                post_string('inspector_name', 150),
                post_string('defects'),
                post_string('warehouse_location', 120),
                $userId
            );
            set_flash('success', 'Batch approved — finished goods updated in inventory.');
        } elseif ($action === 'reject') {
            production_qc_reject_order(
                $pdo,
                $orderId,
                post_int('failed_qty'),
                post_string('defects'),
                $userId
            );
            set_flash('warning', 'Batch rejected — order returned to production for rework.');
        } else {
            throw new InvalidArgumentException('Unknown action.');
        }
    } catch (Throwable $e) {
        set_flash('danger', 'QC action failed: ' . $e->getMessage());
    }
    redirect('quality/list');
}

$pendingOrders = $pdo->query(
    "SELECT id, order_code, tyre_type, target_qty, total_produced, total_rejected, updated_at
     FROM production_orders WHERE status = 'QC Pending' ORDER BY updated_at ASC"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$rows = $pdo->query(
    'SELECT q.*, o.order_code, p.production_date
     FROM quality_checks q
     LEFT JOIN production_orders o ON o.id = q.production_order_id
     LEFT JOIN production p ON p.id = q.production_id
     ORDER BY q.id DESC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Quality Control</h1>
            <p class="prod-page__sub">Inspect production batches after curing. Only approved tyres enter finished goods for dispatch.</p>
        </div>
    </header>

    <section class="prod-card mb-3">
        <div class="prod-card__head"><h2 class="prod-card__title">Pending QC batches</h2></div>
        <div class="prod-card__body">
            <?php if (!$pendingOrders): ?>
                <p class="text-muted small mb-0">No orders awaiting inspection. Production Manager sends completed curing batches here.</p>
            <?php else: ?>
                <?php foreach ($pendingOrders as $po): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <strong><?= e($po['order_code']) ?></strong>
                                <span class="text-muted small ms-2"><?= e($po['tyre_type']) ?> · Shop produced <?= e((string)$po['total_produced']) ?></span>
                            </div>
                        </div>
                        <form method="post" class="row g-2 align-items-end">
                            <?= csrf_input() ?>
                            <input type="hidden" name="production_order_id" value="<?= (int)$po['id'] ?>">
                            <div class="col-md-2">
                                <label class="form-label small">Inspector</label>
                                <input class="form-control form-control-sm" name="inspector_name" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Passed qty</label>
                                <input class="form-control form-control-sm" type="number" name="passed_qty" min="0" value="<?= (int)$po['total_produced'] ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Failed qty</label>
                                <input class="form-control form-control-sm" type="number" name="failed_qty" min="0" value="0" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Warehouse</label>
                                <input class="form-control form-control-sm" name="warehouse_location" value="FG-A1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Defect remarks</label>
                                <input class="form-control form-control-sm" name="defects" placeholder="Defects / notes">
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve batch</button>
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject and send back to production?');">Reject batch</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="prod-card prod-card--table">
        <div class="prod-card__head"><h2 class="prod-card__title">Inspection history</h2></div>
        <div class="table-responsive">
            <table class="table table-sm prod-table mb-0">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th class="text-end">Pass</th>
                        <th class="text-end">Fail</th>
                        <th>Status</th>
                        <th>Defects</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['order_code'] ?? ('#' . $r['production_id'])) ?></td>
                        <td><?= e($r['inspection_date']) ?></td>
                        <td class="text-end"><?= e((string)$r['passed_qty']) ?></td>
                        <td class="text-end"><?= e((string)$r['failed_qty']) ?></td>
                        <td><?= e((string)($r['quality_status'] ?? '')) ?></td>
                        <td><?= e((string)($r['defects'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-muted text-center py-3">No inspections recorded.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
