-- First, backup the existing data
CREATE TABLE IF NOT EXISTS memberships_backup AS SELECT * FROM memberships;

-- Modify the memberships table
ALTER TABLE memberships
    MODIFY COLUMN userIdIndex INT(6) UNSIGNED NOT NULL,
    MODIFY COLUMN invoices TEXT NOT NULL,
    MODIFY COLUMN club VARCHAR(255) NOT NULL,
    MODIFY COLUMN unpaid DECIMAL(10,2) NOT NULL DEFAULT 0,
    MODIFY COLUMN paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    MODIFY COLUMN athleteData TEXT NOT NULL,
    ADD COLUMN last_invoice_date TIMESTAMP NULL,
    ADD COLUMN last_payment_date TIMESTAMP NULL,
    ADD PRIMARY KEY (userIdIndex),
    ADD INDEX idx_club (club),
    ADD INDEX idx_unpaid (unpaid),
    ADD INDEX idx_paid (paid);

-- Update the invoices column to store JSON data with this structure:
-- {
--   "invoices": [
--     {
--       "id": "unique_id",
--       "amount": 100.00,
--       "due_date": "2024-03-20",
--       "payment_method": "bank_transfer",
--       "status": "pending",
--       "created_at": "2024-03-13 10:00:00",
--       "bank_details": {
--         "id": 1,
--         "account_name": "John Doe",
--         "account_number": "12345678",
--         "sort_code": "12-34-56",
--         "bank_name": "Bank Name"
--       }
--     }
--   ]
-- } 