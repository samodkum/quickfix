<?php
/**
 * api/available_slots.php
 * Returns available hourly time slots for a given TECHNICIAN on a given date.
 * Each technician has their own independent slot availability.
 */
date_default_timezone_set('Asia/Kolkata'); // IST
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
$tech_id    = isset($_GET['tech_id'])    ? (int)$_GET['tech_id']    : 0;
$date       = isset($_GET['date'])       ? trim($_GET['date'])       : '';

// Basic validation
if ($tech_id <= 0 || $service_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid tech_id, service_id or date']);
    exit();
}

// Don't allow past dates
if ($date < date('Y-m-d')) {
    echo json_encode(['ok' => true, 'slots' => [], 'message' => 'Date is in the past']);
    exit();
}

// All possible hourly slots (9AM to 7PM)
$all_slots = [
    '09:00', '10:00', '11:00', '12:00',
    '13:00', '14:00', '15:00', '16:00',
    '17:00', '18:00', '19:00'
];

// If today, remove slots that have already passed
if ($date === date('Y-m-d')) {
    $current_hour = (int)date('H');
    $all_slots = array_filter($all_slots, function($slot) use ($current_hour) {
        return (int)substr($slot, 0, 2) > $current_hour;
    });
    $all_slots = array_values($all_slots);
}

try {
    // Validate the technician belongs to this service
    $tech_stmt = $pdo->prepare(
        "SELECT id, name FROM technicians WHERE id = ? AND service_id = ? AND deleted_at IS NULL LIMIT 1"
    );
    $tech_stmt->execute([$tech_id, $service_id]);
    $tech = $tech_stmt->fetch();

    if (!$tech) {
        echo json_encode(['ok' => false, 'error' => 'Technician not found for this service']);
        exit();
    }

    // Capacity = 1 per technician per slot (one technician = one job at a time)
    $capacity = 1;

    // Get slots already booked by THIS specific technician on this date
    $booked_stmt = $pdo->prepare(
        "SELECT TIME_FORMAT(service_time, '%H:%i') as booking_time, COUNT(*) as booked_count
         FROM bookings
         WHERE technician_id = ? AND service_date = ? AND status NOT IN ('Cancelled')
         GROUP BY service_time"
    );
    $booked_stmt->execute([$tech_id, $date]);
    $booked_map = [];
    foreach ($booked_stmt->fetchAll() as $row) {
        $booked_map[$row['booking_time']] = (int)$row['booked_count'];
    }

    // Build slot list with availability
    $result = [];
    foreach ($all_slots as $slot) {
        $booked    = $booked_map[$slot] ?? 0;
        $available = $booked < $capacity;

        // Format for display: 09:00 -> 9:00 AM
        $hour    = (int)substr($slot, 0, 2);
        $display = ($hour === 12 ? 12 : $hour % 12) . ':00 ' . ($hour < 12 ? 'AM' : 'PM');

        $result[] = [
            'time'      => $slot,
            'display'   => $display,
            'available' => $available,
            'remaining' => $available ? 1 : 0,
            'capacity'  => $capacity,
        ];
    }

    echo json_encode(['ok' => true, 'slots' => $result]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
