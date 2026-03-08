<?php
require_once 'config/db.php';
$sql = file_get_contents('admin_updates.sql');
try {
    $pdo->exec($sql);
    echo "Database migration successful.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
