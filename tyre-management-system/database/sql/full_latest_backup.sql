-- Tyre ERP full_latest_backup.sql
-- Generated: 2026-05-17T19:39:07+05:30
-- Database: tyre_erp
-- Import: mysql -u root < full_latest_backup.sql

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
  `punch_in_time` datetime DEFAULT NULL,
  `punch_out_time` datetime DEFAULT NULL,
  `total_hours` decimal(8,2) DEFAULT NULL,
  `overtime_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `is_early_exit` tinyint(1) NOT NULL DEFAULT 0,
  `is_emergency_duty` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance` (`employee_id`,`attendance_date`),
  KEY `idx_att_date` (`attendance_date`),
  CONSTRAINT `fk_att_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=427 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (51, 1, '2026-05-01', 'Morning', 'Absent', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, NULL, '0.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (52, 1, '2026-05-02', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-02 09:00:00', '2026-05-02 13:00:00', '4.50', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (53, 1, '2026-05-04', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-04 09:00:00', '2026-05-04 13:00:00', '4.50', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (54, 1, '2026-05-05', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-05 09:25:00', '2026-05-05 18:00:00', '9.00', '0.00', 1, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (55, 1, '2026-05-06', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-06 09:25:00', '2026-05-06 18:00:00', '9.00', '0.00', 1, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (56, 1, '2026-05-07', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-07 09:25:00', '2026-05-07 18:00:00', '9.00', '0.00', 1, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (57, 1, '2026-05-08', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-08 09:00:00', '2026-05-08 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (58, 1, '2026-05-09', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-09 09:00:00', '2026-05-09 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (59, 1, '2026-05-11', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-11 09:00:00', '2026-05-11 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (60, 1, '2026-05-12', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-12 09:00:00', '2026-05-12 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (61, 1, '2026-05-13', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-13 09:00:00', '2026-05-13 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (62, 1, '2026-05-14', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-14 09:00:00', '2026-05-14 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (63, 1, '2026-05-15', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-15 09:00:00', '2026-05-15 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (64, 1, '2026-05-16', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-16 09:00:00', '2026-05-16 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (65, 1, '2026-05-18', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-18 09:00:00', '2026-05-18 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (66, 1, '2026-05-19', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-19 09:00:00', '2026-05-19 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (67, 1, '2026-05-20', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-20 09:00:00', '2026-05-20 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (68, 1, '2026-05-21', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-21 09:00:00', '2026-05-21 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (69, 1, '2026-05-22', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-22 09:00:00', '2026-05-22 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (70, 1, '2026-05-23', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-23 09:00:00', '2026-05-23 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (71, 1, '2026-05-25', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-25 09:00:00', '2026-05-25 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (72, 1, '2026-05-26', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-26 09:00:00', '2026-05-26 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (73, 1, '2026-05-27', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-27 09:00:00', '2026-05-27 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (74, 1, '2026-05-28', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-28 09:00:00', '2026-05-28 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (75, 1, '2026-05-29', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-29 09:00:00', '2026-05-29 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (76, 2, '2026-05-01', 'Morning', 'Absent', '[PAYROLL_TEST] Auto-generated for payroll testing', NULL, NULL, '0.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (77, 2, '2026-05-02', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-02 09:00:00', '2026-05-02 13:00:00', '4.50', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (78, 2, '2026-05-04', 'Morning', 'Half Day', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-04 09:00:00', '2026-05-04 13:00:00', '4.50', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (79, 2, '2026-05-05', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-05 09:25:00', '2026-05-05 18:00:00', '9.00', '0.00', 1, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (80, 2, '2026-05-06', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-06 09:25:00', '2026-05-06 18:00:00', '9.00', '0.00', 1, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (81, 2, '2026-05-07', 'Morning', 'Late', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-07 09:25:00', '2026-05-07 18:00:00', '9.00', '0.00', 1, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (82, 2, '2026-05-08', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-08 09:00:00', '2026-05-08 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (83, 2, '2026-05-09', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-09 09:00:00', '2026-05-09 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (84, 2, '2026-05-11', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-11 09:00:00', '2026-05-11 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (85, 2, '2026-05-12', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-12 09:00:00', '2026-05-12 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (86, 2, '2026-05-13', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-13 09:00:00', '2026-05-13 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (87, 2, '2026-05-14', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-14 09:00:00', '2026-05-14 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (88, 2, '2026-05-15', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-15 09:00:00', '2026-05-15 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (89, 2, '2026-05-16', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-16 09:00:00', '2026-05-16 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (90, 2, '2026-05-18', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-18 09:00:00', '2026-05-18 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (91, 2, '2026-05-19', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-19 09:00:00', '2026-05-19 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (92, 2, '2026-05-20', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-20 09:00:00', '2026-05-20 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (93, 2, '2026-05-21', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-21 09:00:00', '2026-05-21 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (94, 2, '2026-05-22', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-22 09:00:00', '2026-05-22 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (95, 2, '2026-05-23', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-23 09:00:00', '2026-05-23 18:00:00', '9.00', '0.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (96, 2, '2026-05-25', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-25 09:00:00', '2026-05-25 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (97, 2, '2026-05-26', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-26 09:00:00', '2026-05-26 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (98, 2, '2026-05-27', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-27 09:00:00', '2026-05-27 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (99, 2, '2026-05-28', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-28 09:00:00', '2026-05-28 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (100, 2, '2026-05-29', 'Morning', 'Present', '[PAYROLL_TEST] Auto-generated for payroll testing', '2026-05-29 09:00:00', '2026-05-29 18:00:00', '11.00', '2.00', 0, 0, 0, '2026-05-15 19:17:24');
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `shift`, `status`, `remarks`, `punch_in_time`, `punch_out_time`, `total_hours`, `overtime_hours`, `is_late`, `is_early_exit`, `is_emergency_duty`, `created_at`) VALUES (426, 5, '2026-05-17', 'Morning', 'Present', NULL, '2026-05-17 08:00:00', '2026-05-17 18:00:00', '10.00', '0.00', 0, 0, 0, '2026-05-17 18:49:03');

DROP TABLE IF EXISTS `company_holidays`;
CREATE TABLE `company_holidays` (
  `holiday_date` date NOT NULL,
  `label` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=2599 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=8661 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=27713 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `order_no` varchar(40) NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `invoice_no` varchar(40) NOT NULL,
  `dispatch_date` date NOT NULL,
  `qty` int(11) NOT NULL,
  `dispatch_status` enum('Created','In Transit','Delivered') DEFAULT 'Created',
  `tracking_no` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_dispatch_status` (`dispatch_status`),
  KEY `idx_dispatch_date` (`dispatch_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (1, 7, 'EMP001', 'Ravi Kumar', NULL, NULL, NULL, 'Staff', NULL, NULL, 'Tire Building / Product Assembly', '', 5, NULL, 'Employee', 'Monthly', NULL, NULL, NULL, '9876500001', NULL, NULL, '2024-01-15', '28000.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 0, '0.00', '0.00', '0.00', '0.00', '0.00', '28000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-14 17:43:38');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (2, NULL, 'EMP002', 'Neha Sharma', NULL, NULL, NULL, 'Staff', NULL, NULL, 'Quality Assurance & Testing (QA/QC)', '', 7, NULL, 'Employee', 'Monthly', NULL, NULL, NULL, '9876500002', NULL, NULL, '2024-02-12', '30000.00', '12.00', '6.00', '40.00', '0.00', 1, '12.00', 1, '0.75', '21000.00', 0, 0, '0.00', '0.00', '0.00', '0.00', '0.00', '30000.00', '0.00', '0.00', '0.00', '0.00', 'active', '2026-05-14 17:43:38');
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `full_name`, `father_name`, `dob`, `aadhaar_number`, `employee_type`, `email`, `password_hash`, `department`, `designation`, `department_id`, `designation_id`, `role`, `salary_type`, `shift_timing`, `shift_start`, `shift_end`, `contact_no`, `address`, `profile_image`, `joining_date`, `basic_salary`, `paid_leave_limit`, `half_paid_leave_limit`, `hra_percentage`, `hra_amount`, `pf_applicable`, `pf_percentage`, `esi_applicable`, `esi_percentage`, `esi_salary_limit`, `metro`, `payroll_auto_indian`, `medical_allowance`, `dearness_allowance`, `travel_allowance`, `special_allowance`, `other_allowances`, `gross_salary`, `gratuity_monthly`, `overtime_rate`, `daily_wage`, `hourly_rate`, `status`, `created_at`) VALUES (5, 2910, 'EMP003', 'Gagan Kumar Shah', 'Munna Shah', '2004-12-18', '567607543456', 'Staff', 'gaganshah011111@gmail.com', NULL, 'IT Support', 'IT Support Engineer', 10, 31, 'Employee', 'Monthly', 'Morning Shift', '09:00:00', '18:00:00', '09915492452', 'House No BXXX-1743/18/30 Dashmesh Market Dhandari Khurd Ludhiana', NULL, '2026-05-01', '12500.00', '2.00', '1.96', '40.00', '5000.00', 1, '12.00', 0, '0.75', '21000.00', 0, 1, '500.00', '625.00', '500.00', '5874.99', '0.00', '24999.99', '601.25', '120.10', '960.80', '120.10', 'active', '2026-05-17 18:41:30');

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

DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(120) NOT NULL,
  `batch_ref` varchar(40) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 0,
  `warehouse_location` varchar(120) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inventory_qty` (`qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `staffing_risk` varchar(20) NOT NULL DEFAULT 'Safe',
  `system_note` varchar(255) DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `day_allocation_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_leave_employee` (`employee_id`),
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `machines`;
CREATE TABLE `machines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `machine_code` varchar(30) NOT NULL,
  `machine_name` varchar(120) NOT NULL,
  `status` enum('Active','Under Maintenance','Inactive') NOT NULL DEFAULT 'Active',
  `last_maintenance_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_code` (`machine_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `status`, `last_maintenance_date`, `created_at`) VALUES (1, 'MC-101', 'Tyre Curing Press 1', 'Active', '2026-04-10', '2026-05-14 17:43:38');
INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `status`, `last_maintenance_date`, `created_at`) VALUES (2, 'MC-102', 'Tyre Building Machine 1', 'Active', '2026-04-22', '2026-05-14 17:43:38');

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payroll_settings` (`id`, `basic_pct_of_gross`, `da_pct_of_basic`, `da_enabled`, `hra_pct_non_metro`, `hra_pct_metro`, `medical_enabled`, `medical_mode`, `medical_fixed`, `medical_pct_of_basic`, `travel_enabled`, `travel_mode`, `travel_fixed`, `travel_pct_of_basic`, `gratuity_pct_of_basic`, `pf_employee_pct`, `pf_employer_pct`, `esi_employee_pct`, `esi_employer_pct`, `esi_gross_limit`, `working_days_default`, `shift_hours_default`, `ot_multiplier`, `late_deduction_pct_of_daily`, `updated_at`) VALUES (1, '50.00', '5.00', 1, '40.00', '50.00', 1, 'fixed', '500.00', '0.00', 1, 'fixed', '500.00', '0.00', '4.810', '12.00', '12.00', '0.75', '3.25', '21000.00', '26.02', '8.00', '1.00', '10.00', '2026-05-15 18:42:48');

DROP TABLE IF EXISTS `production`;
CREATE TABLE `production` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `production_date` date NOT NULL,
  `machine_id` int(11) NOT NULL,
  `shift` enum('Morning','Evening','Night') NOT NULL,
  `raw_material_id` int(11) NOT NULL,
  `material_used_qty` decimal(12,2) NOT NULL,
  `output_quantity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prod_date` (`production_date`),
  KEY `fk_prod_machine` (`machine_id`),
  KEY `fk_prod_material` (`raw_material_id`),
  CONSTRAINT `fk_prod_machine` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  CONSTRAINT `fk_prod_material` FOREIGN KEY (`raw_material_id`) REFERENCES `raw_materials` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quality_checks`;
CREATE TABLE `quality_checks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `production_id` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `inspector_name` varchar(120) NOT NULL,
  `passed_qty` int(11) NOT NULL,
  `failed_qty` int(11) NOT NULL,
  `quality_status` enum('Pass','Fail') NOT NULL DEFAULT 'Pass',
  `defects` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_qc_production` (`production_id`),
  CONSTRAINT `fk_qc_production` FOREIGN KEY (`production_id`) REFERENCES `production` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `raw_materials`;
CREATE TABLE `raw_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_name` varchar(120) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `stock_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_material_stock` (`stock_qty`),
  KEY `fk_material_supplier` (`supplier_id`),
  CONSTRAINT `fk_material_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `raw_materials` (`id`, `material_name`, `unit`, `stock_qty`, `reorder_level`, `supplier_id`, `created_at`) VALUES (1, 'Natural Rubber', 'kg', '5000.00', '1200.00', 1, '2026-05-14 17:43:38');
INSERT INTO `raw_materials` (`id`, `material_name`, `unit`, `stock_qty`, `reorder_level`, `supplier_id`, `created_at`) VALUES (2, 'Carbon Black', 'kg', '2200.00', '800.00', 2, '2026-05-14 17:43:38');

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=498 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (1, 'company_name', 'Ralson India Private Limited');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (104, 'employee_gross_backfill_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (135, 'employee_payroll_auto_legacy_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (292, 'erp_collation_utf8mb4_general_ci_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (407, 'attendance_fk_cascade_v1', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (488, 'leave_auto_approve_enabled', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES (489, 'leave_min_present_pct', '50');

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `created_at`) VALUES (1, 'Punjab Rubber Corp', 'Harjit Singh', '9811111111', 'harjit@rubber.local', 'Ludhiana', '2026-05-14 17:43:38');
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `created_at`) VALUES (2, 'ChemTech Industries', 'K. Mehta', '9822222222', 'mehta@chem.local', 'Delhi NCR', '2026-05-14 17:43:38');

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
) ENGINE=InnoDB AUTO_INCREMENT=3268 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (1, NULL, 'Super Admin', 'superadmin@ralson.local', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'active', 0, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (2, NULL, 'HR Manager', 'hr@ralson.local', 'hrmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Manager', 'active', 0, '2026-05-17 18:45:26', '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (3, NULL, 'Production Manager', 'production@ralson.local', 'prodmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Production Manager', 'active', 0, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (4, NULL, 'Inventory Manager', 'inventory@ralson.local', 'invmanager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Manager', 'active', 0, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (5, NULL, 'Dispatch Manager', 'dispatch@ralson.local', 'dispatchmgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dispatch Manager', 'active', 0, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (6, NULL, 'Quality Manager', 'quality@ralson.local', 'qualitymgr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quality Manager', 'active', 0, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (7, NULL, 'Employee User', 'employee@ralson.local', 'employeeuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 'active', 0, NULL, '2026-05-14 17:43:38');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (561, NULL, 'Gagan Kumar Shah', NULL, 'gag43456', '$2y$10$PW5mc3q6.lKCixv6SLvF0eY5uGjOQJW5xDQ2oNaFLwEuKYvr5xY3C', 'Employee', 'active', 1, NULL, '2026-05-14 19:37:52');
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `username`, `password_hash`, `role`, `status`, `must_change_password`, `last_login`, `created_at`) VALUES (2910, 5, 'Gagan Kumar Shah', NULL, 'gag53456', '$2y$10$GQQuDXgWFyGbUbXCkX/ML.aHjN.Qk7xzzhLuHJmM8VJXBaMBJA.V.', 'Employee', 'active', 0, '2026-05-17 18:50:12', '2026-05-17 18:41:30');

SET FOREIGN_KEY_CHECKS=1;
