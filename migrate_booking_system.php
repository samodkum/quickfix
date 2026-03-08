<?php
require_once __DIR__ . '/config/db.php';

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?"
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $indexName): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?"
    );
    $stmt->execute([$table, $indexName]);
    return (int)$stmt->fetchColumn() > 0;
}

function tryExec(PDO $pdo, string $sql): void {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // Keep migrations resilient across partially-migrated DBs.
    }
}

try {
    $pdo->exec("USE quickfix_db");

    // ---------------------------
    // Settings (for API keys, supported cities, etc)
    // ---------------------------
    if (!tableExists($pdo, 'settings')) {
        $pdo->exec(
            "CREATE TABLE settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT
            )"
        );
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['supported_cities', 'New Delhi,Mumbai,Bangalore']);
    // Legacy key, no longer used (manual address system).
    $stmt->execute(['google_maps_api_key', '']);
    $stmt->execute(['contact_email', 'support@quickfix.com']);
    $stmt->execute(['sms_api_url', '']);
    $stmt->execute(['sms_api_key', '']);
    $stmt->execute(['sms_sender_id', 'QuickFix']);

    // ---------------------------
    // Normalized manual location tables (states/cities)
    // ---------------------------
    if (!tableExists($pdo, 'states')) {
        $pdo->exec(
            "CREATE TABLE states (
                id INT AUTO_INCREMENT PRIMARY KEY,
                state_name VARCHAR(100) NOT NULL UNIQUE
            )"
        );
    }

    if (!tableExists($pdo, 'cities')) {
        $pdo->exec(
            "CREATE TABLE cities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                state_id INT NOT NULL,
                city_name VARCHAR(100) NOT NULL,
                UNIQUE KEY uq_city_state (state_id, city_name),
                INDEX idx_cities_state (state_id),
                CONSTRAINT fk_cities_state FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE
            )"
        );
    }

    // Seed sample data (idempotent)
    $pdo->prepare("INSERT IGNORE INTO states (state_name) VALUES (?), (?)")
        ->execute(['Gujarat', 'Maharashtra']);

    $guj_id = (int)$pdo->query("SELECT id FROM states WHERE state_name = 'Gujarat' LIMIT 1")->fetchColumn();
    $mah_id = (int)$pdo->query("SELECT id FROM states WHERE state_name = 'Maharashtra' LIMIT 1")->fetchColumn();
    if ($guj_id > 0) {
        $c = $pdo->prepare("INSERT IGNORE INTO cities (state_id, city_name) VALUES (?, ?), (?, ?)");
        $c->execute([$guj_id, 'Surat', $guj_id, 'Ahmedabad']);
    }
    if ($mah_id > 0) {
        $c = $pdo->prepare("INSERT IGNORE INTO cities (state_id, city_name) VALUES (?, ?), (?, ?)");
        $c->execute([$mah_id, 'Mumbai', $mah_id, 'Pune']);
    }

    // ---------------------------
    // Coupons
    // ---------------------------
    if (!tableExists($pdo, 'coupons')) {
        $pdo->exec(
            "CREATE TABLE coupons (
                code VARCHAR(50) PRIMARY KEY,
                discount_type ENUM('percentage','fixed') NOT NULL,
                discount_value DECIMAL(10,2) NOT NULL,
                expiry_date DATE NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    // ---------------------------
    // Booking slots
    // ---------------------------
    if (!tableExists($pdo, 'booking_slots')) {
        $pdo->exec(
            "CREATE TABLE booking_slots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_id INT NOT NULL,
                date DATE NOT NULL,
                time TIME NOT NULL,
                available_count INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_slot (service_id, date, time),
                INDEX idx_slot_lookup (service_id, date, time),
                CONSTRAINT fk_slot_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
            )"
        );
    }

    // ---------------------------
    // Booking sequences (for BK IDs)
    // ---------------------------
    if (!tableExists($pdo, 'booking_sequences')) {
        $pdo->exec(
            "CREATE TABLE booking_sequences (
                year INT PRIMARY KEY,
                next_number INT NOT NULL
            )"
        );
    }

    // ---------------------------
    // Technicians (extend existing table if present)
    // ---------------------------
    if (!tableExists($pdo, 'technicians')) {
        $pdo->exec(
            "CREATE TABLE technicians (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NULL UNIQUE,
                phone VARCHAR(20) NULL,
                specialty VARCHAR(100) NULL,
                photo VARCHAR(255) NULL,
                experience INT NULL,
                skills TEXT NULL,
                service_id INT NULL,
                status ENUM('available','busy','offline') DEFAULT 'available',
                rating DECIMAL(3,2) NOT NULL DEFAULT 0,
                total_reviews INT NOT NULL DEFAULT 0,
                total_jobs_completed INT NOT NULL DEFAULT 0,
                deleted_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tech_service (service_id),
                CONSTRAINT fk_tech_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
            )"
        );
    } else {
        if (!columnExists($pdo, 'technicians', 'email')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN email VARCHAR(255) NULL UNIQUE");
        if (!columnExists($pdo, 'technicians', 'phone')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN phone VARCHAR(20) NULL");
        if (!columnExists($pdo, 'technicians', 'specialty')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN specialty VARCHAR(100) NULL");
        if (!columnExists($pdo, 'technicians', 'photo')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN photo VARCHAR(255) NULL");
        if (!columnExists($pdo, 'technicians', 'experience')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN experience INT NULL");
        if (!columnExists($pdo, 'technicians', 'skills')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN skills TEXT NULL");
        if (!columnExists($pdo, 'technicians', 'service_id')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN service_id INT NULL");
        if (!columnExists($pdo, 'technicians', 'rating')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN rating DECIMAL(3,2) NOT NULL DEFAULT 0");
        if (!columnExists($pdo, 'technicians', 'total_reviews')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN total_reviews INT NOT NULL DEFAULT 0");
        if (!columnExists($pdo, 'technicians', 'total_jobs_completed')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN total_jobs_completed INT NOT NULL DEFAULT 0");
        if (!columnExists($pdo, 'technicians', 'deleted_at')) tryExec($pdo, "ALTER TABLE technicians ADD COLUMN deleted_at DATETIME NULL");
        if (!indexExists($pdo, 'technicians', 'idx_tech_service')) tryExec($pdo, "ALTER TABLE technicians ADD INDEX idx_tech_service (service_id)");
        tryExec($pdo, "ALTER TABLE technicians ADD CONSTRAINT fk_tech_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL");
    }

    // ---------------------------
    // Soft delete fields on core tables
    // ---------------------------
    if (!columnExists($pdo, 'services', 'is_active')) tryExec($pdo, "ALTER TABLE services ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
    if (!columnExists($pdo, 'services', 'deleted_at')) tryExec($pdo, "ALTER TABLE services ADD COLUMN deleted_at DATETIME NULL");
    if (!columnExists($pdo, 'users', 'deleted_at')) tryExec($pdo, "ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL");

    // ---------------------------
    // Bookings (extend existing)
    // ---------------------------
    if (!tableExists($pdo, 'bookings')) {
        throw new RuntimeException("Expected bookings table to exist (from database.sql).");
    }

    if (!columnExists($pdo, 'bookings', 'booking_unique_id')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN booking_unique_id VARCHAR(20) NULL");
    if (!indexExists($pdo, 'bookings', 'uq_booking_unique_id')) tryExec($pdo, "ALTER TABLE bookings ADD UNIQUE KEY uq_booking_unique_id (booking_unique_id)");

    if (!columnExists($pdo, 'bookings', 'technician_count')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN technician_count INT NOT NULL DEFAULT 1");
    if (!columnExists($pdo, 'bookings', 'service_date')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN service_date DATE NULL");
    if (!columnExists($pdo, 'bookings', 'service_time')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN service_time TIME NULL");
    if (!columnExists($pdo, 'bookings', 'latitude')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN latitude DECIMAL(10,7) NULL");
    if (!columnExists($pdo, 'bookings', 'longitude')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN longitude DECIMAL(10,7) NULL");
    if (!columnExists($pdo, 'bookings', 'contact')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN contact VARCHAR(20) NULL");
    if (!columnExists($pdo, 'bookings', 'payment_method')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN payment_method ENUM('upi','card','netbanking','cash') NULL");
    if (!columnExists($pdo, 'bookings', 'coupon_code')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN coupon_code VARCHAR(50) NULL");
    if (!columnExists($pdo, 'bookings', 'discount_amount')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0");
    if (!columnExists($pdo, 'bookings', 'subtotal_amount')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN subtotal_amount DECIMAL(10,2) NULL");
    if (!columnExists($pdo, 'bookings', 'total_amount')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(10,2) NULL");
    if (!columnExists($pdo, 'bookings', 'cancel_reason')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN cancel_reason TEXT NULL");
    if (!columnExists($pdo, 'bookings', 'cancelled_at')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN cancelled_at DATETIME NULL");
    if (!columnExists($pdo, 'bookings', 'deleted_at')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN deleted_at DATETIME NULL");

    // Structured address fields (manual dropdown-based system)
    if (!columnExists($pdo, 'bookings', 'state')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN state VARCHAR(100) NULL");
    if (!columnExists($pdo, 'bookings', 'city')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN city VARCHAR(100) NULL");
    if (!columnExists($pdo, 'bookings', 'area')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN area VARCHAR(150) NULL");
    if (!columnExists($pdo, 'bookings', 'pincode')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN pincode VARCHAR(10) NULL");
    if (!columnExists($pdo, 'bookings', 'flat_no')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN flat_no VARCHAR(100) NULL");
    if (!columnExists($pdo, 'bookings', 'landmark')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN landmark VARCHAR(255) NULL");
    if (!columnExists($pdo, 'bookings', 'full_address')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN full_address TEXT NULL");

    // Backfill full_address for legacy rows
    tryExec($pdo, "UPDATE bookings SET full_address = address WHERE (full_address IS NULL OR full_address = '') AND address IS NOT NULL AND address != ''");

    // ---------------------------
    // Service availability by city (manual restriction)
    // ---------------------------
    if (!tableExists($pdo, 'service_available_cities')) {
        $pdo->exec(
            "CREATE TABLE service_available_cities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_id INT NOT NULL,
                city_id INT NOT NULL,
                UNIQUE KEY uq_service_city (service_id, city_id),
                INDEX idx_sac_service (service_id),
                INDEX idx_sac_city (city_id),
                CONSTRAINT fk_sac_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
                CONSTRAINT fk_sac_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
            )"
        );
    }

    // Expand status enum to support requested flow.
    tryExec(
        $pdo,
        "ALTER TABLE bookings MODIFY COLUMN status
         ENUM('Requested','Accepted','Technician Assigned','In Progress','Completed','Cancelled')
         DEFAULT 'Requested'"
    );

    // Ensure technician_id FK exists (admin_updates.sql may already add it).
    if (!columnExists($pdo, 'bookings', 'technician_id')) tryExec($pdo, "ALTER TABLE bookings ADD COLUMN technician_id INT NULL");
    tryExec($pdo, "ALTER TABLE bookings ADD CONSTRAINT fk_booking_technician FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL");

    // ---------------------------
    // Booking logs (status history)
    // ---------------------------
    if (!tableExists($pdo, 'booking_logs')) {
        $pdo->exec(
            "CREATE TABLE booking_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                old_status VARCHAR(50) NULL,
                new_status VARCHAR(50) NOT NULL,
                note TEXT NULL,
                changed_by_user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_booking_logs_booking (booking_id),
                CONSTRAINT fk_booking_logs_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
            )"
        );
    }

    // ---------------------------
    // Reviews
    // ---------------------------
    if (!tableExists($pdo, 'reviews')) {
        $pdo->exec(
            "CREATE TABLE reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                user_id INT NOT NULL,
                technician_id INT NOT NULL,
                rating INT NOT NULL,
                review_text TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_review_booking_user (booking_id, user_id),
                INDEX idx_reviews_tech (technician_id),
                CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_reviews_tech FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE CASCADE
            )"
        );
    }

    // ---------------------------
    // Notifications (user-level)
    // ---------------------------
    if (!tableExists($pdo, 'notifications')) {
        $pdo->exec(
            "CREATE TABLE notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notifications_user (user_id),
                CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        );
    } else {
        if (!columnExists($pdo, 'notifications', 'user_id')) tryExec($pdo, "ALTER TABLE notifications ADD COLUMN user_id INT NULL");
        if (!columnExists($pdo, 'notifications', 'is_read')) tryExec($pdo, "ALTER TABLE notifications ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
        if (!indexExists($pdo, 'notifications', 'idx_notifications_user')) tryExec($pdo, "ALTER TABLE notifications ADD INDEX idx_notifications_user (user_id)");
        tryExec($pdo, "ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    }

    echo "Booking system migration completed.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
}

