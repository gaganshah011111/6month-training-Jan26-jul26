-- Sales Manager role — dedicated CRM department login
USE `tyre_erp`;

INSERT INTO users (full_name, email, username, password_hash, role, status, must_change_password)
SELECT 'Sales Manager', 'sales@ralson.local', 'salesmgr', '$2y$10$PMHkpYKirj3dQrnJR31zveSQwM7eI1q2E/Sd5MyPQm.ODO6wrk/fG', 'Sales Manager', 'active', 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'salesmgr' LIMIT 1);
