<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/app.php';

require_auth();
$user = current_user();

if (!isset($_GET['page'])) {
    $homeFile = role_home_file((string)($user['role'] ?? ''));
    if ($homeFile !== 'login.php') {
        header('Location: ' . $homeFile);
        exit;
    }
    header('Location: login.php');
    exit;
}

$routes = require __DIR__ . '/routes/web.php';
$defaultPage = role_home_page((string)((current_user()['role'] ?? '')));
$page = $_GET['page'] ?? $defaultPage;
$path = $routes[$page] ?? null;

if (!$path) {
    http_response_code(404);
    echo 'Route not found';
    exit;
}

if (!can_access_page($page, $user)) {
    $target = role_home_page((string)($user['role'] ?? ''));
    if ($target === $page) {
        header('Location: 403.php');
        exit;
    }
    if (str_contains($target, '.php')) {
        header('Location: ' . $target);
    } else {
        header('Location: ' . route_url($target));
    }
    exit;
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
$flash = get_flash();
?>
<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="col-lg-10 col-md-9 p-4 offset-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php require __DIR__ . '/' . $path; ?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

