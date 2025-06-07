USE virtual_office;

-- Add end_time column to queues table
ALTER TABLE queues
ADD COLUMN end_time DATETIME NULL AFTER start_time;

-- Update existing queues to have an end_time based on start_time + default_duration
UPDATE queues 
SET end_time = DATE_ADD(start_time, INTERVAL default_duration MINUTE)
WHERE start_time IS NOT NULL AND end_time IS NULL; 