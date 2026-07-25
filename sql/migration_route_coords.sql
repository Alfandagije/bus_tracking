-- Migration: Add coordinates to routes for ETA calculation
-- Safe to re-run (checks column existence before adding)

-- Add columns if they don't exist (MySQL 5.7 compatible)
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'origin_lat');
SET @sql = IF(@exists = 0, 'ALTER TABLE routes ADD COLUMN origin_lat DECIMAL(10,7) DEFAULT NULL AFTER `origin`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'origin_lng');
SET @sql = IF(@exists = 0, 'ALTER TABLE routes ADD COLUMN origin_lng DECIMAL(10,7) DEFAULT NULL AFTER origin_lat', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'dest_lat');
SET @sql = IF(@exists = 0, 'ALTER TABLE routes ADD COLUMN dest_lat DECIMAL(10,7) DEFAULT NULL AFTER `destination`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'dest_lng');
SET @sql = IF(@exists = 0, 'ALTER TABLE routes ADD COLUMN dest_lng DECIMAL(10,7) DEFAULT NULL AFTER dest_lat', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Kigali route coordinates
UPDATE routes SET origin_lat = -1.9403, origin_lng = 30.0616, dest_lat = -1.9381, dest_lng = 30.0934 WHERE id = 1;
UPDATE routes SET origin_lat = -1.9403, origin_lng = 30.0616, dest_lat = -1.9536, dest_lng = 30.0515 WHERE id = 2;
UPDATE routes SET origin_lat = -1.9403, origin_lng = 30.0616, dest_lat = -1.9536, dest_lng = 30.0942 WHERE id = 3;
