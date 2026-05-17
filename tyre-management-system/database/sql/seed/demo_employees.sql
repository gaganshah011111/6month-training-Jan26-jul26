-- Demo employees for HR / payroll / leave testing
USE `tyre_erp`;

INSERT INTO employees (
    employee_code, full_name, employee_type, department, designation, role,
    joining_date, basic_salary, paid_leave_limit, half_paid_leave_limit,
    gross_salary, contact_no, status
) VALUES
    ('EMP-DEMO-001', 'Amit Sharma', 'Staff', 'Human Resources', 'HR Executive', 'Employee', CURDATE(), 28000, 12, 6, 35000, '9876500001', 'active'),
    ('EMP-DEMO-002', 'Priya Patel', 'Staff', 'Production', 'Supervisor', 'Employee', CURDATE(), 32000, 12, 6, 40000, '9876500002', 'active'),
    ('EMP-DEMO-003', 'Ravi Kumar', 'Worker', 'Production', 'Machine Operator', 'Employee', CURDATE(), 18000, 0, 0, 22000, '9876500003', 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'active';
