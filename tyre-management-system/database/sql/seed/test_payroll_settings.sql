-- Ensure payroll settings row exists with Indian defaults
USE `tyre_erp`;

INSERT IGNORE INTO payroll_settings (id) VALUES (1);

UPDATE payroll_settings SET
    basic_pct_of_gross = 50.00,
    hra_pct_non_metro = 40.00,
    hra_pct_metro = 50.00,
    pf_employee_pct = 12.00,
    pf_employer_pct = 12.00,
    esi_employee_pct = 0.75,
    esi_employer_pct = 3.25,
    working_days_default = 26.00
WHERE id = 1;
