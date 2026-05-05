<?php
declare(strict_types=1);
$user = current_user();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(route_url('dashboard')) ?>">Ralson ERP</a>
        <div class="ms-auto text-white small">
            <?= $user ? e($user['name'] . ' (' . $user['role'] . ')') : '' ?>
            <a class="btn btn-sm btn-outline-light ms-2" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

