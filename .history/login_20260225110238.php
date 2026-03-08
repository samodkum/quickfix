<?php
// login.php handles user authentication
require_once 'config/db.php';

// IMPORTANT: session_start() must be called BEFORE we can use $_SESSION variables
// But it's already safely started in our db.php / header.php ecosystem
// However, if db.php doesn't start it, we ensure it's started here just in case.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If the user is already logged in, redirect them to the homepage instantly
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit(); // Always call exit() after a header redirect to stop script execution
}

// Variable to hold error messages
$error = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get the email and password from the form
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate that inputs are not completely empty
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            // Find the user in the database by their email
            $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            // fetch() grabs a single row (since emails are unique, there should only be one)
            $user = $stmt->fetch();

            // Check two conditions:
            // 1. Did we find a user with this email? ($user will be true/array if found, false if not)
            // 2. Does the entered password match the scrambled hash in the database?
            if ($user && password_verify($password, $user['password'])) {
                
                // --- SUCCESSFUL LOGIN ---
                
                // Store user details in the Session array.
                // Sessions are stored safely on the server, not on the user's computer.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role']; // e.g. 'user' or 'admin'
                
                // Redirect based on cart and role
                
                
            } else {
                // If either email wasn't found OR password was wrong
                // NOTE: Use a generic error message for security! Don't say "Email not found" vs "Wrong password".
                // That prevents hackers from guessing valid email addresses.
                $error = "Invalid email or password.";
            }

        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Load global header
include 'includes/header.php';
?>

<!-- HTML Form Section -->
<section class="container" style="padding: 60px 20px;">
    
    <div class="form-container">
        
        <h2 style="margin-bottom: 24px; text-align: center; font-size: 1.8rem; font-weight: 800;">Welcome Back</h2>
        
        <!-- Display Error Box if $error is not empty -->
        <?php if(!empty($error)): ?>
            <div style="background-color: #FEF2F2; color: var(--danger-color); padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 24px; font-size: 0.95rem; border: 1px solid #FECACA;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="" method="POST">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required class="form-control" placeholder="name@example.com">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required class="form-control" placeholder="Enter your password">
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 10px; width: 100%;">Log in</button>
            
        </form>

        <div style="text-align: center; margin-top: 32px; color: var(--text-muted); font-size: 0.95rem;">
            Don't have an account? <a href="register.php" style="color: var(--primary-color); font-weight: 600;">Sign up</a>
        </div>
        
    </div>
</section>

<?php 
// Load global footer
include 'includes/footer.php'; 
?>
