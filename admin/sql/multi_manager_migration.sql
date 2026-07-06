-- Multi-Manager Migration
-- Add manager_id to tbl_employee
ALTER TABLE tbl_employee ADD COLUMN IF NOT EXISTS manager_id INT DEFAULT NULL AFTER id;
CREATE INDEX IF NOT EXISTS idx_employee_manager ON tbl_employee(manager_id);

-- Add manager_id to tbl_order
ALTER TABLE tbl_order ADD COLUMN IF NOT EXISTS manager_id INT DEFAULT NULL AFTER id;
CREATE INDEX IF NOT EXISTS idx_order_manager ON tbl_order(manager_id);

-- Add role column to tbl_user if not exists (Manager, Super Admin)
-- role column already exists, ensure managers have proper role
UPDATE tbl_user SET role = "Manager" WHERE role IS NULL OR role = "";

