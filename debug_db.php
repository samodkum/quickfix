<?php
require_once 'config/db.php';

try {
    $stmt = $pdo->query("SELECT email, role FROM users WHERE role = 'admin'");
    echo "Admin Users:<br>";
    while ($row = $stmt->fetch()) {
        echo "- Email: <b>" . $row['email'] . "</b> (Role: " . $row['role'] . ")<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
