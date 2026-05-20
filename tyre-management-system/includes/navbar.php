<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$user = current_user();
$home = $user ? role_home_page((string)($user['role'] ?? '')) : 'dashboard';
$userName = (string)($user['name'] ?? 'Guest');
$loginUser = trim((string)($user['username'] ?? ''));
$userRole = (string)($user['role'] ?? '');
$roleLabel = $userRole !== '' ? $userRole : 'Visitor';
$initial = strtoupper(substr($userName, 0, 1));
$showNotify = has_role(['Super Admin', 'HR Manager', 'Admin']);
$notifyApi = $showNotify ? route_url('api/hr-notifications') : '';
?>
<nav class="navbar navbar-expand-lg app-navbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e(route_url($home)) ?>">
            <span class="brand-mark">R</span>
            <span>Ralson ERP</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if ($showNotify): ?>
            <div class="app-notify-backdrop" id="appNotifyBackdrop" aria-hidden="true"></div>
            <div class="app-notify-wrap" id="appNotifyDropdown" data-api="<?= e($notifyApi) ?>">
                <button type="button" class="btn btn-sm app-notify-btn" aria-label="Notifications" aria-expanded="false">
                    <i class="bi bi-bell"></i>
                    <span class="app-notify-count" hidden></span>
                    <span class="app-notify-dot" hidden></span>
                </button>
                <div class="app-notify-panel" role="menu">
                    <div class="app-notify-panel__head">
                        <p class="app-notify-panel__title mb-0">
                            Notifications
                            <span class="app-notify-panel__badge" hidden>0</span>
                        </p>
                        <div class="app-notify-panel__actions">
                            <button type="button" data-notify-action="read_all">Mark all read</button>
                            <span class="sep">|</span>
                            <button type="button" data-notify-action="clear_all">Clear all</button>
                        </div>
                    </div>
                    <ul class="app-notify-list"></ul>
                </div>
            </div>
            <?php endif; ?>
            <span class="role-badge d-none d-md-inline"><?= e($roleLabel) ?></span>
            <span class="user-chip">
                <span class="user-avatar"><?= e($initial) ?></span>
                <span class="d-none d-md-inline">
                    <strong><?= e($userName) ?></strong>
                    <?php if ($loginUser !== ''): ?><small class="d-block text-white-50 font-monospace"><?= e($loginUser) ?></small><?php endif; ?>
                    <small>ERP Panel</small>
                </span>
            </span>
            <a class="btn btn-sm btn-outline-light app-logout" href="logout.php">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>
