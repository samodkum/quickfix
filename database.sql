-- Create the database if it doesn't already exist
CREATE DATABASE IF NOT EXISTS quickfix_db;

-- Select the database to use for the following tables
USE quickfix_db;

-- Create the users table to store both regular users and admins
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY, -- Unique ID for each user, auto-increases automatically
    name VARCHAR(100) NOT NULL, -- User's full name, cannot be empty
    email VARCHAR(100) NOT NULL UNIQUE, -- User's email, must be unique (no duplicates allowed) and not empty
    password VARCHAR(255) NOT NULL, -- Hashed password for security (never store plain text passwords)
    role ENUM('user', 'admin') DEFAULT 'user', -- Role to distinguish admin from regular user. Default is 'user'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- Automatically stores the exact date and time when the user registered
);

-- Create the services table to store available emergency services offered by the platform
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY, -- Unique ID for the service
    title VARCHAR(150) NOT NULL, -- Name of the service (e.g., 'Emergency Electrician')
    description TEXT NOT NULL, -- Detailed description of what the service includes
    price DECIMAL(10, 2) NOT NULL, -- Base price of the service, allows up to 2 decimal places (e.g., 50.00)
    category VARCHAR(100) NOT NULL -- Category to which the service belongs (e.g., 'Plumbing')
);

-- Insert some default initial services into the services table so the site is not empty
INSERT INTO services (title, description, price, category) VALUES
('Emergency Electrician', 'Fast repair for short circuits, power outages, and faulty wiring.', 50.00, 'Electrical'),
('Plumbing Rescue', 'Quick fix for burst pipes, severe leaks, and blocked drains.', 60.00, 'Plumbing'),
('Gas Leak Repair', 'Urgent gas leak detection and sealing to ensure safety.', 80.00, 'Gas'),
('Lockout Assistance', 'Emergency lock opening and replacement for home or office.', 45.00, 'Locksmith');

-- Create the bookings table to store user service requests/orders
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY, -- Unique ID for the specific booking
    user_id INT NOT NULL, -- ID of the user who made the booking
    service_id INT NOT NULL, -- ID of the requested service
    problem_description TEXT NOT NULL, -- User's description of their specific issue
    emergency_level ENUM('Low', 'Medium', 'High') DEFAULT 'Medium', -- Priority level of the request (default 'Medium')
    address TEXT NOT NULL, -- Full location address where the service is needed
    contact_number VARCHAR(20) NOT NULL, -- Phone number for the service provider to contact the user
    preferred_time VARCHAR(100) NOT NULL, -- When the user wants the service (e.g., 'ASAP', 'Today 5PM')
    status ENUM('Requested', 'Accepted', 'In Progress', 'Completed') DEFAULT 'Requested', -- Current status of the job in the system
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- When the booking was officially requested
    -- Foreign keys link tables together to maintain data integrity
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- If a user is deleted, automatically delete all their bookings
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE -- If a service is removed, delete all associated bookings
);
