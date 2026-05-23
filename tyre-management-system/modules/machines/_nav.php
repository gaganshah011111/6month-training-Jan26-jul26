<?php
declare(strict_types=1);
$pageKey = (string)($_GET['page'] ?? 'machines/list');
$links = [
    'machines/dashboard' => 'Overview',
    'machines/list' => 'Machine Master',
    'machines/assignments' => 'Assignments',
    'machines/inventory' => 'Inventory',
    'machines/history' => 'History',
];
?>
<nav class="mach-subnav" aria-label="Machine module">
    <?php foreach ($links as $route => $label): ?>
        <a class="mach-subnav__link<?= $pageKey === $route ? ' is-active' : '' ?>" href="<?= e(route_url($route)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
