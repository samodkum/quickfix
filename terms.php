<?php 
// terms.php 
if (session_status() === PHP_SESSION_NONE) session_start();
include 'includes/header.php'; 
?>

<section class="container" style="padding: 60px 20px; max-width: 800px; text-align: left;">
    <h1 style="font-size: 2.5rem; margin-bottom: 40px; font-weight: 800; text-align: center;">Terms & Privacy Policy</h1>
    
    <div style="background: var(--card-bg); padding: 48px; border-radius: var(--border-radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); color: var(--text-muted); line-height: 1.8;">
        
        <h3 style="color: var(--text-main); margin-bottom: 12px; font-size: 1.3rem; font-weight: 700;">1. Terms of Use</h3>
        <p style="margin-bottom: 32px; font-size: 1.05rem;">By using QuickFix, you agree to our terms. We act primarily as an intermediary between independent service providers and customers. We are not liable for direct damages caused by independent contractors, though we do hold strict insurance standards.</p>
        
        <h3 style="color: var(--text-main); margin-bottom: 12px; font-size: 1.3rem; font-weight: 700;">2. Privacy Data</h3>
        <p style="margin-bottom: 32px; font-size: 1.05rem;">We respect your privacy. All passwords are encrypted securely. We never sell your personal contact information to third parties. Your address is only shared with the specific technician assigned to your booking.</p>
        
        <h3 style="color: var(--text-main); margin-bottom: 12px; font-size: 1.3rem; font-weight: 700;">3. Cancellation Policy</h3>
        <p style="font-size: 1.05rem;">Bookings can be cancelled without penalty within 10 minutes of request. Repeated false emergency requests will result in an account ban according to fair use policies.</p>
        
    </div>
</section>

<?php include 'includes/footer.php'; ?>
