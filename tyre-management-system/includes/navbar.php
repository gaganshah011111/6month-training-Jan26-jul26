<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$user = current_user();
$home = $user ? role_home_page((string)($user['role'] ?? '')) : 'dashboard';
$userName = (string)($user['name'] ?? 'Guest');
$userRole = (string)($user['role'] ?? '');
$roleLabel = $userRole !== '' ? $userRole : 'Visitor';
$initial = strtoupper(substr($userName, 0, 1));
?>
<nav class="navbar navbar-expand-lg app-navbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e(route_url($home)) ?>">
            <span class="brand-mark">R</span>
            <span>Ralson ERP</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm app-notify-btn" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <span class="app-notify-dot"></span>
            </button>
            <span class="role-badge d-none d-md-inline"><?= e($roleLabel) ?></span>
            <span class="user-chip">
                <span class="user-avatar"><?= e($initial) ?></span>
                <span class="d-none d-md-inline">
                    <strong><?= e($userName) ?></strong>
                    <small>ERP Panel</small>
                </span>
            </span>
            <a class="btn btn-sm btn-outline-light app-logout" href="logout.php">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

