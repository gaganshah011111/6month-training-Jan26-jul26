<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = current_user();
$userName = (string)($user['name'] ?? 'Sales Manager');
$loginUser = trim((string)($user['username'] ?? ''));
$initial = strtoupper(substr($userName, 0, 1));
?>
<nav class="navbar navbar-expand-lg app-navbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e(route_url('sales/dashboard')) ?>">
            <span class="brand-mark">R</span>
            <span>Ralson ERP — Sales</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="role-badge d-none d-md-inline">Sales Manager</span>
            <span class="user-chip">
                <span class="user-avatar"><?= e($initial) ?></span>
                <span class="d-none d-md-inline">
                    <strong><?= e($userName) ?></strong>
                    <?php if ($loginUser !== ''): ?><small class="d-block text-white-50 font-monospace"><?= e($loginUser) ?></small><?php endif; ?>
                    <small>Sales Department</small>
                </span>
            </span>
            <a class="btn btn-sm btn-outline-light app-logout" href="logout.php">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>
