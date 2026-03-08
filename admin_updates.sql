-- Add new columns to existing tables
ALTER TABLE users ADD COLUMN status ENUM('active', 'blocked') DEFAULT 'active';
ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE services ADD COLUMN is_active BOOLEAN DEFAULT TRUE;

ALTER TABLE bookings ADD COLUMN technician_id INT NULL;
ALTER TABLE bookings ADD COLUMN internal_notes TEXT;
ALTER TABLE bookings ADD COLUMN payment_status ENUM('pending', 'completed') DEFAULT 'pending';

-- Create technicians table
CREATE TABLE IF NOT EXISTS technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    specialty VARCHAR(100),
    status ENUM('available', 'busy', 'offline') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add foreign key constraint to bookings for technicians
ALTER TABLE bookings ADD CONSTRAINT fk_booking_technician FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL;

-- Create settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT
);

-- Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
('site_name', 'QuickFix Emergency Services'),
('contact_email', 'support@quickfix.com'),
('contact_phone', '1-800-555-0199'),
('currency', 'USD');

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    type ENUM('success', 'warning', 'info', 'danger') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
