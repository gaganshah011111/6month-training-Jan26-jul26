-- Sample attendance rows for current month (adjust employee_id if needed)
USE `tyre_erp`;

INSERT INTO attendance (employee_id, attendance_date, shift, status, remarks)
SELECT e.id, CURDATE(), 'Morning', 'Present', 'Seed: present today'
FROM employees e
WHERE e.employee_code IN ('EMP-DEMO-001', 'EMP-DEMO-002')
ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks);
