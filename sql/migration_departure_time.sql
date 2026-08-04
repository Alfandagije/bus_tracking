-- Migration: Add departure_time to buses
-- Run this once (phpMyAdmin / MySQL client). Safe to re-run.
-- Manager sets the daily departure time when assigning a bus.

SET @db = (SELECT DATABASE());

-- Add departure_time column if it does not exist yet
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'buses' AND COLUMN_NAME = 'departure_time');
SET @sql = IF(@col = 0, 'ALTER TABLE buses ADD COLUMN departure_time TIME DEFAULT NULL AFTER fare', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Default departure times for the seeded buses (only where not already set)
UPDATE buses SET departure_time = '08:00:00' WHERE departure_time IS NULL AND bus_code = 'BUS001';
UPDATE buses SET departure_time = '09:00:00' WHERE departure_time IS NULL AND bus_code = 'BUS002';
UPDATE buses SET departure_time = '10:00:00' WHERE departure_time IS NULL AND bus_code = 'BUS003';
