-- 007: Link employees to department hierarchy
USE `tyre_erp`;

ALTER TABLE employees ADD COLUMN department_id INT NULL;
ALTER TABLE employees ADD COLUMN designation_id INT NULL;

CREATE INDEX idx_employees_department_id ON employees (department_id);
CREATE INDEX idx_employees_designation_id ON employees (designation_id);
