<?php
require_once 'config/db.php';

try {
    $stmt = $pdo->query("SELECT email, role FROM users WHERE role = 'admin'");
    echo "<h1>Admin Users Found:</h1>";
    while ($row = $stmt->fetch()) {
        echo "<p>Email: <b>" . $row['email'] . "</b> (Role: " . $row['role'] . ")</p>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
