<?php
require_once 'config/db.php';

echo "--- Services Table ---\n";
$stmt = $pdo->query("SELECT id, title, category, location, image_url FROM services LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n--- States Table ---\n";
try {
    $stmt = $pdo->query("SELECT * FROM states");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) { echo "States table error: " . $e->getMessage() . "\n"; }

echo "\n--- Cities Table ---\n";
try {
    $stmt = $pdo->query("SELECT * FROM cities LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) { echo "Cities table error: " . $e->getMessage() . "\n"; }
