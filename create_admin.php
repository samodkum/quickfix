<?php
require_once 'config/db.php';

$name = 'System Admin';
$email = 'admin@quickfix.com';
$password = password_hash('admin123', PASSWORD_BCRYPT);
$role = 'admin';

try {
    // Check if user already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    
    if ($check->fetch()) {
        echo "Admin user already exists. Updating password...<br>";
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE email = ?");
        $stmt->execute([$password, $email]);
    } else {
        echo "Creating new admin user...<br>";
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role]);
    }
    echo "Successfully created/updated admin account!<br>";
    echo "Email: <b>$email</b><br>";
    echo "Password: <b>admin123</b><br>";
    echo "<br><a href='admin/login.php'>Go to Login</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
