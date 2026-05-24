<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$page = (string)($_GET['page'] ?? 'sales/dashboard');
$links = [
    'sales/dashboard' => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    'sales/customers' => ['label' => 'Customers', 'icon' => 'bi-building'],
    'sales/orders' => ['label' => 'Sales Orders', 'icon' => 'bi-cart-check'],
    'sales/invoices' => ['label' => 'Invoices', 'icon' => 'bi-receipt'],
    'sales/payments' => ['label' => 'Payments', 'icon' => 'bi-cash-stack'],
    'sales/dispatch' => ['label' => 'Dispatch Tracking', 'icon' => 'bi-truck'],
    'sales/reports' => ['label' => 'CRM Reports', 'icon' => 'bi-clipboard-data'],
    'sales/analytics' => ['label' => 'Analytics', 'icon' => 'bi-graph-up'],
];
?>
<aside class="col-lg-2 col-md-3 app-sidebar min-vh-100 p-3 sidebar-fixed">
    <div class="sidebar-section text-uppercase fw-semibold small mb-1">Sales &amp; CRM</div>
    <?php foreach ($links as $route => $meta): ?>
        <?php
        $isActive = $page === $route;
        if (!$isActive && $route === 'sales/orders' && in_array($page, ['sales/order'], true)) {
            $isActive = true;
        }
        if (!$isActive && $route === 'sales/customers' && $page === 'sales/customer') {
            $isActive = true;
        }
        if (!$isActive && $route === 'sales/invoices' && in_array($page, ['sales/invoice', 'sales/invoice-print'], true)) {
            $isActive = true;
        }
        if (!$isActive && $route === 'sales/dispatch' && $page === 'sales/dispatch-entry') {
            $isActive = true;
        }
        ?>
        <a class="sidebar-link d-flex align-items-center gap-2 p-2 text-decoration-none rounded mb-1 <?= $isActive ? 'active-nav' : '' ?>" href="<?= e(route_url($route)) ?>">
            <i class="bi <?= e($meta['icon']) ?>"></i>
            <span><?= e($meta['label']) ?></span>
        </a>
    <?php endforeach; ?>
    <a class="sidebar-link d-flex align-items-center gap-2 p-2 text-decoration-none rounded mb-1 mt-3" href="logout.php">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>
</aside>
