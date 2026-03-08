<?php
require 'config/db.php';
$stmt = $pdo->query('SELECT id, service_date, service_time, technician_id, status FROM bookings ORDER BY id DESC LIMIT 5');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
