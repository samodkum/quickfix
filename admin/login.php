<?php
// admin/login.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/db.php';

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] === 'admin') {
                if ($user['status'] === 'blocked') {
                    $error = "This admin account has been blocked.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];
                    // Added for session timeout feature later
                    $_SESSION['last_activity'] = time();
                    
                    // Update last login
                    $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update_stmt->execute([$user['id']]);

                    header('Location: index.php');
                    exit();
                }
            } else {
                $error = "Access denied. You do not have top-level administrative privileges.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickFix - Admin Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }
        .admin-login-card {
            background: var(--card-bg);
            padding: 48px;
            border-radius: var(--border-radius-lg);
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>
<body>
    <div class="admin-login-card">
        <div style="text-align: center; margin-bottom: 32px;">
            <i class="fa-solid fa-screwdriver-wrench" style="font-size: 3rem; color: var(--accent-color); margin-bottom: 16px;"></i>
            <h1 style="font-size: 1.8rem; margin: 0; color: var(--text-main); font-weight: 800;">QuickFix Admin</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 8px;">Restricted Access Portal</p>
        </div>

        <?php if ($error): ?>
            <div style="background: #FEF2F2; color: var(--danger-color); padding: 12px; border-radius: var(--border-radius-md); border: 1px solid #FECACA; text-align: center; margin-bottom: 24px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
            <div class="form-group" style="margin: 0;">
                <label style="font-weight: 600;">Admin Email</label>
                <div class="input-with-icon" style="position: relative;">
                    <i class="fa-solid fa-envelope" style="position: absolute; left: 16px; top: 16px; color: var(--text-muted);"></i>
                    <input type="email" name="email" required class="form-control" style="padding-left: 48px;" placeholder="admin@quickfix.com" autofocus>
                </div>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label style="font-weight: 600;">Password</label>
                <div class="input-with-icon" style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 16px; top: 16px; color: var(--text-muted);"></i>
                    <input type="password" name="password" required class="form-control" style="padding-left: 48px;" placeholder="••••••••">
                </div>
                <div style="text-align: right; margin-top: 8px;">
                    <a href="#" onclick="alert('Please contact the superadmin to reset your password.')" style="color: var(--accent-color); font-size: 0.85rem; font-weight: 500;">Forgot Password?</a>
                </div>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; font-size: 1.05rem; margin-top: 8px;">Secure Login</button>
            <div style="text-align: center; margin-top: 24px;">
                <a href="../index.php" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Back to Public Site</a>
            </div>
        </form>
    </div>
</body>
</html>
