-- =============================================================================
-- Tyre ERP — FULL DATABASE BACKUP (use this file if MySQL fails or data is lost)
-- =============================================================================
-- Generated: 2026-05-23T18:39:08+05:30
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
) ENGINE=InnoDB AUTO_INCREMENT=490 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (51, 1, '2026-05-01', 'Morning', 'Absent', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, NULL, NULL, '0.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (52, 1, '2026-05-02', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-02 09:00:00', '2026-05-02 13:00:00', '4.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (53, 1, '2026-05-04', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-04 09:00:00', '2026-05-04 13:00:00', '4.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (54, 1, '2026-05-05', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-05 09:25:00', '2026-05-05 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (55, 1, '2026-05-06', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-06 09:25:00', '2026-05-06 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (56, 1, '2026-05-07', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-07 09:25:00', '2026-05-07 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (57, 1, '2026-05-08', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-08 09:00:00', '2026-05-08 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (58, 1, '2026-05-09', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-09 09:00:00', '2026-05-09 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (59, 1, '2026-05-11', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-11 09:00:00', '2026-05-11 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (60, 1, '2026-05-12', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-12 09:00:00', '2026-05-12 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (61, 1, '2026-05-13', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-13 09:00:00', '2026-05-13 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (62, 1, '2026-05-14', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-14 09:00:00', '2026-05-14 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (63, 1, '2026-05-15', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-15 09:00:00', '2026-05-15 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (64, 1, '2026-05-16', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-16 09:00:00', '2026-05-16 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (65, 1, '2026-05-18', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-18 09:00:00', '2026-05-18 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (66, 1, '2026-05-19', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-19 09:00:00', '2026-05-19 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (67, 1, '2026-05-20', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-20 09:00:00', '2026-05-20 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (68, 1, '2026-05-21', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-21 09:00:00', '2026-05-21 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (69, 1, '2026-05-22', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-22 09:00:00', '2026-05-22 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (70, 1, '2026-05-23', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-23 09:00:00', '2026-05-23 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (71, 1, '2026-05-25', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-25 09:00:00', '2026-05-25 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (72, 1, '2026-05-26', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-26 09:00:00', '2026-05-26 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (73, 1, '2026-05-27', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-27 09:00:00', '2026-05-27 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (74, 1, '2026-05-28', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-28 09:00:00', '2026-05-28 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (75, 1, '2026-05-29', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-29 09:00:00', '2026-05-29 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (76, 2, '2026-05-01', 'Morning', 'Absent', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, NULL, NULL, '0.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (77, 2, '2026-05-02', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-02 09:00:00', '2026-05-02 13:00:00', '4.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (78, 2, '2026-05-04', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-04 09:00:00', '2026-05-04 13:00:00', '4.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (79, 2, '2026-05-05', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-05 09:25:00', '2026-05-05 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (80, 2, '2026-05-06', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-06 09:25:00', '2026-05-06 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (81, 2, '2026-05-07', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-07 09:25:00', '2026-05-07 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (82, 2, '2026-05-08', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-08 09:00:00', '2026-05-08 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (83, 2, '2026-05-09', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-09 09:00:00', '2026-05-09 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (84, 2, '2026-05-11', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-11 09:00:00', '2026-05-11 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (85, 2, '2026-05-12', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-12 09:00:00', '2026-05-12 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (86, 2, '2026-05-13', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-13 09:00:00', '2026-05-13 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (87, 2, '2026-05-14', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-14 09:00:00', '2026-05-14 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (88, 2, '2026-05-15', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-15 09:00:00', '2026-05-15 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (89, 2, '2026-05-16', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-16 09:00:00', '2026-05-16 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (90, 2, '2026-05-18', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-18 09:00:00', '2026-05-18 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (91, 2, '2026-05-19', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-19 09:00:00', '2026-05-19 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (92, 2, '2026-05-20', 'Morning', 'Absent', NULL, NULL, NULL, NULL, '0.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (93, 2, '2026-05-21', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-21 09:00:00', '2026-05-21 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (94, 2, '2026-05-22', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-22 09:00:00', '2026-05-22 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (95, 2, '2026-05-23', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-23 09:00:00', '2026-05-23 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (96, 2, '2026-05-25', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-25 09:00:00', '2026-05-25 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (97, 2, '2026-05-26', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-26 09:00:00', '2026-05-26 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (98, 2, '2026-05-27', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-27 09:00:00', '2026-05-27 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (99, 2, '2026-05-28', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-28 09:00:00', '2026-05-28 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (100, 2, '2026-05-29', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-29 09:00:00', '2026-05-29 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (426, 5, '2026-05-17', 'Morning', 'Present', NULL, NULL, '2026-05-17 08:00:00', '2026-05-17 18:00:00', '10.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-17 18:49:03');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (465, 5, '2026-05-01', 'Morning', 'Absent', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, NULL, NULL, '0.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (466, 5, '2026-05-02', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-02 09:00:00', '2026-05-02 13:00:00', '4.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (467, 5, '2026-05-04', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-04 09:00:00', '2026-05-04 13:00:00', '4.50', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (468, 5, '2026-05-05', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-05 09:25:00', '2026-05-05 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (469, 5, '2026-05-06', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-06 09:25:00', '2026-05-06 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (470, 5, '2026-05-07', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-07 09:25:00', '2026-05-07 18:00:00', '9.00', '0.00', 1, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (471, 5, '2026-05-08', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-08 09:00:00', '2026-05-08 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (472, 5, '2026-05-09', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-09 09:00:00', '2026-05-09 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (473, 5, '2026-05-11', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-11 09:00:00', '2026-05-11 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (474, 5, '2026-05-12', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-12 09:00:00', '2026-05-12 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (475, 5, '2026-05-13', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-13 09:00:00', '2026-05-13 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (476, 5, '2026-05-14', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-14 09:00:00', '2026-05-14 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (477, 5, '2026-05-15', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-15 09:00:00', '2026-05-15 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (478, 5, '2026-05-16', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-16 09:00:00', '2026-05-16 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (479, 5, '2026-05-18', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-18 09:00:00', '2026-05-18 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:00');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (480, 5, '2026-05-19', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-19 09:00:00', '2026-05-19 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (481, 5, '2026-05-20', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-20 09:00:00', '2026-05-20 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (482, 5, '2026-05-21', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-21 09:00:00', '2026-05-21 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (483, 5, '2026-05-22', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-22 09:00:00', '2026-05-22 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (484, 5, '2026-05-23', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-23 09:00:00', '2026-05-23 18:00:00', '9.00', '0.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (485, 5, '2026-05-25', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-25 09:00:00', '2026-05-25 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (486, 5, '2026-05-26', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-26 09:00:00', '2026-05-26 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (487, 5, '2026-05-27', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-27 09:00:00', '2026-05-27 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (488, 5, '2026-05-28', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-28 09:00:00', '2026-05-28 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `linked_leave_id`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `needs_verification`, `verified_by`, `verified_at`, `created_at`) VALUES (489, 5, '2026-05-29', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, '2026-05-29 09:00:00', '2026-05-29 18:00:00', '11.00', '2.00', 0, 0, 0, 0, NULL, NULL, '2026-05-21 16:59:01');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `curing_entries` (`id`, `batch_code`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `downtime_minutes`, `remarks`, `qc_status`, `qc_inspection_id`, `created_at`) VALUES (1, 'CUR-20260522-0001', '2026-05-22', 'Morning', 1, 1, 'PCR Car', 10, 23, 40, NULL, 'Inspecting', NULL, '2026-05-22 17:27:47');

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
) ENGINE=InnoDB AUTO_INCREMENT=8257 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `min_staff_required` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_code` (`department_code`),
  UNIQUE KEY `uk_dept_cat_name` (`category_id`,`department_name`),
  KEY `idx_dept_category` (`category_id`),
  CONSTRAINT `fk_dept_category` FOREIGN KEY (`category_id`) REFERENCES `department_categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27521 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (1, 1, 'Production Planning & Control (PPC)', 'PPC', 'DEPT_PPC', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (2, 1, 'Raw Materials & Inventory Management', 'Raw Materials', 'DEPT_RAW_MAT', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (3, 1, 'Mixing & Compounding', 'Mixing', 'DEPT_MIXING', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (4, 1, 'Component Preparation (Extrusion & Calendering)', 'Comp. Prep.', 'DEPT_COMP_PREP', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (5, 1, 'Tire Building / Product Assembly', 'Tire Building', 'DEPT_TIRE_BUILD', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (6, 1, 'Curing & Vulcanization', 'Curing', 'DEPT_CURING', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (7, 2, 'Quality Assurance & Testing (QA/QC)', 'QA/QC', 'DEPT_QA_QC', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (8, 2, 'Environment Health & Safety (EHS)', 'EHS', 'DEPT_EHS', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (9, 3, 'Plant Maintenance & Engineering', 'Maintenance', 'DEPT_MAINT', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (10, 3, 'IT Support', 'IT', 'DEPT_IT', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (11, 4, 'Logistics & Dispatch', 'Dispatch', 'DEPT_LOG_DISP', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (12, 4, 'Warehouse Management', 'Warehouse', 'DEPT_WH', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (13, 4, 'Store Management', 'Store', 'DEPT_STORE', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (14, 5, 'Human Resources (HR)', 'HR', 'DEPT_HR', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (15, 5, 'Administration', 'Administration', 'DEPT_ADMIN', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (16, 5, 'Accounts & Finance', 'Accounts', 'DEPT_ACC', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (17, 5, 'Purchase & Procurement', 'Purchase', 'DEPT_PUR', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (18, 5, 'Unclassified (Legacy)', 'Legacy', 'DEPT_UNCLASS', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (19, 6, 'Sales & Marketing', 'Sales', 'DEPT_SALES', 'active', NULL, '2026-05-14 18:28:16');
INSERT INTO `departments` (`id`, `category_id`, `department_name`, `department_short_name`, `department_code`, `status`, `min_staff_required`, `created_at`) VALUES (20, 6, 'Management', 'Management', 'DEPT_MGMT', 'active', NULL, '2026-05-14 18:28:16');

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
) ENGINE=InnoDB AUTO_INCREMENT=88065 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  KEY `idx_dispatch_date` (`dispatch_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 1, 'DSP-20260522-001', 'ORD-260522-001', 'Metro Tyre Traders', 1, 'TBR Truck', 'INV-20260522-2226', 'PB10AX1234', 1, 'Ravi Kumar', 1, 'Punjab Logistics', 1, '2026-05-22', 4, '769.00', '700.00', '69.00', 'lkk', 'Dispatched', 1, 'In Transit', NULL, '2026-05-22 19:43:34');
INSERT INTO `dispatch` (`inventory_id`, `id`, `dispatch_code`, `order_no`, `customer_name`, `customer_id`, `tyre_type`, `invoice_no`, `vehicle_no`, `vehicle_id`, `driver_name`, `driver_id`, `transport_company`, `transport_company_id`, `dispatch_date`, `qty`, `gross_weight_kg`, `tare_weight_kg`, `net_weight_kg`, `remarks`, `status`, `stock_deducted`, `dispatch_status`, `tracking_no`, `created_at`) VALUES (NULL, 2, 'DSP-20260523-001', 'ORD-260523-001', 'North Highway Fleet', 2, 'TBR Truck', 'INV-20260523-5366', 'PB10AX1234', 1, 'Ravi Kumar', 1, 'Punjab Logistics', 1, '2026-05-23', 24, '1000.00', '200.00', '800.00', 'do it care', 'Dispatched', 1, 'In Transit', NULL, '2026-05-23 17:32:31');

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (1, 'Metro Tyre Traders', 'Metro Tyre Traders Pvt Ltd', '9876500001', NULL, NULL, 'Mumbai', 'Maharashtra', 'Active', '2026-05-22 18:50:37');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (2, 'North Highway Fleet', 'North Highway Fleet Services', '9876500002', NULL, NULL, 'Delhi', 'Delhi', 'Active', '2026-05-22 18:50:37');
INSERT INTO `dispatch_customers` (`id`, `customer_name`, `company`, `phone`, `gst_number`, `address`, `city`, `state`, `status`, `created_at`) VALUES (3, 'Southern Auto Mart', 'Southern Auto Mart', '9876500003', NULL, NULL, 'Chennai', 'Tamil Nadu', 'Active', '2026-05-22 18:50:37');

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (1, 'Ravi Kumar', '9876510001', 'DL-09-2018-0012345', 1, 'PB10AX1234', 1, 'Punjab Logistics', 'Ludhiana, Punjab', 'Active', '2026-05-22 19:04:30');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (2, 'Suresh Patel', '9876510002', 'GJ-12-2019-0098765', 2, 'GJ01BT5678', 2, 'Western Freight Lines', 'Ahmedabad, Gujarat', 'Active', '2026-05-22 19:04:30');
INSERT INTO `dispatch_drivers` (`id`, `driver_name`, `phone`, `license_number`, `vehicle_id`, `vehicle_no`, `transport_company_id`, `transport_company`, `address`, `status`, `created_at`) VALUES (3, 'Mohit Singh', '9876510003', 'HR-05-2020-0045678', 3, 'HR26CD9012', 3, 'North Star Transport', 'Gurgaon, Haryana', 'Active', '2026-05-22 19:04:30');

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (1, 'Punjab Logistics', 'Harpreet Singh', '9876520001', '03AABCP1234F1Z5', 'Ludhiana, Punjab', 'Active', '2026-05-22 19:15:32');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (2, 'Western Freight Lines', 'Amit Shah', '9876520002', '24AABCW5678G1Z9', 'Ahmedabad, Gujarat', 'Active', '2026-05-22 19:15:32');
INSERT INTO `dispatch_transport_companies` (`id`, `company_name`, `contact_person`, `phone`, `gst_number`, `address`, `status`, `created_at`) VALUES (3, 'North Star Transport', 'Vikram Mehta', '9876520003', '06AABCN9012H1Z3', 'Gurgaon, Haryana', 'Active', '2026-05-22 19:15:32');

DROP TABLE IF EXISTS `dispatch_vehicles`;
CREATE TABLE `dispatch_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_number` varchar(40) NOT NULL,
  `vehicle_type` varchar(60) DEFAULT NULL,
  `capacity` varchar(40) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `transport_company_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_number` (`vehicle_number`),
  KEY `idx_vehicle_status` (`status`),
  KEY `idx_vehicle_driver` (`driver_id`),
  KEY `idx_vehicle_transport` (`transport_company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (1, 'PB10AX1234', 'Truck', '40 tyres', 1, 1, 'Active', '2026-05-22 19:24:33');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (2, 'GJ01BT5678', 'Truck', '40 tyres', 2, 2, 'Active', '2026-05-22 19:24:33');
INSERT INTO `dispatch_vehicles` (`id`, `vehicle_number`, `vehicle_type`, `capacity`, `driver_id`, `transport_company_id`, `status`, `created_at`) VALUES (3, 'HR26CD9012', 'Truck', '40 tyres', 3, 3, 'Active', '2026-05-22 19:24:33');

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (1, 7, 'EMP001', 'Ravi Kumar', NULL, NULL, NULL, 'Staff', NULL, NULL, 'Tire Building / Product Assembly', '', 5, NULL, 'Employee', 'Monthly', NULL, NULL, NULL, '9876500001', NULL, NULL, NULL, '2024-01-15', '28000.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 0, '0.00', '0.00', '0.00', '0.00', '0.00', '28000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-14 17:43:38');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (2, NULL, 'EMP002', 'Neha Sharma', NULL, NULL, NULL, 'Staff', NULL, NULL, 'Quality Assurance & Testing (QA/QC)', '', 7, NULL, 'Employee', 'Monthly', NULL, NULL, NULL, '9876500002', NULL, NULL, NULL, '2024-02-12', '30000.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 0, '0.00', '0.00', '0.00', '0.00', '0.00', '30000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-14 17:43:38');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `emergency_contact`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (5, 2910, 'EMP003', 'Gagan Kumar Shah', 'Munna Shah', '2004-12-18', '567607543456', 'Staff', 'gaganshah011111@gmail.com', NULL, 'IT Support', 'IT Support Engineer', 10, 31, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '09915492452', NULL, 'House No BXXX-1743/18/30 Dashmesh Market Dhandari Khurd Ludhiana', 'assets/uploads/profiles/emp_5_1779359624.jpg', '2026-05-01', '12500.00', '2.00', '1.96', '40.00', '5000.00', 1, '12.00', 0, '0.75', '21000.00', 0, 1, '500.00', '625.00', '500.00', '5874.99', '0.00', '24999.99', '601.25', '120.10', '960.80', '120.10', 'active', '2026-05-17 18:41:30');

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inventory` (`id`, `product_name`, `batch_ref`, `qty`, `reorder_level`, `warehouse_location`, `stock_category`, `updated_at`) VALUES (1, 'TBR Truck', 'QC-20260522-1', 1, 50, 'FG-A1', NULL, '2026-05-23 17:32:31');

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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `status` varchar(30) NOT NULL DEFAULT 'Idle',
  `last_maintenance_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deactivated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_code` (`machine_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (1, 'MC-101', 'Tyre Curing Press 1', NULL, NULL, NULL, NULL, 0, 'Active', '2026-04-10', NULL, NULL, '2026-05-14 17:43:38', 1, NULL);
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `department`, `section`, `installation_date`, `shift_capacity`, `status`, `last_maintenance_date`, `notes`, `remarks`, `created_at`, `is_active`, `deactivated_at`) VALUES (2, 'MC-102', 'Tyre Building Machine 1', NULL, NULL, NULL, NULL, 0, 'Active', '2026-04-22', NULL, NULL, '2026-05-14 17:43:38', 1, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (1, '2026-05-25', 'Morning', 2, 2, 'TBR Truck', '1000.00', '4.81', NULL, '2026-05-22 17:25:09');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (5, '2026-05-23', 'Morning', 1, 1, 'PCR Car', '600.00', '0.00', NULL, '2026-05-23 18:17:09');
INSERT INTO `mixing_entries` (`id`, `production_date`, `shift`, `machine_id`, `operator_id`, `tyre_type`, `produced_qty`, `rejected_qty`, `remarks`, `created_at`) VALUES (6, '2026-05-23', 'Morning', 1, 1, 'PCR Car', '600.00', '0.00', NULL, '2026-05-23 18:17:19');

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payroll` (`id`, `employee_id`, `month`, `year`, `present_days`, `paid_leave_days`, `unpaid_leave_days`, `overtime_amount`, `basic_salary`, `deduction`, `net_salary`, `created_at`) VALUES (8, 5, 5, 2026, '24.00', '0.00', '0.00', '1201.00', '26200.99', '2749.04', '23451.95', '2026-05-21 16:58:56');

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `qc_entries` (`id`, `entry_date`, `shift`, `inspector_name`, `tyre_type`, `checked_qty`, `passed_qty`, `failed_qty`, `defect_type`, `remarks`, `created_at`) VALUES (1, '2026-05-22', 'Morning', 'sd', 'TBR Truck', 150, 29, 30, NULL, 'h', '2026-05-22 17:26:30');

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
  `storage_location` varchar(120) NOT NULL DEFAULT 'Main Store',
  `remarks` varchar(500) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_material_stock` (`stock_qty`),
  KEY `fk_material_supplier` (`supplier_id`),
  CONSTRAINT `fk_material_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (1, 'RM-1', 'Natural Rubber', 'General', 'kg', '4460.00', '1200.00', '0.00', 'Main Store', NULL, 'Active', 1, '2026-05-14 17:43:38');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (2, 'RM-2', 'Carbon Black', 'General', 'kg', '107009787.97', '800.00', '0.00', 'Main Store', NULL, 'Active', 2, '2026-05-14 17:43:38');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (3, '677', 'gaga', 'Rubber', 'kg', '10000.00', '49.93', '0.00', 'Main Store', NULL, 'Active', 2, '2026-05-22 18:08:26');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (4, '677', 'gaga', 'Rubber', 'kg', '10000.00', '49.93', '0.00', 'Main Store', NULL, 'Active', 2, '2026-05-22 18:08:30');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (5, 'RM-CHEM', 'Chemicals', 'Chemicals', 'kg', '740.00', '100.00', '0.00', 'Store-C1', NULL, 'Active', NULL, '2026-05-23 17:19:25');
INSERT INTO `raw_materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `stock_qty`, `reorder_level`, `max_stock_level`, `storage_location`, `remarks`, `status`, `supplier_id`, `created_at`) VALUES (6, 'RM-PACK', 'Packaging', 'Packaging', 'piece', '5000.00', '500.00', '0.00', 'Store-D1', NULL, 'Active', NULL, '2026-05-23 17:19:25');

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
  `payment_status` varchar(20) NOT NULL DEFAULT 'unpaid',
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
  CONSTRAINT `fk_salary_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `payment_status`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (8, 5, '2026-05', '24.00', '0.00', '0.00', '0.00', '10.00', '1201.00', '12500.00', '0.00', '0.00', '23451.95', 'unpaid', 0, NULL, '2026-05-21 16:58:56', '2026-05-21 16:58:56', '40.00', '5000.00', '12.00', '1500.00', '0.75', '0.00', '3.25', '0.00', '500.00', '625.00', '500.00', '5874.99', '0.00', '1500.00', '0.00', '601.25', '0.00', '960.80', '288.24', '26200.99', '2749.04');
INSERT INTO `salaries` (`id`, `employee_id`, `month_year`, `present_days`, `paid_leave_days`, `half_paid_leave_days`, `unpaid_leave_days`, `overtime_hours`, `overtime_amount`, `basic`, `overtime`, `deductions`, `net_salary`, `payment_status`, `is_draft`, `paid_at`, `generated_at`, `created_at`, `hra_percentage`, `hra_amount`, `pf_percentage`, `pf_amount`, `esi_employee_percentage`, `esi_employee_amount`, `esi_employer_percentage`, `esi_employer_amount`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `pf_employer_amount`, `tax_deduction`, `gratuity_accrual`, `leave_deduction`, `half_day_deduction`, `late_entry_deduction`, `gross_salary`, `total_deduction`) VALUES (9, 5, '2026-05', '24.00', '0.00', '0.00', '0.00', '10.00', '1201.00', '12500.00', '0.00', '0.00', '23451.95', 'unpaid', 0, NULL, '2026-05-21 16:59:01', '2026-05-21 16:59:01', '40.00', '5000.00', '12.00', '1500.00', '0.75', '0.00', '3.25', '0.00', '500.00', '625.00', '500.00', '5874.99', '0.00', '1500.00', '0.00', '601.25', '0.00', '960.80', '288.24', '26200.99', '2749.04');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1443 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (1, 'company_name', 'Ralson India Private Limited');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (104, 'employee_gross_backfill_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (135, 'employee_payroll_auto_legacy_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (292, 'erp_collation_utf8mb4_general_ci_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (407, 'attendance_fk_cascade_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (488, 'leave_auto_approve_enabled', '0');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (489, 'leave_min_present_pct', '50');

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
  `inward_date` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(80) DEFAULT NULL,
  `material_id` int(11) NOT NULL,
  `batch_no` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_by` varchar(150) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inward_date` (`inward_date`),
  KEY `idx_inward_material` (`material_id`),
  KEY `idx_inward_supplier` (`supplier_id`),
  CONSTRAINT `fk_inward_material` FOREIGN KEY (`material_id`) REFERENCES `raw_materials` (`id`),
  CONSTRAINT `fk_inward_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock_inward` (`id`, `inward_date`, `supplier_id`, `invoice_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `received_by`, `remarks`, `created_at`) VALUES (1, '2026-05-22', 1, NULL, 2, NULL, NULL, '7887.97', '0.00', NULL, NULL, '2026-05-22 18:17:43');
INSERT INTO `stock_inward` (`id`, `inward_date`, `supplier_id`, `invoice_no`, `material_id`, `batch_no`, `expiry_date`, `quantity`, `rate`, `received_by`, `remarks`, `created_at`) VALUES (2, '2026-05-22', 2, NULL, 2, NULL, NULL, '120000000.00', '0.00', NULL, NULL, '2026-05-22 18:18:06');

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (1, '2026-05-22', 2, '13000000.00', 'manual', 'Building', NULL, NULL, NULL, NULL, '2026-05-22 18:18:36');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (2, '2026-05-23', 1, '270.00', 'production', 'Mixing', 'production', 5, 'Auto from mixing entry #5', 'System', '2026-05-23 18:17:09');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (3, '2026-05-23', 2, '150.00', 'production', 'Mixing', 'production', 5, 'Auto from mixing entry #5', 'System', '2026-05-23 18:17:09');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (4, '2026-05-23', 5, '30.00', 'production', 'Mixing', 'production', 5, 'Auto from mixing entry #5', 'System', '2026-05-23 18:17:09');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (5, '2026-05-23', 1, '270.00', 'production', 'Mixing', 'production', 6, 'Auto from mixing entry #6', 'System', '2026-05-23 18:17:19');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (6, '2026-05-23', 2, '150.00', 'production', 'Mixing', 'production', 6, 'Auto from mixing entry #6', 'System', '2026-05-23 18:17:19');
INSERT INTO `stock_usage` (`id`, `usage_date`, `material_id`, `quantity`, `usage_type`, `department`, `usage_reason`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES (7, '2026-05-23', 5, '30.00', 'production', 'Mixing', 'production', 6, 'Auto from mixing entry #6', 'System', '2026-05-23 18:17:19');

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `materials_supplied` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `status`, `materials_supplied`, `created_at`) VALUES (1, 'Punjab Rubber Corp', 'Harjit Singh', '9811111111', 'harjit@rubber.local', 'Ludhiana', 'Active', NULL, '2026-05-14 17:43:38');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `status`, `materials_supplied`, `created_at`) VALUES (2, 'ChemTech Industries', 'K. Mehta', '9822222222', 'mehta@chem.local', 'Delhi NCR', 'Active', NULL, '2026-05-14 17:43:38');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `username` varchar(80) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Employee',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9832 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (1, NULL, 'Super Admin', 'superadmin@ralson.local', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'active', 0, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (2, NULL, 'HR Manager', 'hr@ralson.local', 'hrmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Manager', 'active', 0, '2026-05-23 18:26:31', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (3, NULL, 'Production Manager', 'production@ralson.local', 'prodmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Production Manager', 'active', 0, '2026-05-23 18:27:08', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (4, NULL, 'Inventory Manager', 'inventory@ralson.local', 'invmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Manager', 'active', 0, '2026-05-23 18:04:18', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (5, NULL, 'Dispatch Manager', 'dispatch@ralson.local', 'dispatchmgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dispatch Manager', 'active', 0, '2026-05-23 18:14:45', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (6, NULL, 'Quality Manager', 'quality@ralson.local', 'qualitymgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quality Manager', 'active', 0, '2026-05-23 17:47:10', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (7, NULL, 'Employee User', 'employee@ralson.local', 'employeeuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 'active', 0, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (561, NULL, 'Gagan Kumar Shah', NULL, 'gag43456', '$2y$10$PW5mc3q6.lKCixv6SLvF0eY5uGjOQJW5xDQ2oNaFLwEuKYvr5xY3C', 'Employee', 'active', 1, NULL, '2026-05-14 19:37:52');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (2910, 5, 'Gagan Kumar Shah', NULL, 'gag53456', '$2y$10$GQQuDXgWFyGbUbXCkX/ML.aHjN.Qk7xzzhLuHJmM8VJXBaMBJA.V.', 'Employee', 'active', 0, '2026-05-21 17:00:22', '2026-05-17 18:41:30');

SET FOREIGN_KEY_CHECKS=1;
