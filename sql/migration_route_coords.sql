-- Migration: Add coordinates to routes for ETA calculation
-- Safe to re-run

ALTER TABLE routes ADD COLUMN IF NOT EXISTS origin_lat DECIMAL(10,7) DEFAULT NULL AFTER origin;
ALTER TABLE routes ADD COLUMN IF NOT EXISTS origin_lng DECIMAL(10,7) DEFAULT NULL AFTER origin_lat;
ALTER TABLE routes ADD COLUMN IF NOT EXISTS dest_lat DECIMAL(10,7) DEFAULT NULL AFTER destination;
ALTER TABLE routes ADD COLUMN IF NOT EXISTS dest_lng DECIMAL(10,7) DEFAULT NULL AFTER dest_lat;

-- Kigali route coordinates
UPDATE routes SET origin_lat = -1.9403, origin_lng = 30.0616, dest_lat = -1.9381, dest_lng = 30.0934 WHERE id = 1;
UPDATE routes SET origin_lat = -1.9403, origin_lng = 30.0616, dest_lat = -1.9536, dest_lng = 30.0515 WHERE id = 2;
UPDATE routes SET origin_lat = -1.9403, origin_lng = 30.0616, dest_lat = -1.9536, dest_lng = 30.0942 WHERE id = 3;
