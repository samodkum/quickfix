<?php 
// about.php - Professional Information page
if (session_status() === PHP_SESSION_NONE) session_start();
include 'includes/header.php'; 
?>

<!-- Reusing standard layout but pushing text closer together for readability -->
<section class="container" style="padding: 60px 20px; max-width: 800px; text-align: center;">
    <h1 style="font-size: 2.5rem; margin-bottom: 20px; font-weight: 800;">About QuickFix</h1>
    
    <div style="text-align: left; background: var(--card-bg); padding: 48px; border-radius: var(--border-radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); font-size: 1.1rem; color: var(--text-main); line-height: 1.8;">
        
        <p style="margin-bottom: 24px;">Founded in 2026, <strong>QuickFix Emergency Services</strong> was built with a single mission in mind: To solve your home emergencies faster, cheaper, and safer than traditional call-out businesses.</p>
        
        <p style="margin-bottom: 32px; color: var(--text-muted);">We realized that when a pipe bursts at 2 AM, or when the power goes out, the last thing you want to do is endlessly scroll through google reviews, trying to find someone reliable. Our platform acts as a guaranteed bridge between you and highly vetted, licensed professionals in your area.</p>
        
        <h3 style="margin-top: 40px; margin-bottom: 24px; color: var(--primary-color); font-size: 1.5rem; font-weight: 700; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">Why Choose Us?</h3>
        
        <div style="display: grid; gap: 24px;">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="background: #F8FAFC; padding: 12px; border-radius: var(--border-radius-md); color: var(--accent-color);">
                    <i class="fa-solid fa-shield-halved" style="font-size: 1.25rem;"></i>
                </div>
                <div>
                    <h4 style="margin-bottom: 4px; font-size: 1.1rem; font-weight: 700;">Vetted Professionals</h4>
                    <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6;">Every technician on our platform undergoes a rigorous background check and licensing verification.</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="background: #FFFBEB; padding: 12px; border-radius: var(--border-radius-md); color: #D97706;">
                    <i class="fa-solid fa-tag" style="font-size: 1.25rem;"></i>
                </div>
                <div>
                    <h4 style="margin-bottom: 4px; font-size: 1.1rem; font-weight: 700;">Transparent Pricing</h4>
                    <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6;">No hidden fees. You see the estimated cost before you book and finalize before work starts.</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="background: #FEF2F2; padding: 12px; border-radius: var(--border-radius-md); color: var(--danger-color);">
                    <i class="fa-solid fa-bolt" style="font-size: 1.25rem;"></i>
                </div>
                <div>
                    <h4 style="margin-bottom: 4px; font-size: 1.1rem; font-weight: 700;">Rapid Response</h4>
                    <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6;">We categorize our bookings by emergency level to ensure urgent problems get immediate attention.</p>
                </div>
            </div>
        </div>
        
    </div>
</section>

<?php include 'includes/footer.php'; ?>
