-- Migration v2: Drivers, Payments, MTN MoMo support
-- Run this after schema.sql (safe to re-run)

-- Drivers table
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

-- Payments table
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

-- Add fare column to buses
ALTER TABLE buses ADD COLUMN IF NOT EXISTS fare DECIMAL(10,2) DEFAULT 500.00 AFTER total_seats;

-- Add driver_id to buses
ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_id INT DEFAULT NULL AFTER fare;
ALTER TABLE buses ADD FOREIGN KEY IF NOT EXISTS (driver_id) REFERENCES drivers(id) ON DELETE SET NULL;

-- Add phone number to payments for reference
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) DEFAULT 500.00 AFTER payment_ref;
