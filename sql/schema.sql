-- Smart Multi-Bus IoT Tracking & Ticketing System
-- MySQL Database Schema

CREATE DATABASE IF NOT EXISTS bus_tracking_db;
USE bus_tracking_db;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Buses table
CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_code VARCHAR(20) UNIQUE NOT NULL,
    bus_name VARCHAR(100) NOT NULL,
    total_seats INT DEFAULT 30,
    fare DECIMAL(10,2) DEFAULT 500.00,
    driver_id INT DEFAULT NULL,
    current_lat DECIMAL(10, 7) DEFAULT 0.0000000,
    current_lng DECIMAL(10, 7) DEFAULT 0.0000000,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seats table
CREATE TABLE seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    status ENUM('available', 'occupied', 'booked') DEFAULT 'available',
    ir_sensor_status ENUM('HIGH', 'LOW') DEFAULT 'LOW',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bus_seat (bus_id, seat_number)
) ENGINE=InnoDB;

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bus_id INT NOT NULL,
    seat_id INT NOT NULL,
    booking_date DATE NOT NULL,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_ref VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(10,2) DEFAULT 500.00,
    sms_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES seats(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- SMS Logs table
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Drivers table
CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    assigned_bus_id INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_bus_id) REFERENCES buses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Payments table
CREATE TABLE payments (
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

-- Insert default admin user (password: admin123)
INSERT INTO users (full_name, email, phone, password, role) VALUES
('System Admin', 'admin@bus.com', '+250788000000', '$2y$10$z0XedUA97boWTZGCK7541.thamvg9iZucKaGTLgntBMzAMd.CZ2GO', 'admin');

-- Insert default buses
INSERT INTO buses (bus_code, bus_name, total_seats, current_lat, current_lng) VALUES
('BUS001', 'Kigali Express Route 1', 4, -1.9440727, 30.0618848),
('BUS002', 'Kigali Express Route 2', 4, -1.9480000, 30.0580000),
('BUS003', 'Kigali Express Route 3', 4, -1.9500000, 30.0650000);

-- Insert seats for BUS001 (4 seats)
INSERT INTO seats (bus_id, seat_number, status) VALUES
(1, 'A1', 'available'), (1, 'A2', 'available'), (1, 'A3', 'available'), (1, 'A4', 'available');

-- Insert seats for BUS002 (4 seats)
INSERT INTO seats (bus_id, seat_number, status) VALUES
(2, 'A1', 'available'), (2, 'A2', 'available'), (2, 'A3', 'available'), (2, 'A4', 'available');

-- Insert seats for BUS003 (4 seats)
INSERT INTO seats (bus_id, seat_number, status) VALUES
(3, 'A1', 'available'), (3, 'A2', 'available'), (3, 'A3', 'available'), (3, 'A4', 'available');
