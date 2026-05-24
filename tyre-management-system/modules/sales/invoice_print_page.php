<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

require_sales_manager();

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$inv = sales_get_invoice($pdo, $id);
if (!$inv) {
    echo 'Invoice not found';
    return;
}
$company = dispatch_company_name($pdo);
$pending = (float)$inv['total_amount'] - (float)$inv['amount_paid'];
require __DIR__ . '/invoice_print.php';
