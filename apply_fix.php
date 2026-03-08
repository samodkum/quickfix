<?php
require_once 'config/db.php';

try {
    $pdo->exec("USE quickfix_db");

    // 1. Fix Services Table Schema
    echo "Updating services table...\n";
    try {
        $pdo->exec("ALTER TABLE services ADD COLUMN location VARCHAR(100) DEFAULT 'All Locations' AFTER category");
        echo "Column 'location' added.\n";
    } catch (PDOException $e) {
        echo "Column 'location' might already exist.\n";
    }

    if (!columnExists($pdo, 'services', 'deleted_at')) {
        $pdo->exec("ALTER TABLE services ADD COLUMN deleted_at DATETIME NULL");
    }
    if (!columnExists($pdo, 'services', 'is_active')) {
        $pdo->exec("ALTER TABLE services ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
    }

    // 2. Seed Locations for Services
    $pdo->exec("UPDATE services SET location = 'All Locations'");
    $pdo->exec("UPDATE services SET location = 'New Delhi' WHERE id % 3 = 1");
    $pdo->exec("UPDATE services SET location = 'Mumbai' WHERE id % 3 = 2");
    echo "Services seeded with locations.\n";

    // 3. Fix States and Cities to match Header
    echo "Updating states and cities...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE service_available_cities");
    $pdo->exec("TRUNCATE TABLE cities");
    $pdo->exec("TRUNCATE TABLE states");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Add necessary states
    $stmt = $pdo->prepare("INSERT INTO states (state_name) VALUES (?)");
    $stmt->execute(['Delhi']);
    $delhi_id = $pdo->lastInsertId();
    
    $stmt->execute(['Maharashtra']);
    $maha_id = $pdo->lastInsertId();
    
    $stmt->execute(['Karnataka']);
    $kar_id = $pdo->lastInsertId();

    // Add necessary cities
    $stmt = $pdo->prepare("INSERT INTO cities (state_id, city_name) VALUES (?, ?)");
    $stmt->execute([$delhi_id, 'New Delhi']);
    $delhi_city_id = $pdo->lastInsertId();
    
    $stmt->execute([$maha_id, 'Mumbai']);
    $mumbai_city_id = $pdo->lastInsertId();
    
    $stmt->execute([$kar_id, 'Bangalore']);
    $blr_city_id = $pdo->lastInsertId();

    // Link services to cities (for service_available_cities table used in booking.php)
    $services = $pdo->query("SELECT id, location FROM services")->fetchAll();
    $sac = $pdo->prepare("INSERT INTO service_available_cities (service_id, city_id) VALUES (?, ?)");
    foreach ($services as $s) {
        if ($s['location'] === 'All Locations') {
            $sac->execute([$s['id'], $delhi_city_id]);
            $sac->execute([$s['id'], $mumbai_city_id]);
            $sac->execute([$s['id'], $blr_city_id]);
        } elseif ($s['location'] === 'New Delhi') {
            $sac->execute([$s['id'], $delhi_city_id]);
        } elseif ($s['location'] === 'Mumbai') {
            $sac->execute([$s['id'], $mumbai_city_id]);
        } elseif ($s['location'] === 'Bangalore') {
            $sac->execute([$s['id'], $blr_city_id]);
        }
    }
    echo "States, Cities, and Availability synced.\n";

    // 4. Fix Images (ensure reliable placeholders if original ones fail)
    echo "Updating images...\n";
    // We'll use more robust placeholder images just in case
    $pdo->exec("UPDATE services SET image_url = 'https://plus.unsplash.com/premium_photo-1664303228186-a61e7dc91597?auto=format&fit=crop&w=800&q=80' WHERE title LIKE '%Electrician%'");
    $pdo->exec("UPDATE services SET image_url = 'https://images.unsplash.com/photo-1504148455328-c376907d081c?auto=format&fit=crop&w=800&q=80' WHERE title LIKE '%Plumb%'");
    $pdo->exec("UPDATE services SET image_url = 'https://images.unsplash.com/photo-1521206698660-5e077ff6f9c8?auto=format&fit=crop&w=800&q=80' WHERE title LIKE '%Gas%'");
    $pdo->exec("UPDATE services SET image_url = 'https://images.unsplash.com/photo-1582139329536-e7284fece509?auto=format&fit=crop&w=800&q=80' WHERE title LIKE '%Lock%'");

    echo "Fix applied successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}
