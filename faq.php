<?php 
// faq.php 
if (session_status() === PHP_SESSION_NONE) session_start();
include 'includes/header.php'; 
?>

<section class="container" style="padding: 60px 20px; max-width: 800px;">
    
    <div style="text-align: center; margin-bottom: 48px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px; font-weight: 800;">Frequently Asked Questions</h1>
    </div>

    <div style="display: flex; flex-direction: column; gap: 24px;">
        
        <div class="service-card" style="text-align: left; padding: 32px; border-radius: var(--border-radius-md);">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="color: var(--accent-color); font-size: 1.5rem; margin-top: 2px;">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div>
                    <h3 style="margin-bottom: 12px; color: var(--text-main); font-size: 1.3rem; font-weight: 700;">How fast can someone get to my house?</h3>
                    <p style="color: var(--text-muted); line-height: 1.7; font-size: 1.05rem;">If you select "High - Emergency" priority, our system immediately pings the nearest available technician. Average arrival time for high priority is under 45 minutes.</p>
                </div>
            </div>
        </div>

        <div class="service-card" style="text-align: left; padding: 32px; border-radius: var(--border-radius-md);">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="color: var(--accent-color); font-size: 1.5rem; margin-top: 2px;">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div>
                    <h3 style="margin-bottom: 12px; color: var(--text-main); font-size: 1.3rem; font-weight: 700;">Are the prices fixed?</h3>
                    <p style="color: var(--text-muted); line-height: 1.7; font-size: 1.05rem;">The prices shown are the "Base Starting Price". Depending on the severity of the problem (e.g., replacing 1 outlet vs completely rewiring a room), the technician will give you a final quote before beginning work.</p>
                </div>
            </div>
        </div>

        <div class="service-card" style="text-align: left; padding: 32px; border-radius: var(--border-radius-md);">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="color: var(--accent-color); font-size: 1.5rem; margin-top: 2px;">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div>
                    <h3 style="margin-bottom: 12px; color: var(--text-main); font-size: 1.3rem; font-weight: 700;">Do I pay online or in person?</h3>
                    <p style="color: var(--text-muted); line-height: 1.7; font-size: 1.05rem;">Currently, all payments are handled securely in-person via card reader or cash after the service is marked as "Completed" to your satisfaction.</p>
                </div>
            </div>
        </div>
        
    </div>
    
</section>

<?php include 'includes/footer.php'; ?>
