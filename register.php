<?php
// register.php handles new user sign-ups
require_once 'config/db.php';

// Initialize variables to store any error messages or success messages we want to show the user
$error = '';
$success = '';

// Check if the form was submitted using the POST method (which is used for sending sensitive data)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Retrieve the data submitted in the form
    // trim() removes any accidental spaces someone might have typed at the beginning or end
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // BACKEND VALIDATION: Never trust the frontend! Always re-check data on the server.
    
    // 1. Check if any fields are empty
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } 
    // 2. Validate the email format (e.g., must have an @ symbol)
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } 
    // 3. Ensure the passwords match
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } 
    // 4. Ensure password is strong enough (minimum 6 characters for this example)
    elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } 
    // If all validations pass, proceed with database insertion
    else {
        try {
            // First, check if a user with this email already exists
            // We use a prepared statement (?) to prevent SQL Injection attacks
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            // Execute the query, passing the $email variable safely
            $stmt->execute([$email]);
            
            // If rowCount() is greater than 0, the email is already in the database
            if ($stmt->rowCount() > 0) {
                $error = "An account with this email already exists.";
            } else {
                // SECURITY CRUCIAL: Never store passwords in plain text!
                // password_hash() securely scrambles the password using modern algorithms like bcrypt
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Prepare the INSERT query. The default role in the DB is 'user'.
                $insert_stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                
                // Execute the insert. If successful, it returns true.
                if ($insert_stmt->execute([$name, $email, $hashed_password])) {
                    
                    // NEW: Inject Admin Notification Alert
                    $new_user_id = $pdo->lastInsertId();
                    try {
                        $notif = $pdo->prepare("INSERT INTO notifications (type, message, related_id) VALUES ('user_signup', ?, ?)");
                        $notif->execute(["New customer joined: $name", $new_user_id]);
                    } catch(PDOException $e) {}
                    
                    $success = "Registration successful! You can now login.";
                } else {
                    $error = "Something went wrong. Please try again later.";
                }
            }
        } catch(PDOException $e) {
            // Catch any unexpected database errors (like a connection drop mid-process)
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Include our site header (Visuals)
include 'includes/header.php';
?>

<!-- HTML Form Section -->
<section class="container" style="padding: 60px 20px;">
    
    <div class="form-container">
        
        <h2 style="margin-bottom: 24px; text-align: center; font-size: 1.8rem; font-weight: 800;">Create an Account</h2>
        
        <?php if(!empty($error)): ?>
            <div style="background-color: #FEF2F2; color: var(--danger-color); padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 24px; font-size: 0.95rem; border: 1px solid #FECACA;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
            <div style="background-color: #ECFDF5; color: var(--success-color); padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 24px; font-size: 0.95rem; border: 1px solid #A7F3D0;">
                <?php echo $success; ?>
                <br>
                <a href="login.php" style="color: var(--success-color); font-weight: bold; text-decoration: underline; display: block; margin-top: 8px;">Click here to log in</a>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required class="form-control" placeholder="John Doe">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required class="form-control" placeholder="name@example.com">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required class="form-control" placeholder="Create a password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required class="form-control" placeholder="Repeat password">
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 10px; width: 100%;">Create Account</button>
            
        </form>

        <div style="text-align: center; margin-top: 32px; color: var(--text-muted); font-size: 0.95rem;">
            Already have an account? <a href="login.php" style="color: var(--primary-color); font-weight: 600;">Log in</a>
        </div>
        
    </div>
</section>

<?php 
// Include the global footer
include 'includes/footer.php'; 
?>
