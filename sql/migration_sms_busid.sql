-- Migration: Add bus_id to sms_logs for per-bus SMS routing
-- Safe to re-run

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_logs' AND COLUMN_NAME = 'bus_id');
SET @sql = IF(@exists = 0, 'ALTER TABLE sms_logs ADD COLUMN bus_id INT DEFAULT NULL AFTER booking_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill existing sms_logs from bookings -> buses
UPDATE sms_logs sl
    JOIN bookings b ON sl.booking_id = b.id
    SET sl.bus_id = b.bus_id
    WHERE sl.bus_id IS NULL;
