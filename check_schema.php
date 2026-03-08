<?php
require_once 'config/db.php';

function describeTable($pdo, $table) {
    echo "--- Table: $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

describeTable($pdo, 'services');
describeTable($pdo, 'bookings');
describeTable($pdo, 'states');
describeTable($pdo, 'cities');
describeTable($pdo, 'users');
