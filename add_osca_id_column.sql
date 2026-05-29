-- Add osca_id column to persons table
ALTER TABLE persons ADD COLUMN osca_id VARCHAR(100) NULL AFTER birthdate;

-- Add index for better search performance (optional)
-- CREATE INDEX idx_osca_id ON persons(osca_id);
