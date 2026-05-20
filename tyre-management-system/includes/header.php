<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/erp_ui.php';
$cssPath = __DIR__ . '/../assets/css/style.css';
$cssVersion = is_file($cssPath) ? (string)filemtime($cssPath) : (string)time();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= e($cssVersion) ?>" rel="stylesheet">
    <?php
    $hdrUser = current_user();
    $hdrRole = (string)($hdrUser['role'] ?? '');
    if ($hdrUser) {
        erp_ui_enqueue();
    }
    if (in_array($hdrRole, ['Super Admin', 'HR Manager', 'Admin'], true)) {
        $notifyCss = __DIR__ . '/../assets/css/app-notifications.css';
        if (is_file($notifyCss)) {
            echo '<link href="assets/css/app-notifications.css?v=' . e((string)filemtime($notifyCss)) . '" rel="stylesheet">' . "\n";
        }
    }
    ?>
</head>
<body class="app-body">

