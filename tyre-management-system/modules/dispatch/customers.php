<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/sales_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$customers = sales_customers_for_dispatch($pdo);
?>

<div class="dsp-page">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">Customers</h1>
            <p class="dsp-page__sub">Customer details are auto-fetched from Sales Orders. Registration is handled by the sales team.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('dispatch/new')) ?>">New dispatch</a>
        </nav>
    </header>

    <div class="dsp-entry-alert dsp-entry-alert--warn mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Customer master is read-only here. Contact sales to add or update customer records.
    </div>

    <div class="dsp-table-wrap dsp-table-scroll">
        <table class="dsp-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Company</th>
                    <th>Type</th>
                    <th>Contact</th>
                    <th>City</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= e((string)$c['customer_code']) ?></td>
                    <td><?= e((string)$c['company_name']) ?></td>
                    <td><?= e((string)$c['customer_type']) ?></td>
                    <td><?= e((string)($c['contact_person'] ?? '—')) ?></td>
                    <td><?= e((string)($c['city'] ?? '—')) ?></td>
                    <td><span class="dsp-badge dsp-badge--delivered"><?= e((string)$c['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($customers === []): ?>
                <tr><td colspan="6" class="dsp-empty">No active customers on file.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
