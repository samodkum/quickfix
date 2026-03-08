<?php
/**
 * migrate_slots.php — Run once to add booking_date and booking_time columns
 */
require_once 'config/db.php';

try {
    // Add booking_date column to bookings table
    try {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN booking_date DATE NULL AFTER status");
        echo "Column 'booking_date' added.\n";
    } catch (PDOException $e) {
        echo "Column 'booking_date' may already exist.\n";
    }

    // Add booking_time column to bookings table
    try {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN booking_time VARCHAR(10) NULL AFTER booking_date");
        echo "Column 'booking_time' added.\n";
    } catch (PDOException $e) {
        echo "Column 'booking_time' may already exist.\n";
    }

    // Update existing bookings with default date/time if null
    $pdo->exec("UPDATE bookings SET booking_date = DATE(created_at), booking_time = '09:00' WHERE booking_date IS NULL");
    echo "Existing bookings updated with defaults.\n";

    echo "\nMigration complete!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
