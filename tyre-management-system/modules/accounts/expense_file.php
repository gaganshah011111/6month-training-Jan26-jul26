<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_expenses.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'Sales Manager'])) {
    http_response_code(403);
    exit('Access denied');
}

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$which = (string)($_GET['which'] ?? 'bill');
$row = $id > 0 ? acc_get_expense($pdo, $id) : null;
if (!$row) {
    http_response_code(404);
    exit('File not found');
}

$key = match ($which) {
    'receipt' => 'attachment_receipt',
    'invoice' => 'attachment_invoice',
    default => 'attachment_bill',
};
$path = (string)($row[$key] ?? $row['attachment'] ?? '');
if ($path === '') {
    http_response_code(404);
    exit('No attachment');
}
$full = dirname(__DIR__, 2) . '/' . ltrim($path, '/');
if (!is_file($full)) {
    http_response_code(404);
    exit('File missing');
}
$mime = mime_content_type($full) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($full) . '"');
readfile($full);
exit;
