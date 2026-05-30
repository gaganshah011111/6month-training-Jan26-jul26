-- =============================================================================
-- Tyre ERP — FULL DATABASE BACKUP (use this file if MySQL fails or data is lost)
-- =============================================================================
-- Generated: 2026-05-30T23:32:01+05:30
-- Database: tyre_erp
-- File: database/sql/FULL_DATABASE_BACKUP.sql
--
-- RESTORE (XAMPP command line):
--   c:\xampp\mysql\bin\mysql.exe -u root < database\sql\FULL_DATABASE_BACKUP.sql
--
-- RESTORE (phpMyAdmin): Import this file into server (no database selected first).
--
-- Regenerate backup after changes:
--   php tools/db_sync.php export
--   or double-click: tools/backup_database.bat
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE DATABASE IF NOT EXISTS `tyre_erp` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tyre_erp`;

DROP TABLE IF EXISTS `accounts_expenses`;
CREATE TABLE `accounts_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(40) NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(20) NOT NULL DEFAULT 'Cash',
  `expense_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_by` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reference_no` varchar(80) DEFAULT NULL,
  `attachment_bill` varchar(255) DEFAULT NULL,
  `attachment_receipt` varchar(255) DEFAULT NULL,
  `attachment_invoice` varchar(255) DEFAULT NULL,
  `source_type` varchar(32) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_acc_expense_date` (`expense_date`),
  KEY `idx_acc_expense_cat` (`category`),
  KEY `idx_acc_expense_mode` (`payment_mode`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts_expenses` (`id`, `category`, `amount`, `payment_mode`, `expense_date`, `remarks`, `attachment`, `created_by`, `created_at`, `reference_no`, `attachment_bill`, `attachment_receipt`, `attachment_invoice`, `source_type`, `source_id`) VALUES (1, 'Salary', '5680.98', 'Cash', '2026-05-29', 'Salary — Gagan Kumar Shah · May 2026', NULL, 'accounts_manager', '2026-05-29 18:01:57', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `accounts_expenses` (`id`, `category`, `amount`, `payment_mode`, `expense_date`, `remarks`, `attachment`, `created_by`, `created_at`, `reference_no`, `attachment_bill`, `attachment_receipt`, `attachment_invoice`, `source_type`, `source_id`) VALUES (2, 'Electricity', '70000.00', 'Cheque', '2026-05-29', 'fjgndfjg', 'uploads/expenses/2026/05/exp_6a199d6753ccc6.92123762_Tyre_Factory_ERP_Synopsis.pdf', 'accounts_manager', '2026-05-29 19:36:31', NULL, 'uploads/expenses/2026/05/exp_6a199d6753ccc6.92123762_Tyre_Factory_ERP_Synopsis.pdf', NULL, NULL, NULL, NULL);

DROP TABLE IF EXISTS `accounts_loan_repayments`;
CREATE TABLE `accounts_loan_repayments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payment_date` date NOT NULL,
  `payment_mode` varchar(30) NOT NULL DEFAULT 'Bank',
  `reference_no` varchar(80) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `ledger_entry_id` int(11) DEFAULT NULL,
  `created_by` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_loan_repay_loan` (`loan_id`),
  KEY `idx_loan_repay_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `accounts_loans`;
CREATE TABLE `accounts_loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_code` varchar(32) NOT NULL,
  `loan_source` varchar(60) NOT NULL,
  `lender_name` varchar(120) DEFAULT NULL,
  `principal_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(6,2) NOT NULL DEFAULT 0.00,
  `loan_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `repaid_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `ledger_entry_id` int(11) DEFAULT NULL,
  `created_by` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loan_code` (`loan_code`),
  KEY `idx_loan_status` (`status`),
  KEY `idx_loan_due` (`due_date`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts_loans` (`id`, `loan_code`, `loan_source`, `lender_name`, `principal_amount`, `interest_rate`, `loan_date`, `due_date`, `remarks`, `document_path`, `status`, `repaid_amount`, `ledger_entry_id`, `created_by`, `created_at`) VALUES (1, 'LOAN-20260529-0001', 'Bank Loan', 'Bank Loan', '500000.00', '0.00', '2026-05-29', NULL, NULL, NULL, 'Active', '0.00', 14, 'accounts_manager', '2026-05-29 20:03:25');
INSERT INTO `accounts_loans` (`id`, `loan_code`, `loan_source`, `lender_name`, `principal_amount`, `interest_rate`, `loan_date`, `due_date`, `remarks`, `document_path`, `status`, `repaid_amount`, `ledger_entry_id`, `created_by`, `created_at`) VALUES (2, 'LOAN-20260529-0002', 'Bank Loan', 'Bank Loan', '500000.00', '0.00', '2026-05-29', NULL, NULL, NULL, 'Active', '0.00', 15, 'accounts_manager', '2026-05-29 20:03:33');
INSERT INTO `accounts_loans` (`id`, `loan_code`, `loan_source`, `lender_name`, `principal_amount`, `interest_rate`, `loan_date`, `due_date`, `remarks`, `document_path`, `status`, `repaid_amount`, `ledger_entry_id`, `created_by`, `created_at`) VALUES (3, 'LOAN-20260529-0003', 'Bank Loan', 'Bank Loan', '500000.00', '0.00', '2026-05-29', NULL, NULL, 'uploads/treasury/2026/05/loan_6a19a3c3d91db2.11664764.png', 'Active', '0.00', 16, 'accounts_manager', '2026-05-29 20:03:39');
INSERT INTO `accounts_loans` (`id`, `loan_code`, `loan_source`, `lender_name`, `principal_amount`, `interest_rate`, `loan_date`, `due_date`, `remarks`, `document_path`, `status`, `repaid_amount`, `ledger_entry_id`, `created_by`, `created_at`) VALUES (4, 'LOAN-20260529-0004', 'Bank Loan', 'Bank Loan', '500000.00', '0.00', '2026-05-29', NULL, NULL, 'uploads/treasury/2026/05/loan_6a19a3c59fa868.32982094.png', 'Active', '0.00', 17, 'accounts_manager', '2026-05-29 20:03:41');
INSERT INTO `accounts_loans` (`id`, `loan_code`, `loan_source`, `lender_name`, `principal_amount`, `interest_rate`, `loan_date`, `due_date`, `remarks`, `document_path`, `status`, `repaid_amount`, `ledger_entry_id`, `created_by`, `created_at`) VALUES (5, 'LOAN-20260529-0005', 'Bank Loan', 'Bank Loan', '999999999999.99', '0.00', '2026-05-29', NULL, NULL, NULL, 'Active', '0.00', 18, 'accounts_manager', '2026-05-29 20:04:24');
INSERT INTO `accounts_loans` (`id`, `loan_code`, `loan_source`, `lender_name`, `principal_amount`, `interest_rate`, `loan_date`, `due_date`, `remarks`, `document_path`, `status`, `repaid_amount`, `ledger_entry_id`, `created_by`, `created_at`) VALUES (6, 'LOAN-20260529-0006', 'Bank Loan', 'osfosdodf', '100000.00', '0.00', '2026-05-29', NULL, NULL, NULL, 'Active', '0.00', 19, 'accounts_manager', '2026-05-29 20:14:33');
INSERT INTO `accounts_loans` (`id`, `loan_code`, `loan_source`, `lender_name`, `principal_amount`, `interest_rate`, `loan_date`, `due_date`, `remarks`, `document_path`, `status`, `repaid_amount`, `ledger_entry_id`, `created_by`, `created_at`) VALUES (7, 'LOAN-20260529-0007', 'Bank Loan', 'gdfgd', '3333333333333.00', '0.00', '2026-05-29', '2026-05-31', NULL, NULL, 'Active', '0.00', 20, 'accounts_manager', '2026-05-29 20:23:19');

DROP TABLE IF EXISTS `accounts_salary_batches`;
CREATE TABLE `accounts_salary_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `month_year` varchar(7) NOT NULL,
  `employee_count` int(11) NOT NULL DEFAULT 0,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partial','paid') NOT NULL DEFAULT 'pending',
  `sent_by` varchar(120) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `generated_by_hr` varchar(120) DEFAULT NULL,
  `payroll_closed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salary_batch_month` (`month_year`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts_salary_batches` (`id`, `month_year`, `employee_count`, `total_amount`, `paid_amount`, `status`, `sent_by`, `sent_at`, `generated_by_hr`, `payroll_closed_at`, `notes`, `created_at`, `updated_at`) VALUES (1, '2026-05', 8, '167512.98', '167512.98', 'paid', 'HR Payroll Sync', '2026-05-29 17:12:26', 'HR Payroll Sync', '2026-05-29 18:01:57', NULL, '2026-05-29 17:12:26', '2026-05-29 18:01:57');

DROP TABLE IF EXISTS `accounts_salary_payments`;
CREATE TABLE `accounts_salary_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `salary_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_mode` varchar(40) NOT NULL DEFAULT 'Bank',
  `reference_no` varchar(80) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` varchar(120) DEFAULT NULL,
  `expense_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_salary_pay_batch` (`batch_id`),
  KEY `idx_salary_pay_salary` (`salary_id`),
  KEY `idx_salary_pay_employee` (`employee_id`),
  CONSTRAINT `fk_salary_pay_batch` FOREIGN KEY (`batch_id`) REFERENCES `accounts_salary_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts_salary_payments` (`id`, `batch_id`, `salary_id`, `employee_id`, `payment_date`, `amount`, `payment_mode`, `reference_no`, `remarks`, `recorded_by`, `expense_id`, `created_at`) VALUES (3, 1, 17, 114, '2026-05-29', '5680.98', 'Cash', NULL, NULL, 'accounts_manager', 1, '2026-05-29 18:01:57');

DROP TABLE IF EXISTS `accounts_treasury_ledger`;
CREATE TABLE `accounts_treasury_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tx_code` varchar(32) NOT NULL,
  `tx_date` date NOT NULL,
  `tx_type` varchar(40) NOT NULL,
  `party` varchar(150) DEFAULT NULL,
  `reference_no` varchar(80) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `direction` enum('credit','debit') NOT NULL,
  `cash_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `bank_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(30) DEFAULT NULL,
  `balance_before` decimal(18,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cash_balance_after` decimal(18,2) NOT NULL DEFAULT 0.00,
  `bank_balance_after` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tx_status` varchar(20) NOT NULL DEFAULT 'Completed',
  `source_module` varchar(40) DEFAULT NULL,
  `source_type` varchar(32) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_treasury_tx_code` (`tx_code`),
  UNIQUE KEY `uq_treasury_source` (`source_module`,`source_type`,`source_id`),
  KEY `idx_treasury_date` (`tx_date`),
  KEY `idx_treasury_type` (`tx_type`),
  KEY `idx_treasury_loan` (`loan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (1, 'TRX-20260529-0001', '2026-05-25', 'Customer Payment', 'Bharat Wheels', 'upi', '1692.10', 'credit', '0.00', '1692.10', 'UPI', '10000000.00', '10001692.10', '10000000.00', '1692.10', 'Completed', 'receivables', 'sales_payment', 1, NULL, 'Customer collection · INV-20260525-4596', 'accounts_manager', '2026-05-29 19:51:02');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (2, 'TRX-20260529-0002', '2026-05-25', 'Customer Payment', 'Bharat Wheels', 'INV-20260525-4596', '0.01', 'credit', '0.01', '0.00', 'Cash', '10001692.10', '10001692.11', '10000000.01', '1692.10', 'Completed', 'receivables', 'sales_payment', 2, NULL, 'Customer collection · INV-20260525-4596', 'accounts_manager', '2026-05-29 19:51:02');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (3, 'TRX-20260529-0003', '2026-05-28', 'Customer Payment', 'Punjab Tyres Distributor', 'INV-20260528-2689', '450.00', 'credit', '0.00', '450.00', 'UPI', '9989836.89', '9990286.89', '9993546.79', '-3259.90', 'Completed', 'receivables', 'sales_payment', 3, NULL, 'Customer collection · INV-20260528-2689', 'accounts_manager', '2026-05-29 19:51:02');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (4, 'TRX-20260529-0004', '2026-05-28', 'Customer Payment', 'Punjab Tyres Distributor', 'INV-20260528-2689', '371.28', 'credit', '371.28', '0.00', 'Cash', '9990286.89', '9990658.17', '9993918.07', '-3259.90', 'Completed', 'receivables', 'sales_payment', 4, NULL, 'Customer collection · INV-20260528-2689', 'accounts_manager', '2026-05-29 19:51:02');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (5, 'TRX-20260529-0005', '2026-05-29', 'Expense', 'Salary', NULL, '5680.98', 'debit', '5680.98', '0.00', 'Cash', '-337724000.80', '-337729681.78', '-337726421.88', '-3259.90', 'Completed', 'expenses', 'expense', 1, NULL, 'Salary — Gagan Kumar Shah · May 2026', 'accounts_manager', '2026-05-29 19:51:02');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (6, 'TRX-20260529-0006', '2026-05-29', 'Expense', 'Electricity', NULL, '70000.00', 'debit', '0.00', '70000.00', 'Cheque', '-337729681.78', '-337799681.78', '-337726421.88', '-73259.90', 'Completed', 'expenses', 'expense', 2, NULL, 'fjgndfjg', 'accounts_manager', '2026-05-29 19:51:02');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (7, 'TRX-20260529-0007', '2026-05-29', 'Salary Payment', 'Gagan Kumar Shah', NULL, '5680.98', 'debit', '5680.98', '0.00', 'Cash', '-337799681.78', '-337805362.76', '-337732102.86', '-73259.90', 'Completed', 'salary', 'salary_payment', 3, NULL, 'Salary · 2026-05', 'accounts_manager', '2026-05-29 19:51:02');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (8, 'TRX-20260529-0008', '2026-05-26', 'Supplier Payment', 'gagan', NULL, '5402.00', 'debit', '0.00', '5402.00', 'UPI', '10001692.11', '9996290.11', '10000000.01', '-3709.90', 'Completed', 'payables', 'supplier_payment', 1, NULL, 'Supplier payment · PINV-20260526-001', 'accounts_manager', '2026-05-29 19:59:35');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (9, 'TRX-20260529-0009', '2026-05-26', 'Supplier Payment', 'gagan', NULL, '6453.22', 'debit', '6453.22', '0.00', 'Cash', '9996290.11', '9989836.89', '9993546.79', '-3709.90', 'Completed', 'payables', 'supplier_payment', 2, NULL, 'Supplier payment · PINV-20260526-004', 'accounts_manager', '2026-05-29 19:59:35');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (10, 'TRX-20260529-0010', '2026-05-28', 'Supplier Payment', 'gagan', NULL, '347705026.37', 'debit', '347705026.37', '0.00', 'Cash', '9990658.17', '-337714368.20', '-337711108.30', '-3259.90', 'Completed', 'payables', 'supplier_payment', 3, NULL, 'Supplier payment · PINV-20260526-006', 'accounts_manager', '2026-05-29 19:59:35');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (11, 'TRX-20260529-0011', '2026-05-28', 'Supplier Payment', 'gagan', NULL, '9582.60', 'debit', '9582.60', '0.00', 'Cash', '-337714368.20', '-337723950.80', '-337720690.90', '-3259.90', 'Completed', 'payables', 'supplier_payment', 4, NULL, 'Supplier payment · PINV-20260526-005', 'accounts_manager', '2026-05-29 19:59:35');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (12, 'TRX-20260529-0012', '2026-05-28', 'Supplier Payment', 'gagan', NULL, '50.00', 'debit', '50.00', '0.00', 'Cash', '-337723950.80', '-337724000.80', '-337720740.90', '-3259.90', 'Completed', 'payables', 'supplier_payment', 5, NULL, 'Supplier payment · PINV-20260526-003', 'accounts_manager', '2026-05-29 19:59:35');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (13, 'TRX-20260529-0013', '2026-05-29', 'Opening Balance', 'Company Cash', 'OPEN-CASH', '10000000.00', 'credit', '10000000.00', '0.00', 'Cash', '-337805362.76', '-327805362.76', '-327732102.86', '-73259.90', 'Completed', 'treasury', 'opening_cash', 1, NULL, 'Opening cash balance', 'accounts_manager', '2026-05-29 20:00:25');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (14, 'TRX-20260529-0014', '2026-05-29', 'Loan Received', NULL, 'LOAN-20260529-0001', '500000.00', 'credit', '0.00', '500000.00', 'Bank', '-327805362.76', '-327305362.76', '-327732102.86', '426740.10', 'Completed', 'loan', 'loan_received', 1, 1, NULL, 'accounts_manager', '2026-05-29 20:03:25');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (15, 'TRX-20260529-0015', '2026-05-29', 'Loan Received', NULL, 'LOAN-20260529-0002', '500000.00', 'credit', '0.00', '500000.00', 'Bank', '-327305362.76', '-326805362.76', '-327732102.86', '926740.10', 'Completed', 'loan', 'loan_received', 2, 2, NULL, 'accounts_manager', '2026-05-29 20:03:33');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (16, 'TRX-20260529-0016', '2026-05-29', 'Loan Received', NULL, 'LOAN-20260529-0003', '500000.00', 'credit', '0.00', '500000.00', 'Bank', '-326805362.76', '-326305362.76', '-327732102.86', '1426740.10', 'Completed', 'loan', 'loan_received', 3, 3, NULL, 'accounts_manager', '2026-05-29 20:03:39');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (17, 'TRX-20260529-0017', '2026-05-29', 'Loan Received', NULL, 'LOAN-20260529-0004', '500000.00', 'credit', '0.00', '500000.00', 'Bank', '-326305362.76', '-325805362.76', '-327732102.86', '1926740.10', 'Completed', 'loan', 'loan_received', 4, 4, NULL, 'accounts_manager', '2026-05-29 20:03:41');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (18, 'TRX-20260529-0018', '2026-05-29', 'Loan Received', NULL, 'LOAN-20260529-0005', '999999999999.99', 'credit', '0.00', '999999999999.99', 'Bank', '-325805362.76', '999674194637.23', '-327732102.86', '999999999999.99', 'Completed', 'loan', 'loan_received', 5, 5, NULL, 'accounts_manager', '2026-05-29 20:04:24');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (19, 'TRX-20260529-0019', '2026-05-29', 'Loan Received', 'osfosdodf', 'LOAN-20260529-0006', '100000.00', 'credit', '0.00', '100000.00', 'Bank', '999674194637.23', '999674294637.23', '-327732102.86', '999999999999.99', 'Completed', 'loan', 'loan_received', 6, 6, NULL, 'accounts_manager', '2026-05-29 20:14:33');
INSERT INTO `accounts_treasury_ledger` (`id`, `tx_code`, `tx_date`, `tx_type`, `party`, `reference_no`, `amount`, `direction`, `cash_amount`, `bank_amount`, `payment_mode`, `balance_before`, `balance_after`, `cash_balance_after`, `bank_balance_after`, `tx_status`, `source_module`, `source_type`, `source_id`, `loan_id`, `remarks`, `created_by`, `created_at`) VALUES (20, 'TRX-20260529-0020', '2026-05-29', 'Loan Received', 'gdfgd', 'LOAN-20260529-0007', '3333333333333.00', 'credit', '3333333333333.00', '0.00', 'Cash', '999672267897.13', '4333005601230.10', '3333005601230.10', '999999999999.99', 'Completed', 'loan', 'loan_received', 7, 7, 'Loan LOAN-20260529-0007', 'accounts_manager', '2026-05-29 20:23:19');

DROP TABLE IF EXISTS `accounts_treasury_opening`;
CREATE TABLE `accounts_treasury_opening` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `opening_cash` decimal(18,2) NOT NULL DEFAULT 0.00,
  `opening_bank` decimal(18,2) NOT NULL DEFAULT 0.00,
  `effective_date` date NOT NULL,
  `entered_by` varchar(120) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts_treasury_opening` (`id`, `opening_cash`, `opening_bank`, `effective_date`, `entered_by`, `is_locked`, `created_at`, `updated_at`) VALUES (1, '10000000.00', '0.00', '2026-05-29', 'accounts_manager', 1, '2026-05-29 20:00:25', '2026-05-29 20:00:25');

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `shift` enum('Morning','Evening','Night') NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Present',
  `remarks` varchar(255) DEFAULT NULL,
  `linked_leave_id` int(11) DEFAULT NULL,
  `punch_in_time` datetime DEFAULT NULL,
  `punch_out_time` datetime DEFAULT NULL,
  `total_hours` decimal(8,2) DEFAULT NULL,
  `overtime_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `is_early_exit` tinyint(1) NOT NULL DEFAULT 0,
  `is_emergency_duty` tinyint(1) NOT NULL DEFAULT 0,
  `needs_verification` tinyint(1) NOT NULL DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance` (`employee_id`,`attendance_date`),
  KEY `idx_att_date` (`attendance_date`),
  KEY `idx_attendance_linked_leave` (`linked_leave_id`),
  CONSTRAINT `fk_att_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=652 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (490, 111, '2026-05-22', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '1.50', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (491, 112, '2026-05-22', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (492, 113, '2026-05-22', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-22 14:00:00', '2026-05-22 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (493, 114, '2026-05-22', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-22 09:00:00', '2026-05-22 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (494, 115, '2026-05-22', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (495, 117, '2026-05-22', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (496, 119, '2026-05-22', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (497, 120, '2026-05-22', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (498, 111, '2026-05-21', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '1.50', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (499, 112, '2026-05-21', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (500, 113, '2026-05-21', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-21 14:00:00', '2026-05-21 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (501, 114, '2026-05-21', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-21 09:00:00', '2026-05-21 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (502, 115, '2026-05-21', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (503, 117, '2026-05-21', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (504, 119, '2026-05-21', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (505, 120, '2026-05-21', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (506, 111, '2026-05-20', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '1.50', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (507, 112, '2026-05-20', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (508, 113, '2026-05-20', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-20 14:00:00', '2026-05-20 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (509, 114, '2026-05-20', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-20 09:00:00', '2026-05-20 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (510, 115, '2026-05-20', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (511, 117, '2026-05-20', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (512, 119, '2026-05-20', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (513, 120, '2026-05-20', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (514, 111, '2026-05-19', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '1.50', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (515, 112, '2026-05-19', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (516, 113, '2026-05-19', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-19 14:00:00', '2026-05-19 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (517, 114, '2026-05-19', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-19 09:00:00', '2026-05-19 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (518, 115, '2026-05-19', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (519, 117, '2026-05-19', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (520, 119, '2026-05-19', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (521, 120, '2026-05-19', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (522, 111, '2026-05-18', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (523, 112, '2026-05-18', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (524, 113, '2026-05-18', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-18 14:00:00', '2026-05-18 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (525, 114, '2026-05-18', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-18 09:00:00', '2026-05-18 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (526, 115, '2026-05-18', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (527, 117, '2026-05-18', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (528, 119, '2026-05-18', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (529, 120, '2026-05-18', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (530, 111, '2026-05-17', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (531, 112, '2026-05-17', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (532, 113, '2026-05-17', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (533, 114, '2026-05-17', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (534, 115, '2026-05-17', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (535, 117, '2026-05-17', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (536, 119, '2026-05-17', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (537, 120, '2026-05-17', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (538, 111, '2026-05-16', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (539, 112, '2026-05-16', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (540, 113, '2026-05-16', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-16 14:00:00', '2026-05-16 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (541, 114, '2026-05-16', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-16 09:00:00', '2026-05-16 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (542, 115, '2026-05-16', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (543, 117, '2026-05-16', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (544, 119, '2026-05-16', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (545, 120, '2026-05-16', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (546, 111, '2026-05-15', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (547, 112, '2026-05-15', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (548, 113, '2026-05-15', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-15 14:00:00', '2026-05-15 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (549, 114, '2026-05-15', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-15 09:00:00', '2026-05-15 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (550, 115, '2026-05-15', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (551, 117, '2026-05-15', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (552, 119, '2026-05-15', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (553, 120, '2026-05-15', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (554, 111, '2026-05-14', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (555, 112, '2026-05-14', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (556, 113, '2026-05-14', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-14 14:00:00', '2026-05-14 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (557, 114, '2026-05-14', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-14 09:00:00', '2026-05-14 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (558, 115, '2026-05-14', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (559, 117, '2026-05-14', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (560, 119, '2026-05-14', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (561, 120, '2026-05-14', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (562, 111, '2026-05-13', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (563, 112, '2026-05-13', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (564, 113, '2026-05-13', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-13 14:00:00', '2026-05-13 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (565, 114, '2026-05-13', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-13 09:00:00', '2026-05-13 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (566, 115, '2026-05-13', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (567, 117, '2026-05-13', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (568, 119, '2026-05-13', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (569, 120, '2026-05-13', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (570, 111, '2026-05-12', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (571, 112, '2026-05-12', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (572, 113, '2026-05-12', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-12 14:00:00', '2026-05-12 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (573, 114, '2026-05-12', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-12 09:00:00', '2026-05-12 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (574, 115, '2026-05-12', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (575, 117, '2026-05-12', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (576, 119, '2026-05-12', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (577, 120, '2026-05-12', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (578, 111, '2026-05-11', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (579, 112, '2026-05-11', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (580, 113, '2026-05-11', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-11 14:00:00', '2026-05-11 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (581, 114, '2026-05-11', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-11 09:00:00', '2026-05-11 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (582, 115, '2026-05-11', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (583, 117, '2026-05-11', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (584, 119, '2026-05-11', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (585, 120, '2026-05-11', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (586, 111, '2026-05-10', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (587, 112, '2026-05-10', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (588, 113, '2026-05-10', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (589, 114, '2026-05-10', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (590, 115, '2026-05-10', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (591, 117, '2026-05-10', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (592, 119, '2026-05-10', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (593, 120, '2026-05-10', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (594, 111, '2026-05-09', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (595, 112, '2026-05-09', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (596, 113, '2026-05-09', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-09 14:00:00', '2026-05-09 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (597, 114, '2026-05-09', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-09 09:00:00', '2026-05-09 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (598, 115, '2026-05-09', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (599, 117, '2026-05-09', 'Night', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (600, 119, '2026-05-09', 'Morning', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (601, 120, '2026-05-09', 'Evening', 'Present', NULL, NULL, NULL, NULL, '8.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-23 19:14:21');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (602, 114, '2026-05-01', 'Morning', 'Absent', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, NULL, NULL, '0.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (603, 114, '2026-05-02', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-02 09:00:00', '2026-05-02 13:00:00', '4.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (604, 114, '2026-05-04', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-04 09:00:00', '2026-05-04 13:00:00', '4.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (605, 114, '2026-05-05', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-05 09:25:00', '2026-05-05 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (606, 114, '2026-05-06', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-06 09:25:00', '2026-05-06 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (607, 114, '2026-05-07', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-07 09:25:00', '2026-05-07 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (608, 114, '2026-05-08', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-08 09:00:00', '2026-05-08 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (621, 114, '2026-05-23', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-23 09:00:00', '2026-05-23 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (622, 114, '2026-05-25', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-25 09:00:00', '2026-05-25 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (623, 114, '2026-05-26', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-26 09:00:00', '2026-05-26 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (624, 114, '2026-05-27', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-27 09:00:00', '2026-05-27 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (625, 114, '2026-05-28', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-28 09:00:00', '2026-05-28 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (626, 114, '2026-05-29', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-29 09:00:00', '2026-05-29 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 17:04:55');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (627, 113, '2026-05-01', 'Evening', 'Absent', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, NULL, NULL, '0.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (628, 113, '2026-05-02', 'Evening', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-02 14:00:00', '2026-05-03 13:00:00', '4.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (629, 113, '2026-05-04', 'Evening', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-04 14:00:00', '2026-05-05 13:00:00', '4.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (630, 113, '2026-05-05', 'Evening', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-05 09:25:00', '2026-05-05 22:00:00', '8.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (631, 113, '2026-05-06', 'Evening', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-06 09:25:00', '2026-05-06 22:00:00', '8.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (632, 113, '2026-05-07', 'Evening', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-07 09:25:00', '2026-05-07 22:00:00', '8.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (633, 113, '2026-05-08', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-08 14:00:00', '2026-05-08 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (646, 113, '2026-05-23', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-23 14:00:00', '2026-05-23 22:00:00', '8.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (647, 113, '2026-05-25', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-25 14:00:00', '2026-05-25 22:00:00', '10.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (648, 113, '2026-05-26', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-26 14:00:00', '2026-05-26 22:00:00', '10.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (649, 113, '2026-05-27', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-27 14:00:00', '2026-05-27 22:00:00', '10.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (650, 113, '2026-05-28', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-28 14:00:00', '2026-05-28 22:00:00', '10.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (651, 113, '2026-05-29', 'Evening', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-29 14:00:00', '2026-05-29 22:00:00', '10.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-29 18:36:30');

DROP TABLE IF EXISTS `building_batches`;
CREATE TABLE `building_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `mixing_batch_id` int(11) DEFAULT NULL,
  `batch_code` varchar(40) NOT NULL,
  `produced_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `machine_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `production_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'In Progress',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `idx_bld_order` (`order_id`),
  KEY `idx_bld_mix` (`mixing_batch_id`),
  CONSTRAINT `fk_bld_mix` FOREIGN KEY (`mixing_batch_id`) REFERENCES `mixing_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bld_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `building_entries`;
CREATE TABLE `building_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `production_date` date NOT NULL,
  `shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `machine_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `tyre_type` varchar(120) NOT NULL,
  `produced_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bld_entry_date` (`production_date`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (61, '2026-05-23', 'Evening', 76, 114, 'PCR Car', 180, 4, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (62, '2026-05-22', 'Night', 77, 115, 'PCR SUV', 186, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (63, '2026-05-21', 'Morning', 79, 116, 'TBR Truck', 192, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (64, '2026-05-20', 'Evening', 76, 114, 'Farm / OTR', 198, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (65, '2026-05-19', 'Night', 77, 115, 'Two Wheeler', 204, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (66, '2026-05-18', 'Morning', 79, 116, 'PCR Car', 210, 4, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (67, '2026-05-17', 'Evening', 76, 114, 'PCR SUV', 216, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (68, '2026-05-16', 'Night', 77, 115, 'TBR Truck', 222, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (69, '2026-05-15', 'Morning', 79, 116, 'Farm / OTR', 228, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (70, '2026-05-14', 'Evening', 76, 114, 'Two Wheeler', 234, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (71, '2026-05-13', 'Night', 77, 115, 'PCR Car', 240, 4, 'Demo building run [demo]', '2026-05-23 19:14:21');
INSERT INTO `building_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (72, '2026-05-12', 'Morning', 79, 116, 'PCR SUV', 246, 0, 'Demo building run [demo]', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `company_holidays`;
CREATE TABLE `company_holidays` (
  `holiday_date` date NOT NULL,
  `label` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `curing_batches`;
CREATE TABLE `curing_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `building_batch_id` int(11) DEFAULT NULL,
  `batch_code` varchar(40) NOT NULL,
  `cured_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `cycle_time_min` int(11) NOT NULL DEFAULT 0,
  `downtime_min` int(11) NOT NULL DEFAULT 0,
  `machine_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `production_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'In Progress',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `idx_cur_order` (`order_id`),
  KEY `idx_cur_bld` (`building_batch_id`),
  CONSTRAINT `fk_cur_bld` FOREIGN KEY (`building_batch_id`) REFERENCES `building_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cur_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `curing_entries`;
CREATE TABLE `curing_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_code` varchar(40) DEFAULT NULL,
  `production_date` date NOT NULL,
  `shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `machine_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `tyre_type` varchar(120) NOT NULL DEFAULT 'Tyre',
  `produced_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `downtime_minutes` int(11) NOT NULL DEFAULT 0,
  `remarks` varchar(500) DEFAULT NULL,
  `qc_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `qc_inspection_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cur_entry_date` (`production_date`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (1, 'CUR-20260522-0001', '2026-05-22', 'Morning', 1, 1, 'PCR Car', 10, 23, 40, NULL, 'Inspecting', NULL, '2026-05-22 17:27:47');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (63, 'CUR-20260523-0063', '2026-05-23', 'Morning', 81, 117, 'PCR Car', 175, 3, 25, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (64, 'CUR-20260522-0064', '2026-05-22', 'Evening', 81, 118, 'PCR SUV', 180, 0, 0, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (65, 'CUR-20260521-0065', '2026-05-21', 'Night', 81, 117, 'TBR Truck', 185, 0, 0, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (66, 'CUR-20260520-0066', '2026-05-20', 'Morning', 81, 118, 'Farm / OTR', 190, 0, 25, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (67, 'CUR-20260519-0067', '2026-05-19', 'Evening', 81, 117, 'Two Wheeler', 195, 0, 0, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (68, 'CUR-20260518-0068', '2026-05-18', 'Night', 81, 118, 'PCR Car', 200, 0, 0, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (69, 'CUR-20260517-0069', '2026-05-17', 'Morning', 81, 117, 'PCR SUV', 205, 3, 25, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (70, 'CUR-20260516-0070', '2026-05-16', 'Evening', 81, 118, 'TBR Truck', 210, 0, 0, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (71, 'CUR-20260515-0071', '2026-05-15', 'Night', 81, 117, 'Farm / OTR', 215, 0, 0, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (72, 'CUR-20260514-0072', '2026-05-14', 'Morning', 81, 118, 'Two Wheeler', 220, 0, 25, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (73, 'CUR-20260513-0073', '2026-05-13', 'Evening', 81, 117, 'PCR Car', 225, 0, 0, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');
INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (74, 'CUR-20260512-0074', '2026-05-12', 'Night', 81, 118, 'PCR SUV', 230, 0, 0, 'Demo curing batch [demo]', 'Pending', NULL, '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `defect_logs`;
CREATE TABLE `defect_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quality_check_id` int(11) NOT NULL,
  `production_id` int(11) NOT NULL,
  `failed_qty` int(11) NOT NULL,
  `defect_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_defect_quality` (`quality_check_id`),
  KEY `fk_defect_production` (`production_id`),
  CONSTRAINT `fk_defect_production` FOREIGN KEY (`production_id`) REFERENCES `production` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_defect_quality` FOREIGN KEY (`quality_check_id`) REFERENCES `quality_checks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `department_categories`;
CREATE TABLE `department_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(120) NOT NULL,
  `category_code` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_cat_code` (`category_code`)
) ENGINE=InnoDB AUTO_INCREMENT=22501 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `department_categories` (`id`, `category_name`, `category_code`, `status`, `created_at`) VALUES (1, 'Core Production', 'CORE_PROD', 'active', '2026-05-14 18:28:16');
INSERT INTO `department_categories` (`id`, `category_name`, `category_code`, `status`, `created_at`) VALUES (2, 'Quality & Safety', 'QUAL_SAFETY', 'active', '2026-05-14 18:28:16');
INSERT INTO `department_categories` (`id`, `category_name`, `category_code`, `status`, `created_at`) VALUES (3, 'Engineering & Technical', 'ENG_TECH', 'active', '2026-05-14 18:28:16');
INSERT INTO `department_categories` (`id`, `category_name`, `category_code`, `status`, `created_at`) VALUES (4, 'Logistics & Inventory', 'LOG_INV', 'active', '2026-05-14 18:28:16');
INSERT INTO `department_categories` (`id`, `category_name`, `category_code`, `status`, `created_at`) VALUES (5, 'Administrative & Office', 'ADMIN_OFF', 'active', '2026-05-14 18:28:16');
INSERT INTO `department_categories` (`id`, `category_name`, `category_code`, `status`, `created_at`) VALUES (6, 'Business & Management', 'BIZ_MGMT', 'active', '2026-05-14 18:28:16');

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `department_name` varchar(160) NOT NULL,
  `department_short_name` varchar(80) DEFAULT NULL,
  `department_code` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `head_employee_id` int(11) DEFAULT NULL,
  `min_staff_required` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_code` (`department_code`),
  UNIQUE KEY `uk_dept_cat_name` (`category_id`,`department_name`),
  KEY `idx_dept_category` (`category_id`),
  CONSTRAINT `fk_dept_category` FOREIGN KEY (`category_id`) REFERENCES `department_categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=75001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (1, 1, 'Production Planning & Control (PPC)', 'PPC', 'DEPT_PPC', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (2, 1, 'Raw Materials & Inventory Management', 'Raw Materials', 'DEPT_RAW_MAT', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (3, 1, 'Mixing & Compounding', 'Mixing', 'DEPT_MIXING', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (4, 1, 'Component Preparation (Extrusion & Calendering)', 'Comp. Prep.', 'DEPT_COMP_PREP', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (5, 1, 'Tire Building / Product Assembly', 'Tire Building', 'DEPT_TIRE_BUILD', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (6, 1, 'Curing & Vulcanization', 'Curing', 'DEPT_CURING', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (7, 2, 'Quality Assurance & Testing (QA/QC)', 'QA/QC', 'DEPT_QA_QC', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (8, 2, 'Environment Health & Safety (EHS)', 'EHS', 'DEPT_EHS', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (9, 3, 'Plant Maintenance & Engineering', 'Maintenance', 'DEPT_MAINT', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (10, 3, 'IT Support', 'IT', 'DEPT_IT', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (11, 4, 'Logistics & Dispatch', 'Dispatch', 'DEPT_LOG_DISP', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (12, 4, 'Warehouse Management', 'Warehouse', 'DEPT_WH', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (13, 4, 'Store Management', 'Store', 'DEPT_STORE', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (14, 5, 'Human Resources (HR)', 'HR', 'DEPT_HR', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (15, 5, 'Administration', 'Administration', 'DEPT_ADMIN', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (16, 5, 'Accounts & Finance', 'Accounts', 'DEPT_ACC', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (17, 5, 'Purchase & Procurement', 'Purchase', 'DEPT_PUR', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (18, 5, 'Unclassified (Legacy)', 'Legacy', 'DEPT_UNCLASS', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (19, 6, 'Sales & Marketing', 'Sales', 'DEPT_SALES', 'active', NULL, NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `head_employee_id`, `min_staff_required`, `created_at`) VALUES (20, 6, 'Management', 'Management', 'DEPT_MGMT', 'active', NULL, NULL, '2026-05-14 18:28:16');

DROP TABLE IF EXISTS `designations`;
CREATE TABLE `designations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `designation_name` varchar(120) NOT NULL,
  `designation_code` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_desig_dept_code` (`department_id`,`designation_code`),
  KEY `idx_desig_department` (`department_id`),
  CONSTRAINT `fk_desig_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=240001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (1, 1, 'PPC Executive', 'DES_PPC_EXEC', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (2, 1, 'PPC Manager', 'DES_PPC_MGR', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (3, 1, 'Production Planner', 'DES_PPC_PLNR', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (4, 1, 'Assistant Planner', 'DES_PPC_ASST', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (5, 2, 'Raw Material Officer', 'DES_RM_OFF', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (6, 2, 'Store Keeper', 'DES_RM_SK', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (7, 2, 'Inventory Analyst', 'DES_RM_ANL', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (8, 3, 'Mixing Operator', 'DES_MIX_OP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (9, 3, 'Compounding Technician', 'DES_MIX_TECH', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (10, 3, 'Shift Supervisor', 'DES_MIX_SUP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (11, 4, 'Extrusion Operator', 'DES_EXT_OP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (12, 4, 'Calendering Operator', 'DES_CAL_OP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (13, 4, 'Process Technician', 'DES_COMP_TECH', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (14, 5, 'Tire Builder', 'DES_TB_OP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (15, 5, 'Building Line Supervisor', 'DES_TB_SUP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (16, 5, 'Assembly Technician', 'DES_TB_TECH', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (17, 6, 'Curing Operator', 'DES_CUR_OP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (18, 6, 'Vulcanization Technician', 'DES_CUR_TECH', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (19, 6, 'Curing Supervisor', 'DES_CUR_SUP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (20, 7, 'QA Inspector', 'DES_QA_INSP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (21, 7, 'Quality Engineer', 'DES_QA_ENG', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (22, 7, 'Testing Operator', 'DES_QA_TEST', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (23, 7, 'QA Manager', 'DES_QA_MGR', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (24, 8, 'EHS Officer', 'DES_EHS_OFF', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (25, 8, 'Safety Supervisor', 'DES_EHS_SUP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (26, 8, 'Industrial Hygienist', 'DES_EHS_HYG', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (27, 9, 'Maintenance Engineer', 'DES_MT_ENG', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (28, 9, 'Electrical Technician', 'DES_MT_ELEC', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (29, 9, 'Machine Operator', 'DES_MT_MACH', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (30, 9, 'Millwright', 'DES_MT_MILL', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (31, 10, 'IT Support Engineer', 'DES_IT_ENG', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (32, 10, 'System Administrator', 'DES_IT_SA', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (33, 10, 'Helpdesk Executive', 'DES_IT_HD', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (34, 11, 'Dispatch Executive', 'DES_LOG_DISP_EX', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (35, 11, 'Logistics Coordinator', 'DES_LOG_COORD', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (36, 11, 'Fleet Supervisor', 'DES_LOG_FLEET', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (37, 12, 'Warehouse Supervisor', 'DES_WH_SUP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (38, 12, 'Warehouse Associate', 'DES_WH_ASC', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (39, 12, 'Forklift Operator', 'DES_WH_FL', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (40, 13, 'Store Incharge', 'DES_ST_INC', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (41, 13, 'Store Assistant', 'DES_ST_ASST', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (42, 13, 'Issuing Clerk', 'DES_ST_ISS', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (43, 14, 'HR Executive', 'DES_HR_EX', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (44, 14, 'HR Manager', 'DES_HR_MGR', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (45, 14, 'Talent Acquisition Specialist', 'DES_HR_TA', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (46, 15, 'Admin Executive', 'DES_AD_EX', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (47, 15, 'Receptionist', 'DES_AD_REC', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (48, 15, 'Office Assistant', 'DES_AD_ASST', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (49, 15, 'Peon', 'DES_AD_PEON', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (50, 16, 'Accountant', 'DES_ACC_ACT', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (51, 16, 'Finance Executive', 'DES_ACC_FIN', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (52, 16, 'Payroll Officer', 'DES_ACC_PAY', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (53, 17, 'Purchase Executive', 'DES_PUR_EX', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (54, 17, 'Procurement Officer', 'DES_PUR_PR', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (55, 17, 'Vendor Coordinator', 'DES_PUR_VC', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (56, 18, 'General Staff', 'DES_UN_GEN', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (57, 18, 'To Be Assigned', 'DES_UN_TBA', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (58, 19, 'Sales Executive', 'DES_SAL_EX', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (59, 19, 'Marketing Coordinator', 'DES_SAL_MKT', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (60, 19, 'Key Account Manager', 'DES_SAL_KAM', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (61, 20, 'Plant Manager', 'DES_MGT_PLANT', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (62, 20, 'Operations Manager', 'DES_MGT_OPS', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (63, 20, 'Supervisor', 'DES_MGT_SUP', 'active', '2026-05-14 18:28:16');
INSERT INTO `designations` (`id`, `department_id`, `designation_name`, `designation_code`, `status`, `created_at`) VALUES (64, 20, 'General Manager', 'DES_MGT_GM', 'active', '2026-05-14 18:28:16');

DROP TABLE IF EXISTS `dispatch`;
CREATE TABLE `dispatch` (
  `inventory_id` int(11) DEFAULT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispatch_code` varchar(40) DEFAULT NULL,
  `order_no` varchar(40) NOT NULL,
  `sales_order_id` int(11) DEFAULT NULL,
  `sales_order_item_id` int(11) DEFAULT NULL,
  `sales_customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(120) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `tyre_type` varchar(120) DEFAULT NULL,
  `invoice_no` varchar(40) NOT NULL,
  `vehicle_no` varchar(40) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `driver_name` varchar(150) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `transport_company` varchar(150) DEFAULT NULL,
  `transport_company_id` int(11) DEFAULT NULL,
  `dispatch_date` date NOT NULL,
  `qty` int(11) NOT NULL,
  `gross_weight_kg` decimal(12,2) DEFAULT NULL,
  `tare_weight_kg` decimal(12,2) DEFAULT NULL,
  `net_weight_kg` decimal(12,2) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `status` enum('Pending','Dispatched','Delivered') NOT NULL DEFAULT 'Pending',
  `stock_deducted` tinyint(1) NOT NULL DEFAULT 0,
  `dispatch_status` enum('Created','In Transit','Delivered') DEFAULT 'Created',
  `tracking_no` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_dispatch_status` (`dispatch_status`),
  KEY `idx_dispatch_date` (`dispatch_date`),
  KEY `idx_dispatch_sales_order` (`sales_order_id`),
  KEY `idx_dispatch_sales_customer` (`sales_customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 3, 'DSP-20260523-001', 'ORD-DEMO-10000', NULL, NULL, NULL, 'Metro Tyre Traders [demo]', 54, 'PCR Car', 'INV-20260523-7862', 'DEMO-MH12AB1234', NULL, 'Mohit Singh', 54, 'North Star Transport [demo]', 29, '2026-05-23', 20, '1200.00', '420.00', '780.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 4, 'DSP-20260522-002', 'ORD-DEMO-10001', NULL, NULL, NULL, 'Southern Auto Mart [demo]', 55, 'PCR SUV', 'INV-20260522-7863', 'DEMO-GJ01BT5678', NULL, 'Suresh Patel', 55, 'Western Freight Lines [demo]', 31, '2026-05-22', 23, '1240.00', '422.00', '818.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 5, 'DSP-20260521-003', 'ORD-DEMO-10002', NULL, NULL, NULL, 'Bharat Wheels [demo]', 56, 'TBR Truck', 'INV-20260521-7864', 'DEMO-PB10AX9876', NULL, 'Ravi Kumar', 56, 'Punjab Logistics Express [demo]', 30, '2026-05-21', 26, '1280.00', '424.00', '856.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 6, 'DSP-20260520-004', 'ORD-DEMO-10003', NULL, NULL, NULL, 'Punjab Tyres Distributor [demo]', 57, 'Farm / OTR', 'INV-20260520-7865', 'DEMO-HR26CD4521', NULL, 'Ajay Thakur', 57, 'North Star Transport [demo]', 29, '2026-05-20', 29, '1320.00', '426.00', '894.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 7, 'DSP-20260519-005', 'ORD-DEMO-10004', NULL, NULL, NULL, 'North Highway Fleet [demo]', 58, 'Two Wheeler', 'INV-20260519-7866', 'DEMO-DL01EF3344', NULL, 'Vikram Joshi', 58, 'Bharat Road Carriers [demo]', 33, '2026-05-19', 32, '1360.00', '428.00', '932.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 8, 'DSP-20260518-006', 'ORD-DEMO-10005', NULL, NULL, NULL, 'Western Rubber House [demo]', 59, 'PCR Car', 'INV-20260518-7867', 'DEMO-UP32GH7788', NULL, 'Deepak Kumar', 59, 'Southern Hauliers [demo]', 32, '2026-05-18', 35, '1400.00', '430.00', '970.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 9, 'DSP-20260517-007', 'ORD-DEMO-10006', NULL, NULL, NULL, 'Eastern Tyre Hub [demo]', 60, 'PCR SUV', 'INV-20260517-7868', 'DEMO-RJ14JK2233', NULL, 'Sanjay Mehta', 60, 'Western Freight Lines [demo]', 31, '2026-05-17', 38, '1440.00', '432.00', '1008.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 10, 'DSP-20260516-008', 'ORD-DEMO-10007', NULL, NULL, NULL, 'Deccan Auto Agencies [demo]', 61, 'TBR Truck', 'INV-20260516-7869', 'DEMO-PB65LM8899', NULL, 'Harpreet Singh', 61, 'Punjab Logistics Express [demo]', 30, '2026-05-16', 41, '1480.00', '434.00', '1046.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 11, 'DSP-20260515-009', 'ORD-DEMO-10008', NULL, NULL, NULL, 'Kerala Tyre Mart [demo]', 62, 'Farm / OTR', 'INV-20260515-7870', 'DEMO-CH01NO5566', NULL, 'Karan Malhotra', 62, 'Bharat Road Carriers [demo]', 33, '2026-05-15', 44, '1520.00', '436.00', '1084.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 12, 'DSP-20260514-010', 'ORD-DEMO-10009', NULL, NULL, NULL, 'Rajasthan Wheel Centre [demo]', 63, 'Two Wheeler', 'INV-20260514-7871', 'DEMO-MP09PQ1122', NULL, 'Rohit Sharma', 63, 'Southern Hauliers [demo]', 32, '2026-05-14', 47, '1560.00', '438.00', '1122.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 13, 'DSP-20260513-011', 'ORD-DEMO-10010', NULL, NULL, NULL, 'Metro Tyre Traders [demo]', 54, 'PCR Car', 'INV-20260513-7872', 'DEMO-MH12AB1234', NULL, 'Mohit Singh', 54, 'North Star Transport [demo]', 29, '2026-05-13', 50, '1600.00', '440.00', '1160.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 14, 'DSP-20260512-012', 'ORD-DEMO-10011', NULL, NULL, NULL, 'Southern Auto Mart [demo]', 55, 'PCR SUV', 'INV-20260512-7873', 'DEMO-GJ01BT5678', NULL, 'Suresh Patel', 55, 'Western Freight Lines [demo]', 31, '2026-05-12', 53, '1640.00', '442.00', '1198.00', 'Demo dispatch [demo]', 'Delivered', 1, 'Created', NULL, '2026-05-23 19:14:21');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 15, 'DSP-20260525-001', 'ORD-260525-001', 7, 7, 3, 'Bharat Wheels', NULL, 'TBR Truck', 'INV-20260525-7114', 'DEMO-HR26CD4521', 58, 'Ajay Thakur', 57, 'North Star Transport [demo]', 29, '2026-05-25', 300, '500.00', '450.00', '50.00', NULL, 'Delivered', 1, 'Delivered', NULL, '2026-05-25 17:10:05');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 16, 'DSP-20260525-002', 'ORD-260525-002', 6, 6, 4, 'Punjab Tyres Distributor', NULL, 'PCR SUV', 'INV-20260525-5722', 'DEMO-UP32GH7788', 60, 'Deepak Kumar', 59, 'Southern Hauliers [demo]', 32, '2026-05-25', 1, '780.00', '699.99', '80.01', NULL, 'Delivered', 1, 'Delivered', NULL, '2026-05-25 17:54:43');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `sales_order_id`, `sales_order_item_id`, `sales_customer_id`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 17, 'DSP-20260528-001', 'ORD-260528-001', 8, 8, 4, 'Punjab Tyres Distributor', NULL, 'PCR Car', 'INV-20260528-1406', 'DEMO-PB65LM8899', 62, 'Harpreet Singh', 61, 'Punjab Logistics Express [demo]', 30, '2026-05-28', 87, '980.00', '699.78', '280.22', NULL, 'Delivered', 1, 'Delivered', NULL, '2026-05-28 18:19:16');

DROP TABLE IF EXISTS `dispatch_customers`;
CREATE TABLE `dispatch_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(150) NOT NULL,
  `company` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `gst_number` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dcust_name` (`customer_name`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (1, 'Metro Tyre Traders', 'Metro Tyre Traders Pvt Ltd', '9876500001', NULL, NULL, 'Mumbai', 'Maharashtra', 'Active', '2026-05-22 18:50:37');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (2, 'North Highway Fleet', 'North Highway Fleet Services', '9876500002', NULL, NULL, 'Delhi', 'Delhi', 'Active', '2026-05-22 18:50:37');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (3, 'Southern Auto Mart', 'Southern Auto Mart', '9876500003', NULL, NULL, 'Chennai', 'Tamil Nadu', 'Active', '2026-05-22 18:50:37');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (54, 'Metro Tyre Traders [demo]', 'Metro Tyre Traders Pvt Ltd', '9899101000', NULL, NULL, 'Mumbai', 'Maharashtra', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (55, 'Southern Auto Mart [demo]', 'Southern Auto Mart', '9899101001', NULL, NULL, 'Chennai', 'Tamil Nadu', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (56, 'Bharat Wheels [demo]', 'Bharat Wheels LLP', '9899101002', NULL, NULL, 'Pune', 'Maharashtra', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (57, 'Punjab Tyres Distributor [demo]', 'Punjab Tyres Distributor', '9899101003', NULL, NULL, 'Ludhiana', 'Punjab', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (58, 'North Highway Fleet [demo]', 'North Highway Fleet', '9899101004', NULL, NULL, 'Delhi', 'Delhi', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (59, 'Western Rubber House [demo]', 'Western Rubber House', '9899101005', NULL, NULL, 'Ahmedabad', 'Gujarat', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (60, 'Eastern Tyre Hub [demo]', 'Eastern Tyre Hub', '9899101006', NULL, NULL, 'Kolkata', 'West Bengal', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (61, 'Deccan Auto Agencies [demo]', 'Deccan Auto Agencies', '9899101007', NULL, NULL, 'Hyderabad', 'Telangana', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (62, 'Kerala Tyre Mart [demo]', 'Kerala Tyre Mart', '9899101008', NULL, NULL, 'Kochi', 'Kerala', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (63, 'Rajasthan Wheel Centre [demo]', 'Rajasthan Wheel Centre', '9899101009', NULL, NULL, 'Jaipur', 'Rajasthan', 'Active', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `dispatch_drivers`;
CREATE TABLE `dispatch_drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `license_number` varchar(60) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `vehicle_no` varchar(40) DEFAULT NULL,
  `transport_company_id` int(11) DEFAULT NULL,
  `transport_company` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ddriver_name` (`driver_name`),
  KEY `idx_ddriver_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (1, 'Ravi Kumar', '9876510001', 'DL-09-2018-0012345', 1, 'PB10AX1234', 1, 'Punjab Logistics', 'Ludhiana, Punjab', 'Active', '2026-05-22 19:04:30');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (2, 'Suresh Patel', '9876510002', 'GJ-12-2019-0098765', 2, 'GJ01BT5678', 2, 'Western Freight Lines', 'Ahmedabad, Gujarat', 'Active', '2026-05-22 19:04:30');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (3, 'Mohit Singh', '9876510003', 'HR-05-2020-0045678', 3, 'HR26CD9012', 3, 'North Star Transport', 'Gurgaon, Haryana', 'Active', '2026-05-22 19:04:30');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (54, 'Mohit Singh', '9812500300', 'DL-DEMO-0001', NULL, 'DEMO-MH12AB1234', 29, 'North Star Transport [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (55, 'Suresh Patel', '9812500301', 'DL-DEMO-0002', NULL, 'DEMO-GJ01BT5678', 31, 'Western Freight Lines [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (56, 'Ravi Kumar', '9812500302', 'DL-DEMO-0003', NULL, 'DEMO-PB10AX9876', 30, 'Punjab Logistics Express [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (57, 'Ajay Thakur', '9812500303', 'DL-DEMO-0004', NULL, 'DEMO-HR26CD4521', 29, 'North Star Transport [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (58, 'Vikram Joshi', '9812500304', 'DL-DEMO-0005', NULL, 'DEMO-DL01EF3344', 33, 'Bharat Road Carriers [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (59, 'Deepak Kumar', '9812500305', 'DL-DEMO-0006', NULL, 'DEMO-UP32GH7788', 32, 'Southern Hauliers [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (60, 'Sanjay Mehta', '9812500306', 'DL-DEMO-0007', NULL, 'DEMO-RJ14JK2233', 31, 'Western Freight Lines [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (61, 'Harpreet Singh', '9812500307', 'DL-DEMO-0008', NULL, 'DEMO-PB65LM8899', 30, 'Punjab Logistics Express [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (62, 'Karan Malhotra', '9812500308', 'DL-DEMO-0009', NULL, 'DEMO-CH01NO5566', 33, 'Bharat Road Carriers [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (63, 'Rohit Sharma', '9812500309', 'DL-DEMO-0010', NULL, 'DEMO-MP09PQ1122', 32, 'Southern Hauliers [demo]', 'Driver quarters, plant township', 'Active', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `dispatch_transport_companies`;
CREATE TABLE `dispatch_transport_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `gst_number` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transport_name` (`company_name`),
  KEY `idx_transport_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (1, 'Punjab Logistics', 'Harpreet Singh', '9876520001', '03AABCP1234F1Z5', 'Ludhiana, Punjab', 'Active', '2026-05-22 19:15:32');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (2, 'Western Freight Lines', 'Amit Shah', '9876520002', '24AABCW5678G1Z9', 'Ahmedabad, Gujarat', 'Active', '2026-05-22 19:15:32');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (3, 'North Star Transport', 'Vikram Mehta', '9876520003', '06AABCN9012H1Z3', 'Gurgaon, Haryana', 'Active', '2026-05-22 19:15:32');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (29, 'North Star Transport [demo]', 'Logistics Head 1', '9812400200', '29AABCT1000Z1', 'Transport Nagar, India', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (30, 'Punjab Logistics Express [demo]', 'Logistics Head 2', '9812400201', '29AABCT1001Z1', 'Transport Nagar, India', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (31, 'Western Freight Lines [demo]', 'Logistics Head 3', '9812400202', '29AABCT1002Z1', 'Transport Nagar, India', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (32, 'Southern Hauliers [demo]', 'Logistics Head 4', '9812400203', '29AABCT1003Z1', 'Transport Nagar, India', 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (33, 'Bharat Road Carriers [demo]', 'Logistics Head 5', '9812400204', '29AABCT1004Z1', 'Transport Nagar, India', 'Active', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `dispatch_vehicles`;
CREATE TABLE `dispatch_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_number` varchar(40) NOT NULL,
  `vehicle_type` varchar(60) DEFAULT NULL,
  `capacity` varchar(40) DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `rc_number` varchar(60) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `transport_company_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_number` (`vehicle_number`),
  KEY `idx_vehicle_status` (`status`),
  KEY `idx_vehicle_driver` (`driver_id`),
  KEY `idx_vehicle_transport` (`transport_company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (1, 'PB10AX1234', 'Truck', '40 tyres', NULL, NULL, 1, 1, 'Active', '2026-05-22 19:24:33');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (2, 'GJ01BT5678', 'Truck', '40 tyres', NULL, NULL, 2, 2, 'Active', '2026-05-22 19:24:33');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (3, 'HR26CD9012', 'Truck', '40 tyres', NULL, NULL, 3, 3, 'Active', '2026-05-22 19:24:33');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (55, 'DEMO-MH12AB1234', 'Truck', '40 tyres', NULL, NULL, 54, 29, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (56, 'DEMO-GJ01BT5678', 'Truck', '40 tyres', NULL, NULL, 55, 31, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (57, 'DEMO-PB10AX9876', 'Truck', '40 tyres', NULL, NULL, 56, 30, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (58, 'DEMO-HR26CD4521', 'Truck', '40 tyres', NULL, NULL, 57, 29, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (59, 'DEMO-DL01EF3344', 'Truck', '40 tyres', NULL, NULL, 58, 33, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (60, 'DEMO-UP32GH7788', 'Truck', '40 tyres', NULL, NULL, 59, 32, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (61, 'DEMO-RJ14JK2233', 'Truck', '40 tyres', NULL, NULL, 60, 31, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (62, 'DEMO-PB65LM8899', 'Truck', '40 tyres', NULL, NULL, 61, 30, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (63, 'DEMO-CH01NO5566', 'Truck', '40 tyres', NULL, NULL, 62, 33, 'Active', '2026-05-23 19:14:21');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `insurance_expiry`, `rc_number`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (64, 'DEMO-MP09PQ1122', 'Truck', '40 tyres', NULL, NULL, 63, 32, 'Active', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `employee_code` varchar(20) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `father_name` varchar(120) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `aadhaar_number` char(12) DEFAULT NULL,
  `employee_type` enum('Staff','Worker') NOT NULL DEFAULT 'Staff',
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `department` varchar(80) NOT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `designation_id` int(11) DEFAULT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'Employee',
  `salary_type` varchar(50) NOT NULL DEFAULT 'Monthly',
  `shift_timing` varchar(80) DEFAULT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `joining_date` date NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_leave_limit` decimal(6,2) NOT NULL DEFAULT 12.00,
  `half_paid_leave_limit` decimal(6,2) NOT NULL DEFAULT 6.00,
  `hra_percentage` decimal(6,2) NOT NULL DEFAULT 40.00,
  `hra_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_applicable` tinyint(1) NOT NULL DEFAULT 1,
  `pf_percentage` decimal(6,2) NOT NULL DEFAULT 12.00,
  `esi_applicable` tinyint(1) NOT NULL DEFAULT 1,
  `esi_percentage` decimal(6,2) NOT NULL DEFAULT 0.75,
  `esi_salary_limit` decimal(12,2) NOT NULL DEFAULT 21000.00,
  `metro` tinyint(1) NOT NULL DEFAULT 0,
  `payroll_auto_indian` tinyint(1) NOT NULL DEFAULT 1,
  `medical_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dearness_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `travel_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `special_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_allowances` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gratuity_monthly` decimal(12,2) NOT NULL DEFAULT 0.00,
  `overtime_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `daily_wage` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hourly_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `uk_employees_aadhaar` (`aadhaar_number`),
  KEY `idx_employees_department_id` (`department_id`),
  KEY `idx_employees_designation_id` (`designation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=126 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (111, NULL, 'EMP001', 'Ravi Kumar', 'S/O Demo Father', '1988-01-01', '123456789001', 'Worker', 'ravi.kumar@demo.tyreerp.local', NULL, 'Mixing & Compounding', 'Mixing Operator', 3, 8, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100001', NULL, 'Village Rampur, Ludhiana, Punjab', NULL, '2024-11-23', '12100.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '22000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (112, NULL, 'EMP002', 'Neha Sharma', 'S/O Demo Father', '1988-02-01', '123456789002', 'Staff', 'neha.sharma@demo.tyreerp.local', NULL, 'Mixing & Compounding', 'Shift Supervisor', 3, 10, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100002', NULL, 'Sector 22, Chandigarh', NULL, '2024-11-30', '17600.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '32000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (113, NULL, 'EMP003', 'Amit Verma', 'S/O Demo Father', '1988-03-01', '123456789003', 'Worker', 'amit.verma@demo.tyreerp.local', NULL, 'Mixing & Compounding', 'Mixing Operator', 3, 8, 'Employee', 'Monthly', 'Evening Shift', '14:00:00', '22:00:00', '9876100003', NULL, 'Industrial Area, Jalandhar', NULL, '2024-12-07', '11825.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '21500.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (114, NULL, 'EMP004', 'Gagan Kumar Shah', 'S/O Demo Father', '1988-04-01', '123456789004', 'Worker', 'gagan.kumar.shah@demo.tyreerp.local', NULL, 'Tire Building / Product Assembly', 'Tire Builder', 5, 14, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100004', NULL, 'GT Road, Amritsar', NULL, '2024-12-14', '12650.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '23000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (115, NULL, 'EMP005', 'Suresh Patel', 'S/O Demo Father', '1988-05-01', '123456789005', 'Worker', 'suresh.patel@demo.tyreerp.local', NULL, 'Tire Building / Product Assembly', 'Tire Builder', 5, 14, 'Employee', 'Monthly', 'Evening Shift', '14:00:00', '22:00:00', '9876100005', NULL, 'Naroda, Ahmedabad, Gujarat', NULL, '2024-12-21', '12375.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '22500.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (116, NULL, 'EMP006', 'Mohit Singh', 'S/O Demo Father', '1988-06-01', '123456789006', 'Worker', 'mohit.singh@demo.tyreerp.local', NULL, 'Component Preparation (Extrusion & Calendering)', 'Calendering Operator', 4, 12, 'Employee', 'Monthly', 'Night Shift', '22:00:00', '06:00:00', '9876100006', NULL, 'Manesar, Gurgaon, Haryana', NULL, '2024-12-28', '11990.00', '12.00', '6.00', '0.00', '0.00', 1, '12.00', 0, '0.75', '21000.00', 0, 1, '500.00', '599.50', '500.00', '10390.50', '0.00', '23980.00', '576.72', '115.20', '921.60', '115.20', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (117, NULL, 'EMP007', 'Rakesh Yadav', 'S/O Demo Father', '1988-07-01', '123456789007', 'Worker', 'rakesh.yadav@demo.tyreerp.local', NULL, 'Curing & Vulcanization', 'Curing Operator', 6, 17, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100007', NULL, 'Ballabhgarh, Faridabad', NULL, '2025-01-04', '12320.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '22400.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (118, NULL, 'EMP008', 'Deepak Kumar', 'S/O Demo Father', '1988-08-01', '123456789008', 'Worker', 'deepak.kumar@demo.tyreerp.local', NULL, 'Curing & Vulcanization', 'Vulcanization Technician', 6, 18, 'Employee', 'Monthly', 'Evening Shift', '14:00:00', '22:00:00', '9876100008', NULL, 'Dadri, Greater Noida', NULL, '2025-01-11', '12540.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '22800.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (119, NULL, 'EMP009', 'Pooja Mehta', 'S/O Demo Father', '1988-09-01', '123456789009', 'Staff', 'pooja.mehta@demo.tyreerp.local', NULL, 'Warehouse Management', 'Warehouse Associate', 12, 38, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100009', NULL, 'Warehouse Colony, Bhiwandi', NULL, '2025-01-18', '14300.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '26000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (120, NULL, 'EMP010', 'Vikram Joshi', 'S/O Demo Father', '1988-10-01', '123456789010', 'Staff', 'vikram.joshi@demo.tyreerp.local', NULL, 'Logistics & Dispatch', 'Logistics Coordinator', 11, 35, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100010', NULL, 'Transport Nagar, Delhi', NULL, '2025-01-25', '15675.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '28500.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (121, NULL, 'EMP011', 'Anjali Reddy', 'S/O Demo Father', '1988-11-01', '123456789011', 'Staff', 'anjali.reddy@demo.tyreerp.local', NULL, 'Human Resources (HR)', 'HR Executive', 14, 43, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100011', NULL, 'MG Road, Bengaluru', NULL, '2025-02-01', '16500.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '30000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (122, NULL, 'EMP012', 'Rajesh Gupta', 'S/O Demo Father', '1988-12-01', '123456789012', 'Staff', 'rajesh.gupta@demo.tyreerp.local', NULL, 'Production Planning & Control (PPC)', 'PPC Executive', 1, 1, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100012', NULL, 'Panchkula, Haryana', NULL, '2025-02-08', '17050.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '31000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (123, NULL, 'EMP013', 'Sunil Nambiar', 'S/O Demo Father', '1989-01-01', '123456789013', 'Staff', 'sunil.nambiar@demo.tyreerp.local', NULL, 'Quality Assurance & Testing (QA/QC)', 'QA Inspector', 7, 20, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100013', NULL, 'Peenya, Bengaluru', NULL, '2025-02-15', '14850.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '27000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (124, NULL, 'EMP014', 'Kiran Desai', 'S/O Demo Father', '1989-02-01', '123456789014', 'Worker', 'kiran.desai@demo.tyreerp.local', NULL, 'Mixing & Compounding', 'Mixing Operator', 3, 8, 'Employee', 'Monthly', 'Night Shift', '22:00:00', '06:00:00', '9876100014', NULL, 'Vadodara, Gujarat', NULL, '2025-02-22', '11000.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '20000.00', '0.00', '0.00', '0.00', '0.00', 'inactive', '2026-05-23 19:14:20');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (125, NULL, 'EMP015', 'Meena Iyer', 'S/O Demo Father', '1989-03-01', '123456789015', 'Staff', 'meena.iyer@demo.tyreerp.local', NULL, 'Administration', 'Admin Executive', 15, 46, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '9876100015', NULL, 'Anna Nagar, Chennai', NULL, '2025-03-01', '13200.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 1, '0.00', '0.00', '0.00', '0.00', '0.00', '24000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-23 19:14:20');

DROP TABLE IF EXISTS `erp_activity_log`;
CREATE TABLE `erp_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(120) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `action_text` varchar(255) NOT NULL,
  `module_name` varchar(60) NOT NULL DEFAULT 'System',
  `status` varchar(20) NOT NULL DEFAULT 'success',
  `detail` varchar(255) DEFAULT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_value` varchar(500) DEFAULT NULL,
  `new_value` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_module` (`module_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (1, NULL, 'System', NULL, 'Locked user account #19154', 'User Management', 'warning', NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-29 23:08:25');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (2, NULL, 'System', NULL, 'Locked user account #19154', 'User Management', 'warning', NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-29 23:08:33');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (3, NULL, 'System', NULL, 'Toggled user status #19154', 'User Management', 'success', NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-29 23:10:28');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (4, 1, 'Super Admin', NULL, 'Locked user account', 'User Management', 'warning', '#19154', 'user', 19154, 'active', 'locked', '::1', '2026-05-30 23:02:37');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (5, 1, 'Super Admin', NULL, 'Locked user account', 'User Management', 'warning', '#19154', 'user', 19154, 'active', 'locked', '::1', '2026-05-30 23:04:33');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (6, 1, 'Super Admin', NULL, 'Locked user account', 'User Management', 'warning', '#19154', 'user', 19154, 'active', 'locked', '::1', '2026-05-30 23:06:38');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (7, 1, 'Super Admin', NULL, 'Locked user account', 'User Management', 'warning', '#19154', 'user', 19154, 'active', 'locked', '::1', '2026-05-30 23:07:13');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (8, 1, 'Super Admin', NULL, 'Froze user account', 'User Management', 'warning', '#19154', 'user', 19154, NULL, 'frozen', '::1', '2026-05-30 23:07:20');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (9, 1, 'Super Admin', NULL, 'Locked user account', 'User Management', 'warning', '#19154', 'user', 19154, 'active', 'locked', '::1', '2026-05-30 23:22:18');
INSERT INTO `erp_activity_log` (`id`, `user_id`, `user_name`, `department`, `action_text`, `module_name`, `status`, `detail`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES (10, 1, 'Super Admin', NULL, 'Froze user account', 'User Management', 'warning', '#19154', 'user', 19154, NULL, 'frozen', '::1', '2026-05-30 23:26:20');

DROP TABLE IF EXISTS `erp_custom_roles`;
CREATE TABLE `erp_custom_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(80) NOT NULL,
  `base_role` varchar(50) NOT NULL,
  `permissions_json` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_custom_role` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `erp_failed_logins`;
CREATE TABLE `erp_failed_logins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login_name` varchar(150) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `failure_reason` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_failed_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `erp_failed_logins` (`id`, `login_name`, `ip_address`, `failure_reason`, `created_at`) VALUES (1, 'admin@ralson.local', '::1', 'Account not found', '2026-05-30 23:05:28');
INSERT INTO `erp_failed_logins` (`id`, `login_name`, `ip_address`, `failure_reason`, `created_at`) VALUES (2, 'admin@ralson.local', '::1', 'Account not found', '2026-05-30 23:05:38');
INSERT INTO `erp_failed_logins` (`id`, `login_name`, `ip_address`, `failure_reason`, `created_at`) VALUES (3, 'su', '::1', 'Account not found', '2026-05-30 23:25:24');

DROP TABLE IF EXISTS `erp_login_history`;
CREATE TABLE `erp_login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `login_name` varchar(150) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`user_id`),
  KEY `idx_login_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (1, 1, 'superadmin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 22:27:04');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (2, 19154, 'accounts_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:05:02');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (3, NULL, 'admin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 0, 'Account not found', '2026-05-30 23:05:28');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (4, NULL, 'admin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 0, 'Account not found', '2026-05-30 23:05:38');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (5, 19154, 'accounts_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:05:42');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (6, 19154, 'accounts_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:05:56');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (7, 1, 'superadmin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:06:15');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (8, 19154, 'accounts_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:07:37');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (9, 1, 'superadmin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:07:58');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (10, 1, 'superadmin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:18:52');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (11, 1, 'superadmin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:18:58');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (12, 1, 'superadmin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:19:02');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (13, 19154, 'accounts_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:19:18');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (14, 1, 'superadmin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:21:35');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (15, 19154, 'accounts_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:22:44');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (16, NULL, 'su', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 0, 'Account not found', '2026-05-30 23:25:24');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (17, 1, 'superadmin@ralson.local', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:25:34');
INSERT INTO `erp_login_history` (`id`, `user_id`, `login_name`, `ip_address`, `user_agent`, `success`, `failure_reason`, `created_at`) VALUES (18, 19154, 'accounts_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, NULL, '2026-05-30 23:26:38');

DROP TABLE IF EXISTS `erp_user_sessions`;
CREATE TABLE `erp_user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_active` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session` (`session_id`),
  KEY `idx_sess_user` (`user_id`),
  KEY `idx_sess_active` (`last_active`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (1, 1, 'b9k2c1irvtmspsvgrtn7g91ubs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:04:50', NULL, '2026-05-30 22:27:04');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (2, 19154, 'bbdsgj9sgk5p9kfigcfu56kl6r', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:05:12', '2026-05-30 23:07:20', '2026-05-30 23:05:02');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (3, 19154, 'krf4kk8uggcc0td6pdmcrn4fbh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:05:44', '2026-05-30 23:07:20', '2026-05-30 23:05:42');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (4, 19154, 'ir22htboefv1p68j8e0der1hsc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:05:57', '2026-05-30 23:07:20', '2026-05-30 23:05:56');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (5, 1, 'p0hamk2du0oie12ed2tqitpsks', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:07:23', NULL, '2026-05-30 23:06:15');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (6, 19154, '4rr55g5sj8bqi7kli9qtf2o0gq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:07:38', '2026-05-30 23:22:18', '2026-05-30 23:07:37');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (7, 1, '97cf8o521a990cecdt63tsa97k', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:16:54', NULL, '2026-05-30 23:07:58');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (8, 1, 'ejairuois923s8htj1jcq2tgr7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:22:22', NULL, '2026-05-30 23:21:35');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (9, 19154, '68t2vqbbgv1o593afrjnucn0e8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:22:45', NULL, '2026-05-30 23:22:44');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (10, 1, '3j07i6siedk07drtpvcilaqum9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:26:23', NULL, '2026-05-30 23:25:34');
INSERT INTO `erp_user_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `last_active`, `revoked_at`, `created_at`) VALUES (11, 19154, '0oiiia4pt6n34lka1pi4t5c2k0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 23:31:42', NULL, '2026-05-30 23:26:38');

DROP TABLE IF EXISTS `hr_holidays`;
CREATE TABLE `hr_holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(160) NOT NULL,
  `holiday_type` varchar(50) NOT NULL,
  `department_scope` varchar(120) NOT NULL DEFAULT '' COMMENT 'empty = all departments',
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_hr_holiday_scope` (`holiday_date`,`department_scope`),
  KEY `idx_hr_holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `hr_notification_reads`;
CREATE TABLE `hr_notification_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_key` varchar(120) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dismissed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_notif_user_key` (`user_id`,`notification_key`),
  KEY `idx_hr_notif_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `hr_notification_reads` (`id`, `user_id`, `notification_key`, `read_at`, `dismissed_at`) VALUES (1, 2, 'leave_notice_17', '2026-05-20 19:02:32', NULL);

DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(120) NOT NULL,
  `batch_ref` varchar(40) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 0,
  `warehouse_location` varchar(120) NOT NULL,
  `stock_category` varchar(30) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inventory_qty` (`qty`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inventory` (`id`, `product_name`, `batch_ref`, `qty`, `reorder_level`, `warehouse_location`, `stock_category`, `updated_at`) VALUES (1, 'TBR Truck', 'QC-20260522-1', 0, 50, 'FG-A1', NULL, '2026-05-25 17:10:05');
INSERT INTO `inventory` (`id`, `product_name`, `batch_ref`, `qty`, `reorder_level`, `warehouse_location`, `stock_category`, `updated_at`) VALUES (27, 'PCR Car', 'DEMO-FG-PCR-CAR', 228, 50, 'FG-A1', 'dispatch_ready', '2026-05-28 18:19:16');
INSERT INTO `inventory` (`id`, `product_name`, `batch_ref`, `qty`, `reorder_level`, `warehouse_location`, `stock_category`, `updated_at`) VALUES (28, 'PCR SUV', 'DEMO-FG-PCR-SUV', 265, 50, 'FG-A1', 'dispatch_ready', '2026-05-25 17:54:43');
INSERT INTO `inventory` (`id`, `product_name`, `batch_ref`, `qty`, `reorder_level`, `warehouse_location`, `stock_category`, `updated_at`) VALUES (29, 'TBR Truck', 'DEMO-FG-TBR-TRUCK', 10, 50, 'FG-A1', 'dispatch_ready', '2026-05-25 17:10:05');
INSERT INTO `inventory` (`id`, `product_name`, `batch_ref`, `qty`, `reorder_level`, `warehouse_location`, `stock_category`, `updated_at`) VALUES (30, 'Farm / OTR', 'DEMO-FG-FARM---OTR', 67, 50, 'FG-A1', 'dispatch_ready', '2026-05-23 19:14:21');
INSERT INTO `inventory` (`id`, `product_name`, `batch_ref`, `qty`, `reorder_level`, `warehouse_location`, `stock_category`, `updated_at`) VALUES (31, 'Two Wheeler', 'DEMO-FG-TWO-WHEELER', 431, 50, 'FG-A1', 'dispatch_ready', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `inventory_activity`;
CREATE TABLE `inventory_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `activity_type` varchar(40) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `qty_change` decimal(12,2) NOT NULL DEFAULT 0.00,
  `message` varchar(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inv_activity_at` (`activity_at`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (1, '2026-05-22 18:08:26', 'adjustment', 3, '10000.00', 'New material added: gaga');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (2, '2026-05-22 18:08:30', 'adjustment', 4, '10000.00', 'New material added: gaga');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (3, '2026-05-22 18:17:43', 'inward', 2, '7887.97', 'Carbon Black stock added +7887.97 kg');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (4, '2026-05-22 18:18:06', 'inward', 2, '120000000.00', 'Carbon Black stock added +120000000 kg');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (5, '2026-05-22 18:18:36', 'usage', 2, '-13000000.00', 'Building used 13000000 kg Carbon Black');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (6, '2026-05-23 18:17:09', 'usage', 1, '-270.00', 'Mixing used 270 kg Natural Rubber');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (7, '2026-05-23 18:17:09', 'usage', 2, '-150.00', 'Mixing used 150 kg Carbon Black');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (8, '2026-05-23 18:17:09', 'usage', 5, '-30.00', 'Mixing used 30 kg Chemicals');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (9, '2026-05-23 18:17:19', 'usage', 1, '-270.00', 'Mixing used 270 kg Natural Rubber');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (10, '2026-05-23 18:17:19', 'usage', 2, '-150.00', 'Mixing used 150 kg Carbon Black');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (11, '2026-05-23 18:17:19', 'usage', 5, '-30.00', 'Mixing used 30 kg Chemicals');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (12, '2026-05-26 18:46:59', 'adjustment', 112, '0.00', 'Material registered: chemial hard');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (13, '2026-05-26 19:23:20', 'inward', 102, '89.00', 'PINV-20260526-001: Fabric Layer +89 m');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (14, '2026-05-26 19:23:20', 'adjustment', 102, '0.00', 'Stock limits set for Fabric Layer (min 150, max 3000)');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (15, '2026-05-26 19:25:22', 'adjustment', 102, '0.00', 'PINV-20260526-001: supplier payment ₹5,402.00 (UPI)');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (16, '2026-05-26 19:29:19', 'inward', 5, '60.00', 'PINV-20260526-002: Chemicals +60 kg');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (17, '2026-05-26 19:29:19', 'adjustment', 5, '0.00', 'Stock limits set for Chemicals (min 0, max 0)');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (18, '2026-05-26 19:29:23', 'inward', 5, '60.00', 'PINV-20260526-003: Chemicals +60 kg');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (19, '2026-05-26 19:29:23', 'adjustment', 5, '0.00', 'Stock limits set for Chemicals (min 0, max 0)');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (20, '2026-05-26 19:29:45', 'inward', 5, '60.00', 'PINV-20260526-004: Chemicals +60 kg');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (21, '2026-05-26 19:29:45', 'adjustment', 5, '0.00', 'Stock limits set for Chemicals (min 0, max 0)');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (22, '2026-05-26 19:32:39', 'adjustment', 5, '0.00', 'PINV-20260526-004: supplier payment ₹6,453.22 (Cash)');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (23, '2026-05-26 19:34:00', 'inward', 112, '98.00', 'PINV-20260526-005: chemial hard +98 ltr');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (24, '2026-05-26 19:36:34', 'inward', 5, '435434.99', 'PINV-20260526-006: Chemicals +435434.99 kg');
INSERT INTO `inventory_activity` (`id`, `activity_at`, `activity_type`, `material_id`, `qty_change`, `message`) VALUES (25, '2026-05-28 20:33:08', 'adjustment', 5, '0.00', 'PINV-20260526-003: supplier payment ₹50.00 (Cash)');

DROP TABLE IF EXISTS `leave_notifications`;
CREATE TABLE `leave_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) DEFAULT NULL,
  `leave_id` int(11) DEFAULT NULL,
  `notice_type` varchar(40) NOT NULL,
  `message` varchar(500) NOT NULL,
  `audience` enum('employee','hr') NOT NULL DEFAULT 'employee',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ln_employee` (`employee_id`),
  KEY `idx_ln_audience` (`audience`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (1, 5, 2, 'approved', 'Leave auto-approved. System classified as Paid.', 'employee', 0, '2026-05-17 19:48:49');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (2, 5, 2, 'balance_low', 'Paid leave balance is running low.', 'employee', 0, '2026-05-17 19:48:49');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (3, 5, 3, 'approved', 'Leave auto-approved. System classified as Paid.', 'employee', 0, '2026-05-17 19:49:26');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (4, 5, 3, 'balance_low', 'Paid leave balance is running low.', 'employee', 0, '2026-05-17 19:49:26');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (5, 5, 4, 'pending', 'Leave submitted — Pending HR approval. Critical staffing shortage if approved.', 'employee', 0, '2026-05-17 20:02:10');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (6, NULL, NULL, 'hr_staffing', 'Staffing Critical for leave 2026-05-20 to 2026-05-20 (emp #5)', 'hr', 0, '2026-05-17 20:02:10');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (7, 5, 5, 'pending', 'Leave submitted — Pending HR approval. Critical staffing shortage if approved.', 'employee', 0, '2026-05-17 20:04:05');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (8, NULL, NULL, 'hr_staffing', 'Staffing Critical for leave 2026-05-21 to 2026-05-21 (emp #5)', 'hr', 0, '2026-05-17 20:04:05');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (9, 5, 5, 'approved', 'Your leave request was approved.', 'employee', 0, '2026-05-17 20:04:42');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (10, 5, 4, 'approved', 'Your leave request was approved.', 'employee', 0, '2026-05-17 20:04:48');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (11, 5, 6, 'pending', 'Leave submitted — Pending HR approval. Critical staffing shortage if approved.', 'employee', 0, '2026-05-17 20:06:19');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (12, 5, 6, 'converted_unpaid', 'Part or all of this leave is unpaid due to exhausted balance.', 'employee', 0, '2026-05-17 20:06:19');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (13, NULL, NULL, 'hr_staffing', 'Staffing Critical for leave 2026-05-29 to 2026-06-11 (emp #5)', 'hr', 0, '2026-05-17 20:06:19');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (14, 5, 6, 'rejected', 'Your leave request was rejected. Reason: long', 'employee', 0, '2026-05-17 20:07:01');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (15, 5, 7, 'pending', 'Leave submitted — Pending HR approval. Critical staffing shortage if approved.', 'employee', 0, '2026-05-20 17:42:04');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (16, 5, 7, 'converted_unpaid', 'Part or all of this leave is unpaid due to exhausted balance.', 'employee', 0, '2026-05-20 17:42:04');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (17, NULL, NULL, 'hr_staffing', 'Staffing Critical for leave 2026-05-29 to 2026-05-31 (emp #5)', 'hr', 0, '2026-05-20 17:42:04');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (18, 5, 7, 'approved', 'Your leave request was approved.', 'employee', 0, '2026-05-20 17:42:57');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (19, 5, 5, 'converted_unpaid', 'Your leave was converted to unpaid by HR.', 'employee', 0, '2026-05-20 17:57:35');
INSERT INTO `leave_notifications` (`id`, `employee_id`, `leave_id`, `notice_type`, `message`, `audience`, `is_read`, `created_at`) VALUES (20, 5, 4, 'converted_unpaid', 'Your leave was converted to unpaid by HR.', 'employee', 0, '2026-05-20 17:57:48');

DROP TABLE IF EXISTS `leaves`;
CREATE TABLE `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL DEFAULT 'Casual',
  `from_date` date NOT NULL,
  `start_date` date DEFAULT NULL,
  `to_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `leave_category` enum('Paid','Half Paid','Unpaid') NOT NULL DEFAULT 'Paid',
  `is_paid` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('Applied','Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `half_paid_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `unpaid_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_days` decimal(6,2) NOT NULL DEFAULT 0.00,
  `is_emergency` tinyint(1) NOT NULL DEFAULT 0,
  `auto_approved` tinyint(1) NOT NULL DEFAULT 0,
  `entry_source` varchar(20) NOT NULL DEFAULT 'employee',
  `recorded_by` int(11) DEFAULT NULL,
  `staffing_risk` varchar(20) NOT NULL DEFAULT 'Safe',
  `system_note` varchar(255) DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `day_allocation_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_leave_employee` (`employee_id`),
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `machine_assignments`;
CREATE TABLE `machine_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `released_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ma_order` (`order_id`),
  CONSTRAINT `fk_ma_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `machine_audit_log`;
CREATE TABLE `machine_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` int(11) NOT NULL,
  `field_name` varchar(40) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mal_machine` (`machine_id`),
  CONSTRAINT `fk_mal_machine` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `machine_operator_assignments`;
CREATE TABLE `machine_operator_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `department` varchar(40) NOT NULL,
  `shift` varchar(20) DEFAULT NULL,
  `assigned_from` date NOT NULL,
  `assigned_till` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `closed_at` datetime DEFAULT NULL,
  `closed_reason` varchar(120) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_moa_machine` (`machine_id`),
  KEY `idx_moa_employee` (`employee_id`),
  KEY `idx_moa_active` (`machine_id`,`is_active`),
  CONSTRAINT `fk_moa_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_moa_machine` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `machine_operator_assignments` (`id`, `machine_id`, `employee_id`, `department`, `shift`, `assigned_from`, `assigned_till`, `remarks`, `is_active`, `closed_at`, `closed_reason`, `created_by`, `created_at`, `updated_at`) VALUES (43, 73, 111, 'Mixing', 'Morning', '2026-02-22', '2026-05-23', 'Initial demo assignment', 0, '2026-05-23 20:05:45', 'Machine deactivated', NULL, '2026-05-23 19:14:20', '2026-05-23 20:05:45');
INSERT INTO `machine_operator_assignments` (`id`, `machine_id`, `employee_id`, `department`, `shift`, `assigned_from`, `assigned_till`, `remarks`, `is_active`, `closed_at`, `closed_reason`, `created_by`, `created_at`, `updated_at`) VALUES (44, 74, 113, 'Mixing', 'Evening', '2026-02-22', NULL, 'Initial demo assignment', 1, NULL, NULL, NULL, '2026-05-23 19:14:20', '2026-05-23 19:14:20');
INSERT INTO `machine_operator_assignments` (`id`, `machine_id`, `employee_id`, `department`, `shift`, `assigned_from`, `assigned_till`, `remarks`, `is_active`, `closed_at`, `closed_reason`, `created_by`, `created_at`, `updated_at`) VALUES (45, 76, 114, 'Building', 'Morning', '2026-02-22', NULL, 'Initial demo assignment', 1, NULL, NULL, NULL, '2026-05-23 19:14:20', '2026-05-23 19:14:20');
INSERT INTO `machine_operator_assignments` (`id`, `machine_id`, `employee_id`, `department`, `shift`, `assigned_from`, `assigned_till`, `remarks`, `is_active`, `closed_at`, `closed_reason`, `created_by`, `created_at`, `updated_at`) VALUES (46, 77, 115, 'Building', 'Evening', '2026-02-22', NULL, 'Initial demo assignment', 1, NULL, NULL, NULL, '2026-05-23 19:14:20', '2026-05-23 19:14:20');
INSERT INTO `machine_operator_assignments` (`id`, `machine_id`, `employee_id`, `department`, `shift`, `assigned_from`, `assigned_till`, `remarks`, `is_active`, `closed_at`, `closed_reason`, `created_by`, `created_at`, `updated_at`) VALUES (47, 79, 116, 'Building', 'Night', '2026-02-22', NULL, 'Initial demo assignment', 1, NULL, NULL, NULL, '2026-05-23 19:14:20', '2026-05-23 19:14:20');
INSERT INTO `machine_operator_assignments` (`id`, `machine_id`, `employee_id`, `department`, `shift`, `assigned_from`, `assigned_till`, `remarks`, `is_active`, `closed_at`, `closed_reason`, `created_by`, `created_at`, `updated_at`) VALUES (48, 81, 117, 'Curing', 'Morning', '2026-02-22', NULL, 'Initial demo assignment', 1, NULL, NULL, NULL, '2026-05-23 19:14:20', '2026-05-23 19:14:20');
INSERT INTO `machine_operator_assignments` (`id`, `machine_id`, `employee_id`, `department`, `shift`, `assigned_from`, `assigned_till`, `remarks`, `is_active`, `closed_at`, `closed_reason`, `created_by`, `created_at`, `updated_at`) VALUES (49, 73, 124, 'Mixing', 'Night', '2026-01-23', '2026-02-17', 'Historical demo assignment', 0, NULL, 'Employee left shift', NULL, '2026-05-23 19:14:20', '2026-05-23 19:14:20');

DROP TABLE IF EXISTS `machine_status_history`;
CREATE TABLE `machine_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_id` int(11) NOT NULL,
  `old_status` varchar(40) DEFAULT NULL,
  `new_status` varchar(40) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_msh_machine` (`machine_id`),
  CONSTRAINT `fk_msh_machine` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `machine_status_history` (`id`, `machine_id`, `old_status`, `new_status`, `changed_at`, `changed_by`, `remarks`) VALUES (1, 73, 'Active', 'Scrap / Deactivated', '2026-05-23 20:05:45', 3, 'Deactivated');

DROP TABLE IF EXISTS `machines`;
CREATE TABLE `machines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_code` varchar(30) NOT NULL,
  `machine_name` varchar(120) NOT NULL,
  `machine_type` varchar(80) DEFAULT NULL,
  `department` varchar(40) DEFAULT NULL,
  `section` varchar(80) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `shift_capacity` int(11) NOT NULL DEFAULT 0,
  `status` varchar(40) NOT NULL DEFAULT 'Idle',
  `last_maintenance_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deactivated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_code` (`machine_code`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (73, 'MC-101', 'Banbury Mixer 1', 'Banbury', 'Mixing', 'Line A', '2022-03-15', 120, 'Scrap / Deactivated', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 0, '2026-05-23 20:05:45');
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (74, 'MC-102', 'Compound Mixer 2', 'Internal Mixer', 'Mixing', 'Line A', '2022-03-15', 120, 'Active', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (75, 'MC-103', 'Mixing Mill 3', 'Two-Roll Mill', 'Mixing', 'Line B', '2022-03-15', 120, 'Idle', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (76, 'TB-201', 'Tyre Building Machine 1', 'Building Drum', 'Building', 'Building-1', '2022-03-15', 120, 'Active', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (77, 'TB-202', 'Tyre Building Machine 2', 'Building Drum', 'Building', 'Building-1', '2022-03-15', 120, 'Active', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (78, 'TB-203', 'Tyre Building Machine 3', 'Building Drum', 'Building', 'Building-2', '2022-03-15', 120, 'Under Repair', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (79, 'CL-301', 'Calendar Line 1', 'Calendering Line', 'Building', 'Calendering', '2022-03-15', 120, 'Active', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (80, 'CL-302', 'Calendar Line 2', 'Calendering Line', 'Building', 'Calendering', '2022-03-15', 120, 'Idle', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (81, 'CU-401', 'Curing Press 1', 'Curing Press', 'Curing', 'Curing Bay 1', '2022-03-15', 120, 'Active', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (82, 'CU-402', 'Curing Press 2', 'Curing Press', 'Curing', 'Curing Bay 2', '2022-03-15', 120, 'Scrap / Deactivated', '2026-04-08', NULL, 'Demo factory asset', '2026-05-23 19:14:20', 0, NULL);

DROP TABLE IF EXISTS `mixing_batches`;
CREATE TABLE `mixing_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `batch_code` varchar(40) NOT NULL,
  `compound_name` varchar(120) NOT NULL DEFAULT 'Rubber Compound',
  `produced_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wastage_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) NOT NULL DEFAULT 'kg',
  `machine_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `production_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Ready',
  `notes` varchar(500) DEFAULT NULL,
  `inventory_deducted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `idx_mix_order` (`order_id`),
  KEY `idx_mix_date` (`production_date`),
  CONSTRAINT `fk_mix_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `mixing_entries`;
CREATE TABLE `mixing_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `production_date` date NOT NULL,
  `shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `machine_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `tyre_type` varchar(120) NOT NULL,
  `produced_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rejected_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mix_entry_date` (`production_date`),
  KEY `idx_mix_entry_shift` (`shift`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (1, '2026-05-25', 'Morning', 2, 2, 'TBR Truck', '1000.00', '4.81', NULL, '2026-05-22 17:25:09');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (5, '2026-05-23', 'Morning', 1, 1, 'PCR Car', '600.00', '0.00', NULL, '2026-05-23 18:17:09');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (6, '2026-05-23', 'Morning', 1, 1, 'PCR Car', '600.00', '0.00', NULL, '2026-05-23 18:17:19');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (67, '2026-05-23', 'Morning', 73, 111, 'PCR Car', '820.00', '15.50', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (68, '2026-05-22', 'Evening', 74, 113, 'PCR SUV', '832.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (69, '2026-05-21', 'Night', 73, 112, 'TBR Truck', '844.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (70, '2026-05-20', 'Morning', 74, 111, 'Farm / OTR', '856.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (71, '2026-05-19', 'Evening', 73, 113, 'Two Wheeler', '868.00', '15.50', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (72, '2026-05-18', 'Night', 74, 112, 'PCR Car', '880.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (73, '2026-05-17', 'Morning', 73, 111, 'PCR SUV', '892.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (74, '2026-05-16', 'Evening', 74, 113, 'TBR Truck', '904.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (75, '2026-05-15', 'Night', 73, 112, 'Farm / OTR', '916.00', '15.50', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (76, '2026-05-14', 'Morning', 74, 111, 'Two Wheeler', '928.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (77, '2026-05-13', 'Evening', 73, 113, 'PCR Car', '940.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (78, '2026-05-12', 'Night', 74, 112, 'PCR SUV', '952.00', '0.00', 'Demo mixing batch [demo]', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `payroll`;
CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `present_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `paid_leave_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `unpaid_leave_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `overtime_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payroll_employee_month_year` (`employee_id`,`month`,`year`),
  CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payroll` (`id`, `employee_id`, `month`, `year`, `present_days`, `paid_leave_days`, `unpaid_leave_days`, `overtime_amount`, `basic_salary`, `deduction`, `net_salary`, `created_at`) VALUES (10, 114, 5, 2026, '25.00', '0.00', '0.00', '1104.90', '13754.90', '8073.92', '5680.98', '2026-05-29 17:04:55');
INSERT INTO `payroll` (`id`, `employee_id`, `month`, `year`, `present_days`, `paid_leave_days`, `unpaid_leave_days`, `overtime_amount`, `basic_salary`, `deduction`, `net_salary`, `created_at`) VALUES (11, 113, 5, 2026, '25.00', '0.00', '0.00', '1032.90', '12857.90', '7547.35', '5310.55', '2026-05-29 18:36:30');

DROP TABLE IF EXISTS `payroll_settings`;
CREATE TABLE `payroll_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `basic_pct_of_gross` decimal(6,2) NOT NULL DEFAULT 50.00,
  `da_pct_of_basic` decimal(6,2) NOT NULL DEFAULT 0.00,
  `da_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `hra_pct_non_metro` decimal(6,2) NOT NULL DEFAULT 40.00,
  `hra_pct_metro` decimal(6,2) NOT NULL DEFAULT 50.00,
  `medical_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `medical_mode` varchar(12) NOT NULL DEFAULT 'fixed',
  `medical_fixed` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medical_pct_of_basic` decimal(6,2) NOT NULL DEFAULT 0.00,
  `travel_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `travel_mode` varchar(12) NOT NULL DEFAULT 'fixed',
  `travel_fixed` decimal(12,2) NOT NULL DEFAULT 0.00,
  `travel_pct_of_basic` decimal(6,2) NOT NULL DEFAULT 0.00,
  `gratuity_pct_of_basic` decimal(7,3) NOT NULL DEFAULT 4.810,
  `pf_employee_pct` decimal(6,2) NOT NULL DEFAULT 12.00,
  `pf_employer_pct` decimal(6,2) NOT NULL DEFAULT 12.00,
  `esi_employee_pct` decimal(6,2) NOT NULL DEFAULT 0.75,
  `esi_employer_pct` decimal(6,2) NOT NULL DEFAULT 3.25,
  `esi_gross_limit` decimal(12,2) NOT NULL DEFAULT 21000.00,
  `working_days_default` decimal(6,2) NOT NULL DEFAULT 26.00,
  `shift_hours_default` decimal(6,2) NOT NULL DEFAULT 8.00,
  `ot_multiplier` decimal(6,2) NOT NULL DEFAULT 1.00,
  `late_deduction_pct_of_daily` decimal(6,2) NOT NULL DEFAULT 10.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `full_day_min_hours` decimal(6,2) NOT NULL DEFAULT 8.00,
  `half_day_min_hours` decimal(6,2) NOT NULL DEFAULT 4.00,
  `grace_late_minutes` int(11) NOT NULL DEFAULT 15,
  `min_valid_punch_hours` decimal(6,2) NOT NULL DEFAULT 0.50,
  `auto_absent_after_hours` decimal(6,2) NOT NULL DEFAULT 0.00,
  `ot_threshold_hours` decimal(6,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payroll_settings` (`id`, `basic_pct_of_gross`, `da_pct_of_basic`, `da_enabled`, `hra_pct_non_metro`, `hra_pct_metro`, `medical_enabled`, `medical_mode`, `medical_fixed`, `medical_pct_of_basic`, `travel_enabled`, `travel_mode`, `travel_fixed`, `travel_pct_of_basic`, `gratuity_pct_of_basic`, `pf_employee_pct`, `pf_employer_pct`, `esi_employee_pct`, `esi_employer_pct`, `esi_gross_limit`, `working_days_default`, `shift_hours_default`, `ot_multiplier`, `late_deduction_pct_of_daily`, `updated_at`, `full_day_min_hours`, `half_day_min_hours`, `grace_late_minutes`, `min_valid_punch_hours`, `auto_absent_after_hours`, `ot_threshold_hours`) VALUES (1, '50.00', '5.00', 1, '40.00', '50.00', 1, 'fixed', '500.00', '0.00', 1, 'fixed', '500.00', '0.00', '4.810', '12.00', '12.00', '0.75', '3.25', '21000.00', '26.02', '8.00', '1.00', '10.00', '2026-05-15 18:42:48', '8.00', '4.00', 15, '0.50', '0.00', '0.00');

DROP TABLE IF EXISTS `production`;
CREATE TABLE `production` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `production_date` date NOT NULL,
  `machine_id` int(11) NOT NULL,
  `shift` enum('Morning','Evening','Night') NOT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `tyre_type` varchar(120) DEFAULT NULL,
  `planned_quantity` int(11) NOT NULL DEFAULT 0,
  `raw_material_id` int(11) DEFAULT NULL,
  `material_used_qty` decimal(12,2) DEFAULT NULL,
  `output_quantity` int(11) NOT NULL,
  `rejected_quantity` int(11) NOT NULL DEFAULT 0,
  `downtime_minutes` int(11) NOT NULL DEFAULT 0,
  `remarks` varchar(500) DEFAULT NULL,
  `efficiency_pct` decimal(6,2) DEFAULT NULL,
  `entry_status` varchar(30) NOT NULL DEFAULT 'Submitted',
  `inventory_deducted` tinyint(1) NOT NULL DEFAULT 0,
  `payroll_ot_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Reserved for OT shift payroll link',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prod_date` (`production_date`),
  KEY `fk_prod_machine` (`machine_id`),
  KEY `fk_prod_material` (`raw_material_id`),
  KEY `fk_production_operator` (`operator_id`),
  CONSTRAINT `fk_prod_machine` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  CONSTRAINT `fk_prod_material` FOREIGN KEY (`raw_material_id`) REFERENCES `raw_materials` (`id`),
  CONSTRAINT `fk_production_operator` FOREIGN KEY (`operator_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `production_bom`;
CREATE TABLE `production_bom` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tyre_type` varchar(120) NOT NULL,
  `raw_material_id` int(11) NOT NULL,
  `qty_per_unit` decimal(12,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bom_tyre_material` (`tyre_type`,`raw_material_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `production_bom` (`id`, `tyre_type`, `raw_material_id`, `qty_per_unit`) VALUES (1, 'TBR Truck', 1, '0.5000');
INSERT INTO `production_bom` (`id`, `tyre_type`, `raw_material_id`, `qty_per_unit`) VALUES (2, 'TBR Truck', 2, '0.2000');
INSERT INTO `production_bom` (`id`, `tyre_type`, `raw_material_id`, `qty_per_unit`) VALUES (3, 'PCR Car', 1, '0.4000');
INSERT INTO `production_bom` (`id`, `tyre_type`, `raw_material_id`, `qty_per_unit`) VALUES (4, 'PCR Car', 2, '0.1500');

DROP TABLE IF EXISTS `production_downtime`;
CREATE TABLE `production_downtime` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pd_order` (`order_id`),
  CONSTRAINT `fk_pd_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `production_logs`;
CREATE TABLE `production_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `message` varchar(500) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prod_logs_order` (`order_id`),
  CONSTRAINT `fk_prod_log_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `production_logs` (`id`, `order_id`, `stage_id`, `action`, `message`, `user_id`, `created_at`) VALUES (1, 1, NULL, 'order_created', 'Production job ticket PRD-20260521-001 created — production not started yet.', 3, '2026-05-21 18:14:18');
INSERT INTO `production_logs` (`id`, `order_id`, `stage_id`, `action`, `message`, `user_id`, `created_at`) VALUES (2, 2, NULL, 'order_created', 'Master production target PRD-20260522-001 — departments work independently.', 3, '2026-05-22 17:03:43');

DROP TABLE IF EXISTS `production_orders`;
CREATE TABLE `production_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_code` varchar(40) NOT NULL,
  `tyre_type` varchar(120) NOT NULL,
  `target_qty` int(11) NOT NULL DEFAULT 0,
  `deadline` date DEFAULT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'Normal',
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `current_stage` varchar(40) NOT NULL DEFAULT 'Mixing',
  `total_produced` int(11) NOT NULL DEFAULT 0,
  `total_rejected` int(11) NOT NULL DEFAULT 0,
  `total_downtime` int(11) NOT NULL DEFAULT 0,
  `qc_passed_qty` int(11) NOT NULL DEFAULT 0,
  `qc_failed_qty` int(11) NOT NULL DEFAULT 0,
  `inventory_deducted` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` varchar(500) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_code` (`order_code`),
  KEY `idx_prod_orders_status` (`status`),
  KEY `idx_prod_orders_stage` (`current_stage`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `production_orders` (`id`, `order_code`, `tyre_type`, `target_qty`, `deadline`, `priority`, `status`, `current_stage`, `total_produced`, `total_rejected`, `total_downtime`, `qc_passed_qty`, `qc_failed_qty`, `inventory_deducted`, `remarks`, `created_by_user_id`, `created_at`, `updated_at`) VALUES (1, 'PRD-20260521-001', 'TBR Truck', 1000, '2026-05-13', 'Normal', 'Pending', 'Mixing', 0, 0, 0, 0, 0, 0, 'ggdd', 3, '2026-05-21 18:14:18', NULL);
INSERT INTO `production_orders` (`id`, `order_code`, `tyre_type`, `target_qty`, `deadline`, `priority`, `status`, `current_stage`, `total_produced`, `total_rejected`, `total_downtime`, `qc_passed_qty`, `qc_failed_qty`, `inventory_deducted`, `remarks`, `created_by_user_id`, `created_at`, `updated_at`) VALUES (2, 'PRD-20260522-001', 'Two Wheeler', 1000, '2026-05-21', 'High', 'Open', 'Plant', 0, 0, 0, 0, 0, 0, 'bb', 3, '2026-05-22 17:03:43', NULL);

DROP TABLE IF EXISTS `production_qc_entries`;
CREATE TABLE `production_qc_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `curing_batch_id` int(11) DEFAULT NULL,
  `batch_ref` varchar(40) DEFAULT NULL,
  `inspection_date` date NOT NULL,
  `inspected_qty` int(11) NOT NULL DEFAULT 0,
  `passed_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `defect_type` varchar(120) DEFAULT NULL,
  `inspector_name` varchar(150) NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `inventory_added` tinyint(1) NOT NULL DEFAULT 0,
  `warehouse_location` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pqc_order` (`order_id`),
  KEY `idx_pqc_date` (`inspection_date`),
  KEY `fk_pqc_cur` (`curing_batch_id`),
  CONSTRAINT `fk_pqc_cur` FOREIGN KEY (`curing_batch_id`) REFERENCES `curing_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pqc_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `production_stage_logs`;
CREATE TABLE `production_stage_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `stage_name` varchar(40) NOT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `shift` varchar(20) DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `produced_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `downtime_minutes` int(11) NOT NULL DEFAULT 0,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `action_type` varchar(40) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psl_order` (`order_id`),
  KEY `idx_psl_stage` (`stage_id`),
  CONSTRAINT `fk_psl_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `production_stages`;
CREATE TABLE `production_stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `stage_name` varchar(40) NOT NULL,
  `stage_order` tinyint(4) NOT NULL DEFAULT 1,
  `machine_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `shift` varchar(20) DEFAULT 'Morning',
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `produced_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `downtime_minutes` int(11) NOT NULL DEFAULT 0,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_stage` (`order_id`,`stage_name`),
  KEY `idx_prod_stages_status` (`status`),
  KEY `fk_prod_stage_machine` (`machine_id`),
  KEY `fk_prod_stage_operator` (`operator_id`),
  CONSTRAINT `fk_prod_stage_machine` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_stage_operator` FOREIGN KEY (`operator_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_stage_order` FOREIGN KEY (`order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `production_stages` (`id`, `order_id`, `stage_name`, `stage_order`, `machine_id`, `operator_id`, `shift`, `status`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `started_at`, `ended_at`, `remarks`) VALUES (1, 1, 'Mixing', 1, NULL, NULL, 'Morning', 'Pending', 0, 0, 0, NULL, NULL, NULL);
INSERT INTO `production_stages` (`id`, `order_id`, `stage_name`, `stage_order`, `machine_id`, `operator_id`, `shift`, `status`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `started_at`, `ended_at`, `remarks`) VALUES (2, 1, 'Building', 2, NULL, NULL, 'Morning', 'Pending', 0, 0, 0, NULL, NULL, NULL);
INSERT INTO `production_stages` (`id`, `order_id`, `stage_name`, `stage_order`, `machine_id`, `operator_id`, `shift`, `status`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `started_at`, `ended_at`, `remarks`) VALUES (3, 1, 'Curing', 3, NULL, NULL, 'Morning', 'Pending', 0, 0, 0, NULL, NULL, NULL);
INSERT INTO `production_stages` (`id`, `order_id`, `stage_name`, `stage_order`, `machine_id`, `operator_id`, `shift`, `status`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `started_at`, `ended_at`, `remarks`) VALUES (4, 1, 'QC', 4, NULL, NULL, 'Morning', 'Pending', 0, 0, 0, NULL, NULL, NULL);
INSERT INTO `production_stages` (`id`, `order_id`, `stage_name`, `stage_order`, `machine_id`, `operator_id`, `shift`, `status`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `started_at`, `ended_at`, `remarks`) VALUES (5, 1, 'Finished', 5, NULL, NULL, 'Morning', 'Pending', 0, 0, 0, NULL, NULL, NULL);

DROP TABLE IF EXISTS `purchase_payments`;
CREATE TABLE `purchase_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inward_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(40) DEFAULT NULL,
  `payment_ref` varchar(80) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `recorded_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pp_inward` (`inward_id`),
  KEY `idx_pp_date` (`payment_date`),
  CONSTRAINT `fk_pp_inward` FOREIGN KEY (`inward_id`) REFERENCES `stock_inward` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `purchase_payments` (`id`, `inward_id`, `payment_date`, `amount`, `payment_mode`, `payment_ref`, `notes`, `recorded_by`, `created_at`) VALUES (1, 63, '2026-05-26', '5402.00', 'UPI', NULL, NULL, 'invmanager', '2026-05-26 19:25:22');
INSERT INTO `purchase_payments` (`id`, `inward_id`, `payment_date`, `amount`, `payment_mode`, `payment_ref`, `notes`, `recorded_by`, `created_at`) VALUES (2, 66, '2026-05-26', '6453.22', 'Cash', NULL, NULL, 'invmanager', '2026-05-26 19:32:39');
INSERT INTO `purchase_payments` (`id`, `inward_id`, `payment_date`, `amount`, `payment_mode`, `payment_ref`, `notes`, `recorded_by`, `created_at`) VALUES (3, 68, '2026-05-28', '347705026.37', 'Cash', NULL, NULL, 'accounts_manager', '2026-05-28 20:22:43');
INSERT INTO `purchase_payments` (`id`, `inward_id`, `payment_date`, `amount`, `payment_mode`, `payment_ref`, `notes`, `recorded_by`, `created_at`) VALUES (4, 67, '2026-05-28', '9582.60', 'Cash', NULL, NULL, 'accounts_manager', '2026-05-28 20:23:31');
INSERT INTO `purchase_payments` (`id`, `inward_id`, `payment_date`, `amount`, `payment_mode`, `payment_ref`, `notes`, `recorded_by`, `created_at`) VALUES (5, 65, '2026-05-28', '50.00', 'Cash', NULL, NULL, 'accounts_manager', '2026-05-28 20:33:08');

DROP TABLE IF EXISTS `qc_defect_lines`;
CREATE TABLE `qc_defect_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspection_id` int(11) NOT NULL,
  `defect_type` varchar(40) NOT NULL,
  `defect_label` varchar(120) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qc_def_insp` (`inspection_id`),
  CONSTRAINT `fk_qc_def_insp` FOREIGN KEY (`inspection_id`) REFERENCES `qc_inspections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `qc_entries`;
CREATE TABLE `qc_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL,
  `shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `inspector_name` varchar(150) NOT NULL,
  `tyre_type` varchar(120) NOT NULL,
  `checked_qty` int(11) NOT NULL DEFAULT 0,
  `passed_qty` int(11) NOT NULL DEFAULT 0,
  `failed_qty` int(11) NOT NULL DEFAULT 0,
  `defect_type` varchar(120) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qc_entry_date` (`entry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (1, '2026-05-22', 'Morning', 'sd', 'TBR Truck', 150, 29, 30, NULL, 'h', '2026-05-22 17:26:30');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (2, '2026-05-23', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:12:03');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (3, '2026-05-22', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:03');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (4, '2026-05-21', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:03');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (5, '2026-05-20', 'Morning', 'Sunil Nambiar', 'Farm / OTR', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:12:03');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (6, '2026-05-19', 'Morning', 'Sunil Nambiar', 'Two Wheeler', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:03');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (7, '2026-05-18', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:03');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (8, '2026-05-17', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:12:03');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (9, '2026-05-16', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:03');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (10, '2026-05-23', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:12:35');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (11, '2026-05-22', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:35');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (12, '2026-05-21', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:35');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (13, '2026-05-20', 'Morning', 'Sunil Nambiar', 'Farm / OTR', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:12:35');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (14, '2026-05-19', 'Morning', 'Sunil Nambiar', 'Two Wheeler', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:35');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (15, '2026-05-18', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:35');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (16, '2026-05-17', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:12:35');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (17, '2026-05-16', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:12:35');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (18, '2026-05-23', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:22');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (19, '2026-05-22', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:22');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (20, '2026-05-21', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:22');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (21, '2026-05-20', 'Morning', 'Sunil Nambiar', 'Farm / OTR', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:22');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (22, '2026-05-19', 'Morning', 'Sunil Nambiar', 'Two Wheeler', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:22');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (23, '2026-05-18', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:22');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (24, '2026-05-17', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:22');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (25, '2026-05-16', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:22');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (26, '2026-05-23', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:41');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (27, '2026-05-22', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:41');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (28, '2026-05-21', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:41');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (29, '2026-05-20', 'Morning', 'Sunil Nambiar', 'Farm / OTR', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:41');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (30, '2026-05-19', 'Morning', 'Sunil Nambiar', 'Two Wheeler', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:41');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (31, '2026-05-18', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:41');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (32, '2026-05-17', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:41');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (33, '2026-05-16', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:41');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (34, '2026-05-23', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:59');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (35, '2026-05-22', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:59');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (36, '2026-05-21', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:59');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (37, '2026-05-20', 'Morning', 'Sunil Nambiar', 'Farm / OTR', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:59');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (38, '2026-05-19', 'Morning', 'Sunil Nambiar', 'Two Wheeler', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:59');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (39, '2026-05-18', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:59');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (40, '2026-05-17', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:13:59');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (41, '2026-05-16', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:13:59');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (42, '2026-05-23', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:14:21');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (43, '2026-05-22', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:14:21');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (44, '2026-05-21', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:14:21');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (45, '2026-05-20', 'Morning', 'Sunil Nambiar', 'Farm / OTR', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:14:21');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (46, '2026-05-19', 'Morning', 'Sunil Nambiar', 'Two Wheeler', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:14:21');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (47, '2026-05-18', 'Morning', 'Sunil Nambiar', 'PCR Car', 200, 190, 10, NULL, 'Demo QC log [demo]', '2026-05-23 19:14:21');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (48, '2026-05-17', 'Morning', 'Sunil Nambiar', 'PCR SUV', 200, 192, 8, 'Sidewall blemish', 'Demo QC log [demo]', '2026-05-23 19:14:21');
INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (49, '2026-05-16', 'Morning', 'Sunil Nambiar', 'TBR Truck', 200, 191, 9, NULL, 'Demo QC log [demo]', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `qc_inspections`;
CREATE TABLE `qc_inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curing_entry_id` int(11) NOT NULL,
  `batch_code` varchar(40) NOT NULL,
  `tyre_type` varchar(120) NOT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `production_date` date NOT NULL,
  `production_shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `produced_qty` int(11) NOT NULL DEFAULT 0,
  `inspected_qty` int(11) NOT NULL DEFAULT 0,
  `passed_qty` int(11) NOT NULL DEFAULT 0,
  `rejected_qty` int(11) NOT NULL DEFAULT 0,
  `rework_qty` int(11) NOT NULL DEFAULT 0,
  `inspector_name` varchar(150) NOT NULL,
  `inspection_date` date NOT NULL,
  `inspection_shift` varchar(20) NOT NULL DEFAULT 'Morning',
  `qc_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qc_curing` (`curing_entry_id`),
  KEY `idx_qc_insp_date` (`inspection_date`),
  KEY `idx_qc_batch` (`batch_code`),
  CONSTRAINT `fk_qc_curing` FOREIGN KEY (`curing_entry_id`) REFERENCES `curing_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `quality_checks`;
CREATE TABLE `quality_checks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `production_id` int(11) NOT NULL,
  `production_order_id` int(11) DEFAULT NULL,
  `inspection_date` date NOT NULL,
  `inspector_name` varchar(120) NOT NULL,
  `passed_qty` int(11) NOT NULL,
  `failed_qty` int(11) NOT NULL,
  `quality_status` enum('Pass','Fail') NOT NULL DEFAULT 'Pass',
  `defects` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_qc_production` (`production_id`),
  KEY `idx_qc_prod_order` (`production_order_id`),
  CONSTRAINT `fk_qc_production` FOREIGN KEY (`production_id`) REFERENCES `production` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `raw_materials`;
CREATE TABLE `raw_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_code` varchar(40) DEFAULT NULL,
  `material_name` varchar(120) NOT NULL,
  `category` varchar(80) NOT NULL DEFAULT 'General',
  `unit` varchar(20) NOT NULL,
  `stock_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_stock_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `avg_purchase_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `storage_location` varchar(120) NOT NULL DEFAULT 'Main Store',
  `remarks` varchar(500) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_material_stock` (`stock_qty`),
  KEY `fk_material_supplier` (`supplier_id`),
  CONSTRAINT `fk_material_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (1, 'RM-1', 'Natural Rubber', 'General', 'kg', '4460.00', '1200.00', '0.00', '0.00', 'Main Store', NULL, 'Active', 1, '2026-05-14 17:43:38');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (2, 'RM-2', 'Carbon Black', 'General', 'kg', '107009787.97', '800.00', '0.00', '0.00', 'Main Store', NULL, 'Active', 2, '2026-05-14 17:43:38');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (3, '677', 'gaga', 'Rubber', 'kg', '10000.00', '49.93', '0.00', '0.00', 'Main Store', NULL, 'Active', 2, '2026-05-22 18:08:26');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (4, '677', 'gaga', 'Rubber', 'kg', '10000.00', '49.93', '0.00', '0.00', 'Main Store', NULL, 'Active', 2, '2026-05-22 18:08:30');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (5, 'RM-CHEM', 'Chemicals', 'Chemicals', 'kg', '436354.99', '0.00', '0.00', '675.61', 'Store-C1', NULL, 'Active', NULL, '2026-05-23 17:19:25');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (6, 'RM-PACK', 'Packaging', 'Packaging', 'piece', '5000.00', '500.00', '0.00', '0.00', 'Store-D1', NULL, 'Active', NULL, '2026-05-23 17:19:25');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (97, 'RM-001', 'Natural Rubber', 'Rubber', 'kg', '4400.00', '800.00', '12000.00', '0.00', 'Store-A', NULL, 'Active', 63, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (98, 'RM-002', 'Carbon Black', 'Fillers', 'kg', '3335.00', '500.00', '10000.00', '0.00', 'Store-B', NULL, 'Active', 64, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (99, 'RM-003', 'Sulphur', 'Chemicals', 'kg', '920.00', '100.00', '2000.00', '0.00', 'Store-C', NULL, 'Active', 65, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (100, 'RM-004', 'Nylon Cord', 'Reinforcement', 'kg', '2065.00', '300.00', '5000.00', '0.00', 'Store-D', NULL, 'Active', 66, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (101, 'RM-005', 'Steel Wire', 'Reinforcement', 'kg', '2492.00', '400.00', '6000.00', '0.00', 'Store-A', NULL, 'Active', 67, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (102, 'RM-006', 'Fabric Layer', 'Reinforcement', 'm', '1358.00', '150.00', '3000.00', '3.21', 'Store-B', NULL, 'Active', 68, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (103, 'RM-007', 'Synthetic Rubber', 'Rubber', 'kg', '1846.00', '250.00', '5000.00', '0.00', 'Store-C', NULL, 'Active', 69, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (104, 'RM-008', 'Antioxidant MB', 'Chemicals', 'kg', '653.00', '50.00', '1000.00', '0.00', 'Store-D', NULL, 'Active', 70, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (105, 'RM-009', 'Zinc Oxide', 'Chemicals', 'kg', '820.00', '80.00', '1500.00', '0.00', 'Store-A', NULL, 'Active', 71, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (106, 'RM-010', 'Bead Wire', 'Reinforcement', 'kg', '1527.00', '200.00', '4000.00', '0.00', 'Store-B', NULL, 'Active', 72, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (107, 'RM-011', 'Inner Liner Compound', 'Rubber', 'kg', '804.00', '180.00', '3500.00', '0.00', 'Store-C', NULL, 'Active', 63, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (108, 'RM-012', 'Tread Compound', 'Rubber', 'kg', '1196.00', '220.00', '4500.00', '0.00', 'Store-D', NULL, 'Active', 64, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (109, 'RM-013', 'Solvent Oil', 'Chemicals', 'L', '238.00', '60.00', '1200.00', '0.00', 'Store-A', NULL, 'Active', 65, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (110, 'RM-014', 'Packing Carton', 'Packaging', 'piece', '8000.00', '1000.00', '20000.00', '0.00', 'Store-B', NULL, 'Active', 66, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (111, 'RM-015', 'Process Oil', 'Chemicals', 'L', '85.00', '120.00', '2000.00', '0.00', 'Store-C', NULL, 'Active', 67, '2026-05-23 19:14:20');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `avg_purchase_rate`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (112, 'ch34', 'chemial hard', 'General', 'ltr', '98.00', '0.00', '0.00', '87.00', 'jssd', NULL, 'Active', 65, '2026-05-26 18:46:59');

DROP TABLE IF EXISTS `salaries`;
CREATE TABLE `salaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `month_year` varchar(7) NOT NULL,
  `present_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `paid_leave_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `half_paid_leave_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `unpaid_leave_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `overtime_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `basic` decimal(12,2) NOT NULL,
  `overtime` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'unpaid',
  `hr_payroll_status` varchar(24) NOT NULL DEFAULT 'generated',
  `verified_at` datetime DEFAULT NULL,
  `verified_by` varchar(120) DEFAULT NULL,
  `accounts_batch_id` int(11) DEFAULT NULL,
  `is_draft` tinyint(1) NOT NULL DEFAULT 0,
  `paid_at` datetime DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `hra_percentage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `hra_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_percentage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `pf_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `esi_employee_percentage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `esi_employee_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `esi_employer_percentage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `esi_employer_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medical_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dearness_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `travel_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `special_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_allowances` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_employer_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gratuity_accrual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `leave_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `half_day_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_entry_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_salary_month` (`month_year`),
  KEY `fk_salary_employee` (`employee_id`),
  KEY `idx_salaries_accounts_batch` (`accounts_batch_id`),
  CONSTRAINT `fk_salary_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (10, 111, '2026-05', '24.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '19360.00', '19360.00', 'paid', 'sent_to_accounts', NULL, NULL, 1, 0, NULL, '2026-05-23 19:14:21', '2026-05-23 19:14:21', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '22000.00', '2640.00');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (11, 112, '2026-05', '24.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '28160.00', '28160.00', 'paid', 'sent_to_accounts', NULL, NULL, 1, 0, NULL, '2026-05-23 19:14:21', '2026-05-23 19:14:21', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '32000.00', '3840.00');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (12, 114, '2026-05', '24.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '20240.00', '20240.00', 'paid', 'sent_to_accounts', NULL, NULL, 1, 0, NULL, '2026-05-23 19:14:21', '2026-05-23 19:14:21', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '23000.00', '2760.00');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (13, 117, '2026-05', '24.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '19712.00', '19712.00', 'paid', 'sent_to_accounts', NULL, NULL, 1, 0, NULL, '2026-05-23 19:14:21', '2026-05-23 19:14:21', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '22400.00', '2688.00');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (14, 119, '2026-05', '24.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '22880.00', '22880.00', 'paid', 'sent_to_accounts', NULL, NULL, 1, 0, NULL, '2026-05-23 19:14:21', '2026-05-23 19:14:21', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '26000.00', '3120.00');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (15, 120, '2026-05', '24.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '25080.00', '25080.00', 'paid', 'sent_to_accounts', NULL, NULL, 1, 0, NULL, '2026-05-23 19:14:21', '2026-05-23 19:14:21', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '28500.00', '3420.00');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (16, 121, '2026-05', '24.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '26400.00', '26400.00', 'paid', 'sent_to_accounts', NULL, NULL, 1, 0, NULL, '2026-05-23 19:14:21', '2026-05-23 19:14:21', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '30000.00', '3600.00');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (17, 114, '2026-05', '25.00', '0.00', '0.00', '0.00', '10.00', '1104.90', '12650.00', '0.00', '0.00', '5680.98', '5680.98', 'paid', 'sent_to_accounts', NULL, NULL, 1, 0, '2026-05-29 18:01:57', '2026-05-29 17:04:55', '2026-05-29 17:04:55', '40.00', '0.00', '12.00', '1518.00', '0.75', '103.16', '3.25', '447.03', '0.00', '0.00', '0.00', '0.00', '0.00', '1518.00', '0.00', '0.00', '5303.64', '883.94', '265.18', '13754.90', '8073.92');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `amount_paid`, `payment_status`, `hr_payroll_status`, `verified_at`, `verified_by`, `accounts_batch_id`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (18, 113, '2026-05', '25.00', '0.00', '0.00', '0.00', '10.00', '1032.90', '11825.00', '0.00', '0.00', '5310.55', '0.00', 'unpaid', 'generated', NULL, NULL, NULL, 0, NULL, '2026-05-29 18:36:30', '2026-05-29 18:36:30', '40.00', '0.00', '12.00', '1419.00', '0.75', '96.43', '3.25', '417.88', '0.00', '0.00', '0.00', '0.00', '0.00', '1419.00', '0.00', '0.00', '4957.74', '826.29', '247.89', '12857.90', '7547.35');

DROP TABLE IF EXISTS `salary_increments`;
CREATE TABLE `salary_increments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `old_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `new_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `increment_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `increment_percentage` decimal(8,2) NOT NULL DEFAULT 0.00,
  `effective_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_increment_employee` (`employee_id`),
  CONSTRAINT `fk_increment_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `salary_increments` (`id`, `employee_id`, `old_salary`, `new_salary`, `increment_amount`, `increment_percentage`, `effective_date`, `reason`, `created_at`) VALUES (1, 116, '21800.00', '23980.00', '2180.00', '10.00', '2026-05-23', '', '2026-05-23 19:26:01');

DROP TABLE IF EXISTS `sales_customer_notes`;
CREATE TABLE `sales_customer_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `note_text` text NOT NULL,
  `created_by` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sales_note_customer` (`customer_id`),
  CONSTRAINT `fk_sales_note_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sales_customers`;
CREATE TABLE `sales_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(30) NOT NULL,
  `company_name` varchar(180) NOT NULL,
  `customer_type` enum('Dealer','Distributor','Retailer','Industrial Buyer') NOT NULL DEFAULT 'Dealer',
  `contact_person` varchar(120) DEFAULT NULL,
  `gst_number` varchar(40) DEFAULT NULL,
  `pan_number` varchar(20) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `billing_address` varchar(255) DEFAULT NULL,
  `shipping_address` varchar(255) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `pincode` varchar(12) DEFAULT NULL,
  `credit_limit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_terms` varchar(80) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `remarks` varchar(500) DEFAULT NULL,
  `dispatch_customer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_cust_code` (`customer_code`),
  KEY `idx_sales_cust_status` (`status`),
  KEY `idx_sales_cust_company` (`company_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales_customers` (`id`, `customer_code`, `company_name`, `customer_type`, `contact_person`, `gst_number`, `pan_number`, `phone`, `email`, `billing_address`, `shipping_address`, `city`, `state`, `pincode`, `credit_limit`, `payment_terms`, `status`, `remarks`, `dispatch_customer_id`, `created_at`, `updated_at`) VALUES (1, 'CUS-2026-0001', 'Metro Tyre Traders', 'Distributor', 'Rajesh Mehta', '29AABCM1234F1Z5', NULL, '9876500101', NULL, 'Metro Tyre Traders, Mumbai', NULL, 'Mumbai', 'Maharashtra', NULL, '500000.00', 'Net 30 days', 'Active', NULL, NULL, '2026-05-24 17:49:57', '2026-05-24 17:49:57');
INSERT INTO `sales_customers` (`id`, `customer_code`, `company_name`, `customer_type`, `contact_person`, `gst_number`, `pan_number`, `phone`, `email`, `billing_address`, `shipping_address`, `city`, `state`, `pincode`, `credit_limit`, `payment_terms`, `status`, `remarks`, `dispatch_customer_id`, `created_at`, `updated_at`) VALUES (2, 'CUS-2026-0002', 'Southern Auto Mart', 'Dealer', 'Priya Nair', '33AABCS5678G1Z2', NULL, '9876500202', NULL, 'Southern Auto Mart, Chennai', NULL, 'Chennai', 'Tamil Nadu', NULL, '500000.00', 'Net 30 days', 'Active', NULL, NULL, '2026-05-24 17:49:57', '2026-05-24 17:49:57');
INSERT INTO `sales_customers` (`id`, `customer_code`, `company_name`, `customer_type`, `contact_person`, `gst_number`, `pan_number`, `phone`, `email`, `billing_address`, `shipping_address`, `city`, `state`, `pincode`, `credit_limit`, `payment_terms`, `status`, `remarks`, `dispatch_customer_id`, `created_at`, `updated_at`) VALUES (3, 'CUS-2026-0003', 'Bharat Wheels', 'Retailer', 'Amit Sharma', '07AABCB9012H1Z3', NULL, '9876500303', NULL, 'Bharat Wheels, Delhi', NULL, 'Delhi', 'Delhi', NULL, '500000.00', 'Net 30 days', 'Active', NULL, NULL, '2026-05-24 17:49:57', '2026-05-24 17:49:57');
INSERT INTO `sales_customers` (`id`, `customer_code`, `company_name`, `customer_type`, `contact_person`, `gst_number`, `pan_number`, `phone`, `email`, `billing_address`, `shipping_address`, `city`, `state`, `pincode`, `credit_limit`, `payment_terms`, `status`, `remarks`, `dispatch_customer_id`, `created_at`, `updated_at`) VALUES (4, 'CUS-2026-0004', 'Punjab Tyres Distributor', 'Distributor', 'Harpreet Singh', '03AABCP3456J1Z4', NULL, '9876500404', NULL, 'Punjab Tyres Distributor, Ludhiana', NULL, 'Ludhiana', 'Punjab', NULL, '500000.00', 'Net 30 days', 'Active', NULL, NULL, '2026-05-24 17:49:57', '2026-05-24 17:49:57');

DROP TABLE IF EXISTS `sales_dispatch_allocations`;
CREATE TABLE `sales_dispatch_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_order_id` int(11) NOT NULL,
  `sales_order_item_id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sda_order` (`sales_order_id`),
  KEY `idx_sda_dispatch` (`dispatch_id`),
  KEY `fk_sda_item` (`sales_order_item_id`),
  CONSTRAINT `fk_sda_item` FOREIGN KEY (`sales_order_item_id`) REFERENCES `sales_order_items` (`id`),
  CONSTRAINT `fk_sda_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales_dispatch_allocations` (`id`, `sales_order_id`, `sales_order_item_id`, `dispatch_id`, `qty`, `created_at`) VALUES (1, 7, 7, 15, 300, '2026-05-25 17:10:05');
INSERT INTO `sales_dispatch_allocations` (`id`, `sales_order_id`, `sales_order_item_id`, `dispatch_id`, `qty`, `created_at`) VALUES (2, 6, 6, 16, 1, '2026-05-25 17:54:43');
INSERT INTO `sales_dispatch_allocations` (`id`, `sales_order_id`, `sales_order_item_id`, `dispatch_id`, `qty`, `created_at`) VALUES (3, 8, 8, 17, 87, '2026-05-28 18:19:16');

DROP TABLE IF EXISTS `sales_dispatch_queue`;
CREATE TABLE `sales_dispatch_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_order_id` int(11) NOT NULL,
  `sales_order_item_id` int(11) NOT NULL,
  `so_number` varchar(40) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `company_name` varchar(180) NOT NULL,
  `tyre_type` varchar(120) NOT NULL,
  `ordered_qty` int(11) NOT NULL DEFAULT 0,
  `available_qty` int(11) NOT NULL DEFAULT 0,
  `pending_qty` int(11) NOT NULL DEFAULT 0,
  `dispatchable_qty` int(11) NOT NULL DEFAULT 0,
  `stock_status` enum('READY','PARTIAL STOCK','PRODUCTION REQUIRED') NOT NULL DEFAULT 'PRODUCTION REQUIRED',
  `queue_status` enum('Waiting for Production','Ready for Dispatch','Partially Ready','Completed') NOT NULL DEFAULT 'Waiting for Production',
  `dispatch_readiness` varchar(60) NOT NULL DEFAULT 'Waiting for Production',
  `expected_dispatch_date` date DEFAULT NULL,
  `order_date` date NOT NULL,
  `order_priority` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sdq_item` (`sales_order_item_id`),
  KEY `idx_sdq_order` (`sales_order_id`),
  KEY `idx_sdq_queue_status` (`queue_status`),
  KEY `idx_sdq_stock_status` (`stock_status`),
  KEY `fk_sdq_customer` (`customer_id`),
  CONSTRAINT `fk_sdq_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`id`),
  CONSTRAINT `fk_sdq_item` FOREIGN KEY (`sales_order_item_id`) REFERENCES `sales_order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sdq_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales_dispatch_queue` (`id`, `sales_order_id`, `sales_order_item_id`, `so_number`, `customer_id`, `company_name`, `tyre_type`, `ordered_qty`, `available_qty`, `pending_qty`, `dispatchable_qty`, `stock_status`, `queue_status`, `dispatch_readiness`, `expected_dispatch_date`, `order_date`, `order_priority`, `created_at`, `updated_at`) VALUES (91, 4, 4, 'SO-20260524-004', 3, 'Bharat Wheels', 'Farm / OTR', 80, 67, 80, 67, 'PARTIAL STOCK', 'Partially Ready', 'Partial — 67 of 80 ready', '2026-06-06', '2026-05-22', 'Medium', '2026-05-25 17:44:43', '2026-05-25 17:44:43');
INSERT INTO `sales_dispatch_queue` (`id`, `sales_order_id`, `sales_order_item_id`, `so_number`, `customer_id`, `company_name`, `tyre_type`, `ordered_qty`, `available_qty`, `pending_qty`, `dispatchable_qty`, `stock_status`, `queue_status`, `dispatch_readiness`, `expected_dispatch_date`, `order_date`, `order_priority`, `created_at`, `updated_at`) VALUES (92, 3, 3, 'SO-20260524-003', 2, 'Southern Auto Mart', 'Two Wheeler', 300, 431, 300, 300, 'READY', 'Ready for Dispatch', 'Ready for Dispatch', '2026-06-05', '2026-05-21', 'Medium', '2026-05-25 17:44:43', '2026-05-25 17:44:43');
INSERT INTO `sales_dispatch_queue` (`id`, `sales_order_id`, `sales_order_item_id`, `so_number`, `customer_id`, `company_name`, `tyre_type`, `ordered_qty`, `available_qty`, `pending_qty`, `dispatchable_qty`, `stock_status`, `queue_status`, `dispatch_readiness`, `expected_dispatch_date`, `order_date`, `order_priority`, `created_at`, `updated_at`) VALUES (93, 2, 2, 'SO-20260524-002', 1, 'Metro Tyre Traders', 'TBR Truck', 200, 10, 200, 10, 'PARTIAL STOCK', 'Partially Ready', 'Partial — 10 of 200 ready', '2026-06-04', '2026-05-20', 'Medium', '2026-05-25 17:44:43', '2026-05-25 17:44:43');
INSERT INTO `sales_dispatch_queue` (`id`, `sales_order_id`, `sales_order_item_id`, `so_number`, `customer_id`, `company_name`, `tyre_type`, `ordered_qty`, `available_qty`, `pending_qty`, `dispatchable_qty`, `stock_status`, `queue_status`, `dispatch_readiness`, `expected_dispatch_date`, `order_date`, `order_priority`, `created_at`, `updated_at`) VALUES (100, 5, 5, 'SO-20260524-005', 4, 'Punjab Tyres Distributor', 'PCR SUV', 150, 265, 150, 150, 'READY', 'Ready for Dispatch', 'Ready for Dispatch', '2026-06-07', '2026-05-23', 'Medium', '2026-05-28 18:11:03', '2026-05-28 18:11:03');
INSERT INTO `sales_dispatch_queue` (`id`, `sales_order_id`, `sales_order_item_id`, `so_number`, `customer_id`, `company_name`, `tyre_type`, `ordered_qty`, `available_qty`, `pending_qty`, `dispatchable_qty`, `stock_status`, `queue_status`, `dispatch_readiness`, `expected_dispatch_date`, `order_date`, `order_priority`, `created_at`, `updated_at`) VALUES (110, 1, 1, 'SO-20260524-001', 1, 'Metro Tyre Traders', 'PCR Car', 500, 228, 500, 228, 'PARTIAL STOCK', 'Partially Ready', 'Partial — 228 of 500 ready', '2026-06-03', '2026-05-19', 'Urgent', '2026-05-28 18:19:16', '2026-05-28 18:19:16');

DROP TABLE IF EXISTS `sales_invoice_items`;
CREATE TABLE `sales_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `tyre_type` varchar(120) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT 18.00,
  `line_subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_gst` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_sales_ii_inv` (`invoice_id`),
  CONSTRAINT `fk_sales_ii_inv` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales_invoice_items` (`id`, `invoice_id`, `order_item_id`, `tyre_type`, `qty`, `rate`, `gst_percent`, `line_subtotal`, `line_gst`, `line_total`) VALUES (1, 1, 7, 'TBR Truck', 300, '4.78', '18.00', '1434.00', '258.12', '1692.12');
INSERT INTO `sales_invoice_items` (`id`, `invoice_id`, `order_item_id`, `tyre_type`, `qty`, `rate`, `gst_percent`, `line_subtotal`, `line_gst`, `line_total`) VALUES (2, 2, 6, 'PCR SUV', 1, '0.00', '18.00', '0.00', '0.00', '0.00');
INSERT INTO `sales_invoice_items` (`id`, `invoice_id`, `order_item_id`, `tyre_type`, `qty`, `rate`, `gst_percent`, `line_subtotal`, `line_gst`, `line_total`) VALUES (3, 3, 8, 'PCR Car', 87, '8.00', '18.00', '696.00', '125.28', '821.28');

DROP TABLE IF EXISTS `sales_invoices`;
CREATE TABLE `sales_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(40) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `gst_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Partial','Pending','Overdue') NOT NULL DEFAULT 'Pending',
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_inv` (`invoice_no`),
  KEY `idx_sales_inv_customer` (`customer_id`),
  KEY `idx_sales_inv_order` (`order_id`),
  KEY `idx_sales_inv_status` (`payment_status`),
  CONSTRAINT `fk_sales_inv_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`id`),
  CONSTRAINT `fk_sales_inv_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales_invoices` (`id`, `invoice_no`, `customer_id`, `order_id`, `invoice_date`, `due_date`, `subtotal`, `gst_total`, `total_amount`, `amount_paid`, `payment_status`, `remarks`, `created_at`, `updated_at`) VALUES (1, 'INV-20260525-4596', 3, 7, '2026-05-25', '2026-06-24', '1434.00', '258.12', '1692.12', '1692.12', 'Paid', 'Auto from dispatch:15 (DSP-20260525-001)', '2026-05-25 17:10:05', '2026-05-25 18:56:01');
INSERT INTO `sales_invoices` (`id`, `invoice_no`, `customer_id`, `order_id`, `invoice_date`, `due_date`, `subtotal`, `gst_total`, `total_amount`, `amount_paid`, `payment_status`, `remarks`, `created_at`, `updated_at`) VALUES (2, 'INV-20260525-8667', 4, 6, '2026-05-25', '2026-06-24', '0.00', '0.00', '0.00', '0.00', 'Paid', 'Auto from dispatch:16 (DSP-20260525-002)', '2026-05-25 17:54:43', '2026-05-25 18:56:01');
INSERT INTO `sales_invoices` (`id`, `invoice_no`, `customer_id`, `order_id`, `invoice_date`, `due_date`, `subtotal`, `gst_total`, `total_amount`, `amount_paid`, `payment_status`, `remarks`, `created_at`, `updated_at`) VALUES (3, 'INV-20260528-2689', 4, 8, '2026-05-28', '2026-06-27', '696.00', '125.28', '821.28', '821.28', 'Paid', 'Auto from dispatch:17 (DSP-20260528-001)', '2026-05-28 18:19:16', '2026-05-28 19:09:26');

DROP TABLE IF EXISTS `sales_order_items`;
CREATE TABLE `sales_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL DEFAULT 1,
  `tyre_type` varchar(120) NOT NULL,
  `qty_ordered` int(11) NOT NULL DEFAULT 0,
  `qty_dispatched` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT 18.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_gst` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `stock_available` int(11) NOT NULL DEFAULT 0,
  `fulfillment_status` enum('Ready for Dispatch','Production Required') NOT NULL DEFAULT 'Production Required',
  PRIMARY KEY (`id`),
  KEY `idx_sales_oi_order` (`order_id`),
  CONSTRAINT `fk_sales_oi_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales_order_items` (`id`, `order_id`, `line_no`, `tyre_type`, `qty_ordered`, `qty_dispatched`, `rate`, `gst_percent`, `discount_amount`, `line_subtotal`, `line_gst`, `line_total`, `stock_available`, `fulfillment_status`) VALUES (1, 1, 1, 'PCR Car', 500, 0, '3200.00', '18.00', '0.00', '1600000.00', '288000.00', '1888000.00', 228, 'Production Required');
INSERT INTO `sales_order_items` (`id`, `order_id`, `line_no`, `tyre_type`, `qty_ordered`, `qty_dispatched`, `rate`, `gst_percent`, `discount_amount`, `line_subtotal`, `line_gst`, `line_total`, `stock_available`, `fulfillment_status`) VALUES (2, 2, 1, 'TBR Truck', 200, 0, '8500.00', '18.00', '0.00', '1700000.00', '306000.00', '2006000.00', 10, 'Production Required');
INSERT INTO `sales_order_items` (`id`, `order_id`, `line_no`, `tyre_type`, `qty_ordered`, `qty_dispatched`, `rate`, `gst_percent`, `discount_amount`, `line_subtotal`, `line_gst`, `line_total`, `stock_available`, `fulfillment_status`) VALUES (3, 3, 1, 'Two Wheeler', 300, 0, '1100.00', '18.00', '0.00', '330000.00', '59400.00', '389400.00', 431, 'Ready for Dispatch');
INSERT INTO `sales_order_items` (`id`, `order_id`, `line_no`, `tyre_type`, `qty_ordered`, `qty_dispatched`, `rate`, `gst_percent`, `discount_amount`, `line_subtotal`, `line_gst`, `line_total`, `stock_available`, `fulfillment_status`) VALUES (4, 4, 1, 'Farm / OTR', 80, 0, '12000.00', '18.00', '0.00', '960000.00', '172800.00', '1132800.00', 67, 'Production Required');
INSERT INTO `sales_order_items` (`id`, `order_id`, `line_no`, `tyre_type`, `qty_ordered`, `qty_dispatched`, `rate`, `gst_percent`, `discount_amount`, `line_subtotal`, `line_gst`, `line_total`, `stock_available`, `fulfillment_status`) VALUES (5, 5, 1, 'PCR SUV', 150, 0, '3800.00', '18.00', '0.00', '570000.00', '102600.00', '672600.00', 265, 'Ready for Dispatch');
INSERT INTO `sales_order_items` (`id`, `order_id`, `line_no`, `tyre_type`, `qty_ordered`, `qty_dispatched`, `rate`, `gst_percent`, `discount_amount`, `line_subtotal`, `line_gst`, `line_total`, `stock_available`, `fulfillment_status`) VALUES (6, 6, 1, 'PCR SUV', 1, 1, '0.00', '18.00', '0.00', '0.00', '0.00', '0.00', 265, 'Ready for Dispatch');
INSERT INTO `sales_order_items` (`id`, `order_id`, `line_no`, `tyre_type`, `qty_ordered`, `qty_dispatched`, `rate`, `gst_percent`, `discount_amount`, `line_subtotal`, `line_gst`, `line_total`, `stock_available`, `fulfillment_status`) VALUES (7, 7, 1, 'TBR Truck', 300, 300, '4.78', '18.00', '30.00', '1404.00', '252.72', '1656.72', 10, 'Production Required');
INSERT INTO `sales_order_items` (`id`, `order_id`, `line_no`, `tyre_type`, `qty_ordered`, `qty_dispatched`, `rate`, `gst_percent`, `discount_amount`, `line_subtotal`, `line_gst`, `line_total`, `stock_available`, `fulfillment_status`) VALUES (8, 8, 1, 'PCR Car', 87, 87, '8.00', '18.00', '0.00', '696.00', '125.28', '821.28', 228, 'Ready for Dispatch');

DROP TABLE IF EXISTS `sales_orders`;
CREATE TABLE `sales_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `so_number` varchar(40) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
  `status` enum('Pending','In Production','Ready','Partially Dispatched','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `payment_terms` varchar(80) DEFAULT NULL,
  `discount_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `gst_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_so` (`so_number`),
  KEY `idx_sales_ord_customer` (`customer_id`),
  KEY `idx_sales_ord_status` (`status`),
  KEY `idx_sales_ord_date` (`order_date`),
  CONSTRAINT `fk_sales_ord_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `order_date`, `delivery_date`, `priority`, `status`, `payment_terms`, `discount_amount`, `subtotal`, `gst_total`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES (1, 'SO-20260524-001', 1, '2026-05-19', '2026-06-03', 'Urgent', 'Ready', 'Net 30 days', '0.00', '1600000.00', '288000.00', '1888000.00', NULL, NULL, '2026-05-24 17:49:58', '2026-05-24 19:13:33');
INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `order_date`, `delivery_date`, `priority`, `status`, `payment_terms`, `discount_amount`, `subtotal`, `gst_total`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES (2, 'SO-20260524-002', 1, '2026-05-20', '2026-06-04', 'Medium', 'Ready', 'Net 30 days', '0.00', '1700000.00', '306000.00', '2006000.00', NULL, NULL, '2026-05-24 17:49:58', '2026-05-24 17:49:58');
INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `order_date`, `delivery_date`, `priority`, `status`, `payment_terms`, `discount_amount`, `subtotal`, `gst_total`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES (3, 'SO-20260524-003', 2, '2026-05-21', '2026-06-05', 'Medium', 'Ready', 'Net 30 days', '0.00', '330000.00', '59400.00', '389400.00', NULL, NULL, '2026-05-24 17:49:58', '2026-05-24 17:49:58');
INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `order_date`, `delivery_date`, `priority`, `status`, `payment_terms`, `discount_amount`, `subtotal`, `gst_total`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES (4, 'SO-20260524-004', 3, '2026-05-22', '2026-06-06', 'Medium', 'Ready', 'Net 30 days', '0.00', '960000.00', '172800.00', '1132800.00', NULL, NULL, '2026-05-24 17:49:58', '2026-05-24 19:13:33');
INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `order_date`, `delivery_date`, `priority`, `status`, `payment_terms`, `discount_amount`, `subtotal`, `gst_total`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES (5, 'SO-20260524-005', 4, '2026-05-23', '2026-06-07', 'Medium', 'Ready', 'Net 30 days', '0.00', '570000.00', '102600.00', '672600.00', NULL, NULL, '2026-05-24 17:49:58', '2026-05-24 17:49:58');
INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `order_date`, `delivery_date`, `priority`, `status`, `payment_terms`, `discount_amount`, `subtotal`, `gst_total`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES (6, 'SO-20260524-006', 4, '2026-05-24', NULL, 'Medium', 'Completed', NULL, '2.00', '0.00', '0.00', '0.00', 'kkkkkkkks', NULL, '2026-05-24 18:38:10', '2026-05-25 17:54:43');
INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `order_date`, `delivery_date`, `priority`, `status`, `payment_terms`, `discount_amount`, `subtotal`, `gst_total`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES (7, 'SO-20260525-001', 3, '2026-05-25', '2026-05-29', 'Medium', 'Completed', '20', '0.00', '1404.00', '252.72', '1656.72', NULL, NULL, '2026-05-25 16:38:14', '2026-05-25 17:10:05');
INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `order_date`, `delivery_date`, `priority`, `status`, `payment_terms`, `discount_amount`, `subtotal`, `gst_total`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES (8, 'SO-20260528-001', 4, '2026-05-28', NULL, 'Medium', 'Completed', '45', '69.99', '696.00', '125.28', '751.29', NULL, NULL, '2026-05-28 18:16:51', '2026-05-28 18:19:16');

DROP TABLE IF EXISTS `sales_payments`;
CREATE TABLE `sales_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_mode` enum('Cash','Bank Transfer','UPI','Cheque') NOT NULL DEFAULT 'Bank Transfer',
  `reference_no` varchar(80) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sales_pay_customer` (`customer_id`),
  KEY `idx_sales_pay_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_pay_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`id`),
  CONSTRAINT `fk_sales_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales_payments` (`id`, `customer_id`, `invoice_id`, `payment_date`, `amount`, `payment_mode`, `reference_no`, `remarks`, `created_at`) VALUES (1, 3, 1, '2026-05-25', '1692.10', 'UPI', 'upi', NULL, '2026-05-25 17:12:38');
INSERT INTO `sales_payments` (`id`, `customer_id`, `invoice_id`, `payment_date`, `amount`, `payment_mode`, `reference_no`, `remarks`, `created_at`) VALUES (2, 3, 1, '2026-05-25', '0.01', 'Cash', NULL, NULL, '2026-05-25 17:20:29');
INSERT INTO `sales_payments` (`id`, `customer_id`, `invoice_id`, `payment_date`, `amount`, `payment_mode`, `reference_no`, `remarks`, `created_at`) VALUES (3, 4, 3, '2026-05-28', '450.00', 'UPI', NULL, NULL, '2026-05-28 18:22:05');
INSERT INTO `sales_payments` (`id`, `customer_id`, `invoice_id`, `payment_date`, `amount`, `payment_mode`, `reference_no`, `remarks`, `created_at`) VALUES (4, 4, 3, '2026-05-28', '371.28', 'Cash', NULL, NULL, '2026-05-28 19:09:26');

DROP TABLE IF EXISTS `schema_migrations`;
CREATE TABLE `schema_migrations` (
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL DEFAULT 1,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('000_create_database.sql', 1, '2026-05-17 19:39:05');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('001_core_erp_tables.sql', 1, '2026-05-17 19:39:05');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('002_department_hierarchy.sql', 1, '2026-05-17 19:39:05');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('003_attendance_update.sql', 1, '2026-05-17 19:39:05');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('004_salary_structure_update.sql', 1, '2026-05-17 19:39:05');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('005_create_payroll_tables.sql', 1, '2026-05-17 19:39:05');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('006_leave_module_update.sql', 1, '2026-05-17 19:39:05');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('007_employee_department_links.sql', 1, '2026-05-17 19:39:05');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('008_hr_notifications_and_leave_audit.sql', 2, '2026-05-20 19:38:44');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('009_production_workflow.sql', 3, '2026-05-21 17:19:47');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('010_production_orders_workflow.sql', 4, '2026-05-21 17:40:07');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('011_production_stage_logs.sql', 5, '2026-05-21 18:13:33');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('012_inventory_warehouse_module.sql', 6, '2026-05-23 17:19:25');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('013_dispatch_logistics_module.sql', 6, '2026-05-23 17:19:25');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('014_production_entry_tables.sql', 6, '2026-05-23 17:19:25');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('015_quality_control_module.sql', 7, '2026-05-23 18:00:17');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('016_machine_master_assignments.sql', 8, '2026-05-23 18:39:08');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('017_sales_crm_module.sql', 9, '2026-05-24 17:40:04');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('018_sales_manager_user.sql', 10, '2026-05-24 17:58:30');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('019_crm_dispatch_linkage.sql', 11, '2026-05-24 19:13:32');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('020_dispatch_vehicle_fields.sql', 12, '2026-05-24 20:25:34');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('021_inventory_purchase_inward.sql', 13, '2026-05-26 19:07:59');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('022_purchase_payments.sql', 14, '2026-05-26 19:21:19');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('023_salary_payroll_accounts.sql', 15, '2026-05-29 16:33:01');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('024_salary_payments_v2.sql', 16, '2026-05-29 17:30:55');
INSERT INTO `schema_migrations` (`migration`, `batch`, `applied_at`) VALUES ('025_user_status_locked.sql', 17, '2026-05-30 23:32:01');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3808 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (1, 'company_name', 'Ralson India Private Limited');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (104, 'employee_gross_backfill_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (135, 'employee_payroll_auto_legacy_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (292, 'erp_collation_utf8mb4_general_ci_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (407, 'attendance_fk_cascade_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (488, 'leave_auto_approve_enabled', '0');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (489, 'leave_min_present_pct', '50');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (1601, 'tyre_erp_demo_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3589, 'company_logo', '');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3590, 'gst_number', '');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3591, 'company_address', '');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3592, 'company_email', '');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3593, 'company_phone', '');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3594, 'financial_year_start', '04-01');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3595, 'currency', 'INR');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3596, 'timezone', 'Asia/Kolkata');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3597, 'password_min_length', '6');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3598, 'password_require_upper', '0');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3599, 'login_max_attempts', '5');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3600, 'session_timeout_minutes', '480');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3601, 'login_lockout_minutes', '15');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3651, 'default_tax_rate', '18');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3658, 'notify_low_stock', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (3659, 'notify_backup', '1');

DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `adjust_date` date NOT NULL,
  `material_id` int(11) NOT NULL,
  `previous_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actual_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `difference_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(80) NOT NULL,
  `operator_name` varchar(150) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_adj_date` (`adjust_date`),
  KEY `idx_adj_material` (`material_id`),
  CONSTRAINT `fk_adj_material` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `stock_inward`;
CREATE TABLE `stock_inward` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pinv_no` varchar(40) DEFAULT NULL,
  `inward_date` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(80) DEFAULT NULL,
  `challan_no` varchar(80) DEFAULT NULL,
  `material_id` int(11) NOT NULL,
  `batch_no` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `transport_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loading_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `gst_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Unpaid',
  `payment_mode` varchar(40) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `payment_ref` varchar(80) DEFAULT NULL,
  `warehouse_location` varchar(120) DEFAULT NULL,
  `received_by` varchar(150) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inward_date` (`inward_date`),
  KEY `idx_inward_material` (`material_id`),
  KEY `idx_inward_supplier` (`supplier_id`),
  KEY `idx_inward_pinv` (`pinv_no`),
  KEY `idx_inward_payment_status` (`payment_status`),
  CONSTRAINT `fk_inward_material` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`id`),
  CONSTRAINT `fk_inward_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (1, NULL, '2026-05-22', 1, NULL, NULL, 2, NULL, NULL, '7887.97', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-22 18:17:43');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (2, NULL, '2026-05-22', 2, NULL, NULL, 2, NULL, NULL, '120000000.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-22 18:18:06');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (53, NULL, '2026-05-03', 1, 'PINV-2026-1000', NULL, 97, 'BIN-DEMO-1', NULL, '200.00', '85.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (54, NULL, '2026-05-04', 1, 'PINV-2026-1001', NULL, 98, 'BIN-DEMO-2', NULL, '235.00', '86.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (55, NULL, '2026-05-05', 1, 'PINV-2026-1002', NULL, 99, 'BIN-DEMO-3', NULL, '270.00', '87.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (56, NULL, '2026-05-06', 1, 'PINV-2026-1003', NULL, 100, 'BIN-DEMO-4', NULL, '305.00', '88.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (57, NULL, '2026-05-07', 1, 'PINV-2026-1004', NULL, 101, 'BIN-DEMO-5', NULL, '340.00', '89.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (58, NULL, '2026-05-08', 1, 'PINV-2026-1005', NULL, 102, 'BIN-DEMO-6', NULL, '375.00', '90.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (59, NULL, '2026-05-09', 1, 'PINV-2026-1006', NULL, 103, 'BIN-DEMO-7', NULL, '410.00', '91.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (60, NULL, '2026-05-10', 1, 'PINV-2026-1007', NULL, 104, 'BIN-DEMO-8', NULL, '445.00', '92.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (61, NULL, '2026-05-11', 1, 'PINV-2026-1008', NULL, 105, 'BIN-DEMO-9', NULL, '480.00', '93.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (62, NULL, '2026-05-12', 1, 'PINV-2026-1009', NULL, 106, 'BIN-DEMO-10', NULL, '515.00', '94.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Unpaid', NULL, NULL, NULL, NULL, 'Pooja Mehta', 'Demo inward [demo]', '2026-05-23 19:14:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (63, 'PINV-20260526-001', '2026-05-26', 73, 'pidnsdsfn782', '333534ffdgg', 102, '34', NULL, '89.00', '49.00', '18.00', '78.00', '98.00', '90.00', '9.98', '4361.00', '784.98', '5402.00', '5402.00', 'Paid', NULL, '2026-05-30', NULL, 'Store-B', 'invmanager', NULL, '2026-05-26 19:23:20');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (64, 'PINV-20260526-002', '2026-05-26', 73, 'pidnsdsfn782', '4364642', 5, '4445', NULL, '60.00', '89.00', '18.00', '67.00', '87.00', '98.00', '99.98', '5340.00', '961.20', '6453.22', '0.00', 'Unpaid', NULL, '2026-05-30', NULL, 'Store-C1', 'invmanager', NULL, '2026-05-26 19:29:19');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (65, 'PINV-20260526-003', '2026-05-26', 73, 'pidnsdsfn782', '4364642', 5, '4445', NULL, '60.00', '89.00', '18.00', '67.00', '87.00', '98.00', '99.98', '5340.00', '961.20', '6453.22', '50.00', 'Partial', NULL, '2026-05-30', NULL, 'Store-C1', 'invmanager', NULL, '2026-05-26 19:29:23');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (66, 'PINV-20260526-004', '2026-05-26', 73, 'pidnsdsfn782', '4364642', 5, '4445', NULL, '60.00', '89.00', '18.00', '67.00', '87.00', '98.00', '99.98', '5340.00', '961.20', '6453.22', '6453.22', 'Paid', NULL, '2026-05-30', NULL, 'Store-C1', 'invmanager', NULL, '2026-05-26 19:29:45');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (67, 'PINV-20260526-005', '2026-05-26', 73, '762627e', 'whhuwyr78', 112, NULL, NULL, '98.00', '87.00', '10.00', '97.00', '98.00', '98.00', '89.00', '8526.00', '852.60', '9582.60', '9582.60', 'Paid', NULL, NULL, NULL, 'jssd', 'invmanager', NULL, '2026-05-26 19:34:00');
INSERT INTO `stock_inward` (`id`, `pinv_no`, `inward_date`, `supplier_id`, `invoice_no`, `challan_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `gst_percent`, `transport_charges`, `loading_charges`, `other_charges`, `discount_amount`, `subtotal`, `gst_amount`, `total_amount`, `paid_amount`, `payment_status`, `payment_mode`, `due_date`, `payment_ref`, `warehouse_location`, `received_by`, `remarks`, `created_at`) VALUES (68, 'PINV-20260526-006', '2026-05-26', 73, '4534672', '433462', 5, '464', NULL, '435434.99', '677.00', '17.95', '45.00', '334.00', '456.00', '10.00', '294789488.23', '52914713.14', '347705026.37', '347705026.37', 'Paid', NULL, NULL, NULL, 'Store-C1', 'invmanager', NULL, '2026-05-26 19:36:34');

DROP TABLE IF EXISTS `stock_usage`;
CREATE TABLE `stock_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usage_date` date NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `usage_type` enum('production','manual') NOT NULL DEFAULT 'manual',
  `department` varchar(40) DEFAULT NULL,
  `usage_reason` varchar(80) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usage_date` (`usage_date`),
  KEY `idx_usage_material` (`material_id`),
  KEY `idx_usage_type` (`usage_type`),
  CONSTRAINT `fk_usage_material` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (1, '2026-05-22', 2, '13000000.00', 'manual', 'Building', NULL, NULL, NULL, NULL, '2026-05-22 18:18:36');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (2, '2026-05-23', 1, '270.00', 'production', 'Mixing', 'production', 5, 'Auto from mixing entry #5', 'System', '2026-05-23 18:17:09');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (3, '2026-05-23', 2, '150.00', 'production', 'Mixing', 'production', 5, 'Auto from mixing entry #5', 'System', '2026-05-23 18:17:09');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (4, '2026-05-23', 5, '30.00', 'production', 'Mixing', 'production', 5, 'Auto from mixing entry #5', 'System', '2026-05-23 18:17:09');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (5, '2026-05-23', 1, '270.00', 'production', 'Mixing', 'production', 6, 'Auto from mixing entry #6', 'System', '2026-05-23 18:17:19');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (6, '2026-05-23', 2, '150.00', 'production', 'Mixing', 'production', 6, 'Auto from mixing entry #6', 'System', '2026-05-23 18:17:19');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (7, '2026-05-23', 5, '30.00', 'production', 'Mixing', 'production', 6, 'Auto from mixing entry #6', 'System', '2026-05-23 18:17:19');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (58, '2026-05-11', 100, '40.00', 'production', 'Mixing', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:20');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (59, '2026-05-12', 101, '48.00', 'manual', 'Building', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:20');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (60, '2026-05-13', 102, '56.00', 'production', 'Curing', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:20');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (61, '2026-05-14', 103, '64.00', 'manual', 'Mixing', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:20');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (62, '2026-05-15', 104, '72.00', 'production', 'Building', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:20');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (63, '2026-05-16', 105, '80.00', 'manual', 'Curing', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:20');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (64, '2026-05-17', 106, '88.00', 'production', 'Mixing', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:21');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (65, '2026-05-18', 107, '96.00', 'manual', 'Building', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:21');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (66, '2026-05-19', 108, '104.00', 'production', 'Curing', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:21');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (67, '2026-05-20', 109, '112.00', 'manual', 'Mixing', 'Production consumption', NULL, 'Demo usage [demo]', 'System', '2026-05-23 19:14:21');

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `gst_number` varchar(40) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `materials_supplied` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (1, 'Punjab Rubber Corp', 'Harjit Singh', '9811111111', 'harjit@rubber.local', 'Ludhiana', NULL, 'Active', NULL, '2026-05-14 17:43:38');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (2, 'ChemTech Industries', 'K. Mehta', '9822222222', 'mehta@chem.local', 'Delhi NCR', NULL, 'Active', NULL, '2026-05-14 17:43:38');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (63, 'Punjab Rubber Corp [demo]', 'Contact 1', '9812300100', 'vendor1@demo.supply', 'Industrial Estate, Plot 10, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (64, 'ChemTech Industries [demo]', 'Contact 2', '9812300101', 'vendor2@demo.supply', 'Industrial Estate, Plot 11, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (65, 'Bharat Chemicals [demo]', 'Contact 3', '9812300102', 'vendor3@demo.supply', 'Industrial Estate, Plot 12, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (66, 'Metro Industrial Supply [demo]', 'Contact 4', '9812300103', 'vendor4@demo.supply', 'Industrial Estate, Plot 13, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (67, 'Gujarat Carbon Works [demo]', 'Contact 5', '9812300104', 'vendor5@demo.supply', 'Industrial Estate, Plot 14, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (68, 'Southern Cord Suppliers [demo]', 'Contact 6', '9812300105', 'vendor6@demo.supply', 'Industrial Estate, Plot 15, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (69, 'Himalaya Elastomers [demo]', 'Contact 7', '9812300106', 'vendor7@demo.supply', 'Industrial Estate, Plot 16, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (70, 'Steel Wire India [demo]', 'Contact 8', '9812300107', 'vendor8@demo.supply', 'Industrial Estate, Plot 17, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (71, 'PackPro Logistics Materials [demo]', 'Contact 9', '9812300108', 'vendor9@demo.supply', 'Industrial Estate, Plot 18, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (72, 'National Tyre Chemicals [demo]', 'Contact 10', '9812300109', 'vendor10@demo.supply', 'Industrial Estate, Plot 19, India', NULL, 'Active', 'Rubber, chemicals, reinforcement', '2026-05-23 19:14:20');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `materials_supplied`, `created_at`) VALUES (73, 'gagan', 'ravi', '7710665540', 'gaganshah011111@gmail.com', 'House No BXXX-1743/18/30 Dashmesh Market Dhandari Khurd Ludhiana', 'gsr1324254335', 'Active', 'carbon black ,jhgg', '2026-05-26 19:21:20');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `username` varchar(80) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Employee',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `force_logout_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=26981 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (1, NULL, 'Super Admin', 'superadmin@ralson.local', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'active', 0, NULL, '2026-05-30 23:25:34', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (2, NULL, 'HR Manager', 'hr@ralson.local', 'hrmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Manager', 'active', 0, '2026-05-30 23:30:51', '2026-05-29 18:35:23', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (3, NULL, 'Production Manager', 'production@ralson.local', 'prodmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Production Manager', 'active', 0, NULL, '2026-05-24 17:20:57', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (4, NULL, 'Inventory Manager', 'inventory@ralson.local', 'invmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Manager', 'active', 0, NULL, '2026-05-28 19:51:54', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (5, NULL, 'Dispatch Manager', 'dispatch@ralson.local', 'dispatchmgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dispatch Manager', 'active', 0, NULL, '2026-05-28 19:46:53', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (6, NULL, 'Quality Manager', 'quality@ralson.local', 'qualitymgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quality Manager', 'active', 0, NULL, '2026-05-23 17:47:10', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (7, NULL, 'Employee User', 'employee@ralson.local', 'employeeuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 'active', 0, NULL, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (561, NULL, 'Gagan Kumar Shah', NULL, 'gag43456', '$2y$10$PW5mc3q6.lKCixv6SLvF0eY5uGjOQJW5xDQ2oNaFLwEuKYvr5xY3C', 'Employee', 'active', 1, NULL, NULL, '2026-05-14 19:37:52');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (2910, 5, 'Gagan Kumar Shah', NULL, 'gag53456', '$2y$10$GQQuDXgWFyGbUbXCkX/ML.aHjN.Qk7xzzhLuHJmM8VJXBaMBJA.V.', 'Employee', 'active', 0, NULL, '2026-05-21 17:00:22', '2026-05-17 18:41:30');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (14943, NULL, 'Sales Manager', 'sales@ralson.local', 'salesmgr', '$2y$10$PMHkpYKirj3dQrnJR31zveSQwM7eI1q2E/Sd5MyPQm.ODO6wrk/fG', 'Sales Manager', 'active', 0, NULL, '2026-05-28 19:47:43', '2026-05-24 17:58:30');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `force_logout_at`, `last_login`, `created_at`) VALUES (19154, NULL, 'Accounts Manager', 'accounts@ralson.local', 'accounts_manager', '$2y$10$xEW0mYJ6Y4PdPMsst/j2WuGrA6JPKBY5vMJZ8.T8gRoR2GvxQ.YCi', 'Accounts Manager', 'active', 0, '2026-05-30 23:22:18', '2026-05-30 23:26:38', '2026-05-28 18:06:07');

SET FOREIGN_KEY_CHECKS=1;
