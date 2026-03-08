<?php
// thank_you.php displays a success message after booking
require_once 'config/db.php';
include 'includes/header.php';

$bk = isset($_GET['bk']) ? trim($_GET['bk']) : '';
?>

<section class="container" style="padding: 100px 20px; text-align: center; max-width: 600px;">
    
    <div style="background: var(--card-bg); border-radius: var(--border-radius-md); padding: 60px 40px; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
        
        <div style="width: 80px; height: 80px; background: #ECFDF5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
            <i class="fa-solid fa-check" style="font-size: 2.5rem; color: var(--success-color);"></i>
        </div>
        
        <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 16px;">Booking Confirmed!</h1>
        
        <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 32px; line-height: 1.6;">
            Thank you for choosing QuickFix. Your emergency service request has been successfully placed. Our professionals will contact you shortly.
        </p>

        <?php if($bk !== ''): ?>
            <div style="display: inline-block; padding: 10px 18px; border-radius: 50px; border: 1px solid var(--border-color); background: #FAFAFA; font-weight: 800; margin-bottom: 22px;">
                Booking ID: <?php echo htmlspecialchars($bk); ?>
            </div>
        <?php endif; ?>
        
        <div style="background: #FAFAFA; padding: 20px; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); margin-bottom: 32px;">
            <p style="margin: 0; font-weight: 500;">You can track the live status of your professionals from the bookings page.</p>
        </div>
        
        <div style="display: flex; gap: 16px; justify-content: center;">
            <a href="status.php" class="btn-primary" style="padding: 14px 32px;">Track Bookings</a>
            <a href="index.php" class="btn-outline" style="padding: 14px 32px;">Back to Home</a>
        </div>
        
    </div>
    
</section>

<?php include 'includes/footer.php'; ?>
