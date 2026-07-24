-- Migration: Add 4-role system (admin, manager, driver, passenger)
-- Run this ONCE on existing database

USE bus_tracking_db;

-- 1. Create routes table
CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(100) NOT NULL,
    origin VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    base_price DECIMAL(10,2) DEFAULT 500.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Add route_id to buses table
ALTER TABLE buses ADD COLUMN route_id INT DEFAULT NULL AFTER fare;
ALTER TABLE buses ADD FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL;

-- 3. Add user_id to drivers table (links driver to a login account)
ALTER TABLE drivers ADD COLUMN user_id INT DEFAULT NULL AFTER phone;
ALTER TABLE drivers ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 4. Update users.role ENUM to include all 4 roles
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'driver', 'passenger') DEFAULT 'passenger';

-- 5. Migrate existing 'user' role to 'passenger'
UPDATE users SET role = 'passenger' WHERE role = 'user';

-- 6. Seed default routes
INSERT INTO routes (route_name, origin, destination, base_price, status) VALUES
('Kigali Express Route 1', 'Kigali City Center', 'Kimironko', 500.00, 'active'),
('Kigali Express Route 2', 'Kigali City Center', 'Nyabugogo', 500.00, 'active'),
('Kigali Express Route 3', 'Kigali City Center', 'Remera', 500.00, 'active');

-- 7. Link existing buses to routes
UPDATE buses SET route_id = 1 WHERE bus_code = 'BUS001';
UPDATE buses SET route_id = 2 WHERE bus_code = 'BUS002';
UPDATE buses SET route_id = 3 WHERE bus_code = 'BUS003';

-- 8. Seed manager user (password: manager123)
INSERT INTO users (full_name, email, phone, password, role) VALUES
('System Manager', 'manager@bus.com', '+250788000001', '$2y$10$z0XedUA97boWTZGCK7541.thamvg9iZucKaGTLgntBMzAMd.CZ2GO', 'manager');

-- 9. Seed driver user account (password: driver123)
INSERT INTO users (full_name, email, phone, password, role) VALUES
('Jean Driver', 'driver@bus.com', '+250788000002', '$2y$10$z0XedUA97boWTZGCK7541.thamvg9iZucKaGTLgntBMzAMd.CZ2GO', 'driver');

-- 10. Link driver user to drivers table record
UPDATE drivers SET user_id = (SELECT id FROM users WHERE email = 'driver@bus.com') WHERE id = 1;

-- 11. Update admin role check (ensure admin remains admin)
UPDATE users SET role = 'admin' WHERE email = 'admin@bus.com';
