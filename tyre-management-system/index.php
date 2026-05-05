<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/app.php';

require_auth();
$routes = require __DIR__ . '/routes/web.php';
$page = $_GET['page'] ?? 'dashboard';
$path = $routes[$page] ?? null;
$user = current_user();
$isEmployee = $user && ($user['role'] ?? '') === 'Employee';

if (!$path) {
    http_response_code(404);
    echo 'Route not found';
    exit;
}

if ($isEmployee && strncmp($page, 'employee/', 9) !== 0) {
    header('Location: ' . route_url('employee/dashboard'));
    exit;
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="col-md-10 p-4">
            <?php require __DIR__ . '/' . $path; ?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

