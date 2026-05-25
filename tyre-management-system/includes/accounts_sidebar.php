<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$page = (string)($_GET['page'] ?? 'accounts/dashboard');
$links = [
    'accounts/dashboard' => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    'accounts/ledger' => ['label' => 'Customer Ledger', 'icon' => 'bi-journal-text'],
    'accounts/receivables' => ['label' => 'Receivables', 'icon' => 'bi-hourglass-split'],
    'accounts/expenses' => ['label' => 'Expenses', 'icon' => 'bi-wallet2'],
    'accounts/cashbook' => ['label' => 'Cashbook', 'icon' => 'bi-cash'],
    'accounts/bankbook' => ['label' => 'Bankbook', 'icon' => 'bi-bank'],
    'accounts/gst' => ['label' => 'GST Reports', 'icon' => 'bi-percent'],
    'accounts/pnl' => ['label' => 'Profit & Loss', 'icon' => 'bi-graph-up-arrow'],
    'accounts/reports' => ['label' => 'Financial Reports', 'icon' => 'bi-file-earmark-bar-graph'],
];
?>
<aside class="col-lg-2 col-md-3 app-sidebar min-vh-100 p-3 sidebar-fixed accounts-sidebar">
    <div class="sidebar-section text-uppercase fw-semibold small mb-1">Accounts &amp; Finance</div>
    <?php foreach ($links as $route => $meta): ?>
        <?php
        $isActive = $page === $route;
        if (!$isActive && $route === 'accounts/ledger' && $page === 'accounts/ledger-view') {
            $isActive = true;
        }
        ?>
        <a class="sidebar-link d-flex align-items-center gap-2 p-2 text-decoration-none rounded mb-1 <?= $isActive ? 'active-nav' : '' ?>" href="<?= e(route_url($route)) ?>">
            <i class="bi <?= e($meta['icon']) ?>"></i>
            <span><?= e($meta['label']) ?></span>
        </a>
    <?php endforeach; ?>
    <?php if (has_role('Sales Manager')): ?>
        <a class="sidebar-link d-flex align-items-center gap-2 p-2 text-decoration-none rounded mb-1 mt-2" href="<?= e(route_url('sales/dashboard')) ?>">
            <i class="bi bi-cart-check"></i>
            <span>Sales &amp; CRM</span>
        </a>
    <?php endif; ?>
    <a class="sidebar-link d-flex align-items-center gap-2 p-2 text-decoration-none rounded mb-1 mt-3" href="logout.php">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>
</aside>
