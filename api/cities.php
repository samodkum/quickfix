<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function jsonOut(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

$state_id = isset($_GET['state_id']) ? (int)$_GET['state_id'] : 0;
if ($state_id <= 0) {
    jsonOut(400, ['ok' => false, 'error' => 'Invalid state_id']);
}

try {
    $stmt = $pdo->prepare("SELECT id, city_name FROM cities WHERE state_id = ? ORDER BY city_name ASC");
    $stmt->execute([$state_id]);
    $cities = $stmt->fetchAll();
    jsonOut(200, ['ok' => true, 'cities' => $cities]);
} catch (PDOException $e) {
    jsonOut(500, ['ok' => false, 'error' => 'Unable to fetch cities']);
}

