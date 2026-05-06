<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$user = current_user();
$home = $user ? role_home_page((string)($user['role'] ?? '')) : 'login.php';
http_response_code(403);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container text-center">
    <div class="card shadow-sm mx-auto" style="max-width:560px;">
        <div class="card-body p-4">
            <h4 class="mb-2">Access Denied (403)</h4>
            <p class="text-muted mb-3">You do not have permission to open this page.</p>
            <a class="btn btn-primary" href="<?= e(str_contains($home, '.php') ? $home : route_url($home)) ?>">Go to My Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
