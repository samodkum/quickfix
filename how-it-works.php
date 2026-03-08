<?php 
// how-it-works.php 
if (session_status() === PHP_SESSION_NONE) session_start();
include 'includes/header.php'; 
?>

<section class="container" style="padding: 60px 20px;">
    
    <div style="text-align: center; margin-bottom: 60px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px; font-weight: 800;">How QuickFix Works</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem;">Three simple steps to getting your home back in working order.</p>
    </div>

    <div class="services-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 32px;">
        
        <div class="service-card" style="padding: 40px 32px; text-align: center; border-radius: var(--border-radius-lg);">
            <div style="background: #F8FAFC; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: var(--accent-color); font-size: 2rem;">
                <i class="fa-solid fa-hand-pointer"></i>
            </div>
            <h3 style="font-size: 1.4rem; margin-bottom: 12px; font-weight: 700;">1. Request Service</h3>
            <p style="color: var(--text-muted); line-height: 1.6;">Choose your needed service, tell us the problem, and set your priority level.</p>
        </div>
        
        <div class="service-card" style="padding: 40px 32px; text-align: center; border-radius: var(--border-radius-lg);">
            <div style="background: #F8FAFC; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: var(--accent-color); font-size: 2rem;">
                <i class="fa-solid fa-truck-fast"></i>
            </div>
            <h3 style="font-size: 1.4rem; margin-bottom: 12px; font-weight: 700;">2. Expert Dispatched</h3>
            <p style="color: var(--text-muted); line-height: 1.6;">We match you with the nearest fully-licensed professional who accepts the job.</p>
        </div>
        
        <div class="service-card" style="padding: 40px 32px; text-align: center; border-radius: var(--border-radius-lg);">
            <div style="background: #F8FAFC; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: var(--accent-color); font-size: 2rem;">
                <i class="fa-solid fa-house-circle-check"></i>
            </div>
            <h3 style="font-size: 1.4rem; margin-bottom: 12px; font-weight: 700;">3. Problem Solved</h3>
            <p style="color: var(--text-muted); line-height: 1.6;">Our expert arrives, gives you a quote, completes the work, and ensures it's safe.</p>
        </div>
        
    </div>
    
    <div style="text-align: center; margin-top: 56px;">
        <a href="services.php" class="btn-primary" style="padding: 16px 48px; font-size: 1.1rem;">Book a Service Now</a>
    </div>
    
</section>

<?php include 'includes/footer.php'; ?>
