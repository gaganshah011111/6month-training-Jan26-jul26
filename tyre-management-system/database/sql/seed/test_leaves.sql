-- Sample leave request (pending) for demo employee
USE `tyre_erp`;

INSERT INTO leaves (
    employee_id, from_date, to_date, start_date, end_date,
    leave_type, leave_category, reason, is_paid, status,
    paid_days, half_paid_days, unpaid_days, total_days, staffing_risk, system_note
)
SELECT e.id,
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    DATE_ADD(CURDATE(), INTERVAL 9 DAY),
    DATE_ADD(CURDATE(), INTERVAL 7 DAY),
    DATE_ADD(CURDATE(), INTERVAL 9 DAY),
    'General', 'Paid', 'Family function', 1, 'Pending',
    2, 0, 0, 2, 'Safe', 'Seed test leave'
FROM employees e
WHERE e.employee_code = 'EMP-DEMO-001'
LIMIT 1;
