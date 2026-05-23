<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/qc_service.php';
require_once __DIR__ . '/_flow.php';

if (!has_role(['Quality Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $curingId = post_int('curing_id');
    try {
        qc_start_inspection($pdo, $curingId);
        redirect('quality/inspect&id=' . $curingId);
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect('quality/pending');
    }
}

$pending = qc_list_pending_batches($pdo);
?>

<div class="qc-page">
    <?php qc_render_flow_bar('qc'); ?>

    <header class="qc-page__head">
        <div>
            <h1 class="qc-page__title">Pending Inspections</h1>
            <p class="qc-page__sub">Curing output from Production awaiting quality inspection</p>
        </div>
        <nav class="qc-nav-quick">
            <a href="<?= e(route_url('quality/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('production/curing')) ?>">Curing entry</a>
        </nav>
    </header>

    <section class="qc-card">
        <header class="qc-card__head">
            <h2 class="qc-card__title">Batches from production (curing)</h2>
            <p class="qc-card__sub mb-0">Each row is a curing batch linked to the production module</p>
        </header>
        <div class="table-responsive">
            <table class="qc-table">
                <thead>
                    <tr>
                        <th>Batch ID</th>
                        <th>Tyre type</th>
                        <th>Production date</th>
                        <th>Machine</th>
                        <th>Shift</th>
                        <th class="text-end">Produced qty</th>
                        <th>QC status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $b): ?>
                    <tr>
                        <td><strong><?= e((string)($b['batch_code'] ?? 'CUR-' . $b['id'])) ?></strong></td>
                        <td><?= e((string)$b['tyre_type']) ?></td>
                        <td><?= e((string)$b['production_date']) ?></td>
                        <td><?= e((string)($b['machine_code'] ?? '—')) ?></td>
                        <td><?= e((string)$b['shift']) ?></td>
                        <td class="text-end"><?= e(qc_format_qty((int)$b['produced_qty'])) ?></td>
                        <td><span class="qc-badge qc-badge--<?= e(qc_status_badge((string)$b['qc_status'])) ?>"><?= e((string)$b['qc_status']) ?></span></td>
                        <td class="text-nowrap">
                            <form method="post" class="d-inline">
                                <?= csrf_input() ?>
                                <input type="hidden" name="curing_id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="btn btn-sm qc-btn-primary">Start inspection</button>
                            </form>
                            <?php if (($b['qc_status'] ?? '') === QC_STATUS_INSPECTING): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('quality/inspect') . '&id=' . (int)$b['id']) ?>">Continue</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($pending === []): ?>
                    <tr>
                        <td colspan="8" class="qc-empty">
                            No batches pending QC. Record <a href="<?= e(route_url('production/curing')) ?>">curing output</a> in Production first.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
