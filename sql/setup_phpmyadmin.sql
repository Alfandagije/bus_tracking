SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_code VARCHAR(20) UNIQUE NOT NULL,
    bus_name VARCHAR(100) NOT NULL,
    total_seats INT DEFAULT 4,
    fare DECIMAL(10,2) DEFAULT 500.00,
    driver_id INT DEFAULT NULL,
    current_lat DECIMAL(10,7) DEFAULT 0.0000000,
    current_lng DECIMAL(10,7) DEFAULT 0.0000000,
    status ENUM('active','inactive','maintenance') DEFAULT 'active',
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    status ENUM('available','occupied','booked') DEFAULT 'available',
    ir_sensor_status ENUM('HIGH','LOW') DEFAULT 'LOW',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    UNIQUE KEY (bus_id, seat_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bus_id INT NOT NULL,
    seat_id INT NOT NULL,
    booking_date DATE NOT NULL,
    status ENUM('pending','paid','cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_ref VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(10,2) DEFAULT 500.00,
    sms_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES seats(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending','sent','failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    assigned_bus_id INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_bus_id) REFERENCES buses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) DEFAULT 'RWF',
    payment_method VARCHAR(50) DEFAULT 'MTN_MoMo',
    transaction_id VARCHAR(100) DEFAULT NULL,
    momo_ref VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'successful', 'failed', 'reversed') DEFAULT 'pending',
    payer_phone VARCHAR(20) DEFAULT NULL,
    api_response TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add missing columns to existing tables (idempotent)
SET @db = (SELECT DATABASE());

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'buses' AND COLUMN_NAME = 'fare');
SET @sql = IF(@col = 0, 'ALTER TABLE buses ADD COLUMN fare DECIMAL(10,2) DEFAULT 500.00 AFTER total_seats', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'buses' AND COLUMN_NAME = 'driver_id');
SET @sql = IF(@col = 0, 'ALTER TABLE buses ADD COLUMN driver_id INT DEFAULT NULL AFTER fare', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'amount');
SET @sql = IF(@col = 0, 'ALTER TABLE bookings ADD COLUMN amount DECIMAL(10,2) DEFAULT 500.00 AFTER payment_ref', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO users (full_name, email, phone, password, role) SELECT 'System Admin', 'admin@bus.com', '+250788000000', '$2y$10$z0XedUA97boWTZGCK7541.thamvg9iZucKaGTLgntBMzAMd.CZ2GO', 'admin' WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='admin@bus.com');

INSERT INTO buses (bus_code, bus_name, total_seats, fare, current_lat, current_lng) SELECT 'BUS001', 'Kigali Express Route 1', 4, 500.00, -1.9440727, 30.0618848 WHERE NOT EXISTS (SELECT 1 FROM buses WHERE bus_code='BUS001');
INSERT INTO buses (bus_code, bus_name, total_seats, fare, current_lat, current_lng) SELECT 'BUS002', 'Kigali Express Route 2', 4, 500.00, -1.9480000, 30.0580000 WHERE NOT EXISTS (SELECT 1 FROM buses WHERE bus_code='BUS002');
INSERT INTO buses (bus_code, bus_name, total_seats, fare, current_lat, current_lng) SELECT 'BUS003', 'Kigali Express Route 3', 4, 500.00, -1.9500000, 30.0650000 WHERE NOT EXISTS (SELECT 1 FROM buses WHERE bus_code='BUS003');

INSERT INTO seats (bus_id, seat_number, status) SELECT 1, 'A1', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=1 AND seat_number='A1');
INSERT INTO seats (bus_id, seat_number, status) SELECT 1, 'A2', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=1 AND seat_number='A2');
INSERT INTO seats (bus_id, seat_number, status) SELECT 1, 'A3', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=1 AND seat_number='A3');
INSERT INTO seats (bus_id, seat_number, status) SELECT 1, 'A4', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=1 AND seat_number='A4');
INSERT INTO seats (bus_id, seat_number, status) SELECT 2, 'A1', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=2 AND seat_number='A1');
INSERT INTO seats (bus_id, seat_number, status) SELECT 2, 'A2', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=2 AND seat_number='A2');
INSERT INTO seats (bus_id, seat_number, status) SELECT 2, 'A3', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=2 AND seat_number='A3');
INSERT INTO seats (bus_id, seat_number, status) SELECT 2, 'A4', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=2 AND seat_number='A4');
INSERT INTO seats (bus_id, seat_number, status) SELECT 3, 'A1', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=3 AND seat_number='A1');
INSERT INTO seats (bus_id, seat_number, status) SELECT 3, 'A2', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=3 AND seat_number='A2');
INSERT INTO seats (bus_id, seat_number, status) SELECT 3, 'A3', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=3 AND seat_number='A3');
INSERT INTO seats (bus_id, seat_number, status) SELECT 3, 'A4', 'available' WHERE NOT EXISTS (SELECT 1 FROM seats WHERE bus_id=3 AND seat_number='A4');

SET FOREIGN_KEY_CHECKS = 1;
