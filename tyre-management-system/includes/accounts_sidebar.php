<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$page = (string)($_GET['page'] ?? 'accounts/dashboard');
$links = [
    'accounts/dashboard' => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    'accounts/ledger' => ['label' => 'Customer Ledger', 'icon' => 'bi-journal-text'],
    'accounts/supplier-ledger' => ['label' => 'Supplier Ledger', 'icon' => 'bi-receipt'],
    'accounts/receivables' => ['label' => 'Receivables', 'icon' => 'bi-hourglass-split'],
    'accounts/payables' => ['label' => 'Payables', 'icon' => 'bi-journal-arrow-down'],
    'accounts/expenses' => ['label' => 'Expenses', 'icon' => 'bi-wallet2'],
    'accounts/cashbook' => ['label' => 'Cash & Bank', 'icon' => 'bi-bank'],
    'accounts/reports' => ['label' => 'Reports', 'icon' => 'bi-file-earmark-bar-graph'],
    'accounts/transactions-history' => ['label' => 'Transactions History', 'icon' => 'bi-clock-history'],
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
        if (!$isActive && $route === 'accounts/receivables' && in_array($page, ['accounts/invoice-view', 'accounts/invoice-print'], true)) {
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
