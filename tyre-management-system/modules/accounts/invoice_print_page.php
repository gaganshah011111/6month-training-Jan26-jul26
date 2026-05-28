<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$inv = sales_get_invoice($pdo, $id);
if (!$inv) {
    echo 'Invoice not found';
    exit;
}

$company = dispatch_company_name($pdo);
$pending = max(0, (float)$inv['total_amount'] - (float)$inv['amount_paid']);
$backUrl = route_url('accounts/invoice-view', ['id' => $id]);
$autoPrint = isset($_GET['print']);
require __DIR__ . '/../sales/invoice_print.php';
