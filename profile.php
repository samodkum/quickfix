<?php
// profile.php displays the user's account details
require_once 'config/db.php';

// Check session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Removed admin check to allow testing

// Variables setup
$user_id = $_SESSION['user_id'];
$user_data = [];
$booking_count = 0;

try {
    // 1. Fetch user profile data from the users table based on their session id
    $user_stmt = $pdo->prepare("SELECT name, email, role, created_at FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch();
    
    // 2. We use another query to just COUNT how many total bookings this person has made over their lifetime
    // This is computationally much faster than dragging out all rows just to count them.
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    // fetchColumn() grabs just the first column of the first row (the number itself)
    $booking_count = $count_stmt->fetchColumn();

} catch(PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

// Load global UI header
include 'includes/header.php';
?>

<!-- HTML Details Section -->
<section class="container" style="padding: 60px 20px;">
    
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px; font-weight: 800;">My Profile</h1>
    </div>

    <div style="display: grid; grid-template-columns: 1fr; gap: 32px; max-width: 800px; margin: 0 auto;">
        
        <?php if($user_data): ?>
            
            <div class="service-card" style="text-align: left; padding: 40px; border-radius: var(--border-radius-md);">
                
                <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 24px; font-size: 1.4rem; font-weight: 700;">Account Details</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 32px; margin-bottom: 40px;">
                    
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Full Name</strong>
                        <div style="font-size: 1.1rem; font-weight: 500;"><?php echo htmlspecialchars($user_data['name']); ?></div>
                    </div>
                    
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Email Address</strong>
                        <div style="font-size: 1.1rem; font-weight: 500;"><?php echo htmlspecialchars($user_data['email']); ?></div>
                    </div>
                    
                    <div>
                        <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Member Since</strong>
                        <div style="font-size: 1.1rem; font-weight: 500;">
                            <?php echo date('F j, Y', strtotime($user_data['created_at'])); ?>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Lifetime Stats Section inside the card -->
                <div style="background-color: #FAFAFA; padding: 24px; border-radius: var(--border-radius-sm); display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--border-color);">
                    
                    <div>
                        <h4 style="margin-bottom: 4px; font-size: 1.1rem;">Lifetime Bookings</h4>
                        <p style="color: var(--text-muted); font-size: 0.95rem;">Total times you've requested our services.</p>
                    </div>
                    
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary-color);">
                        <?php echo $booking_count; ?>
                    </div>
                    
                </div>
                
                <!-- Helper Action Buttons at the bottom of the card -->
                <div style="margin-top: 40px; display: flex; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                    <a href="status.php" class="btn-primary" style="padding: 12px 24px;">View Booking History</a>
                    <a href="logout.php" class="btn-outline" style="color: var(--danger-color); border-color: #FECACA; padding: 12px 24px;">Log out</a>
                </div>
                
            </div>
            
        <?php else: ?>
            <div style="text-align: center; color: var(--danger-color); padding: 40px; background: #FEF2F2; border-radius: var(--border-radius-sm); border: 1px solid #FECACA;">Could not fetch user profile details.</div>
        <?php endif; ?>
        
    </div>
</section>

<?php include 'includes/footer.php'; ?>
