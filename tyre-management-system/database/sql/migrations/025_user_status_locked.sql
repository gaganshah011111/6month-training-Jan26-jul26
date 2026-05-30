-- User lifecycle: active, locked, inactive (remove enum restriction for locked state)
ALTER TABLE users MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active';
UPDATE users SET status = 'inactive' WHERE status IN ('frozen', 'terminated', 'deactivated');
UPDATE users SET status = 'active' WHERE status = '' OR status IS NULL;
