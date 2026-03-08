<?php 
// contact.php 
if (session_status() === PHP_SESSION_NONE) session_start();
include 'includes/header.php'; 
?>

<section class="container" style="padding: 60px 20px; max-width: 800px;">
    
    <div style="text-align: center; margin-bottom: 48px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px; font-weight: 800;">Contact Support</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem;">Have a question about our services or your account? Let us know.</p>
    </div>

    <div class="form-container">
        <form method="POST" onsubmit="event.preventDefault(); alert('Thank you for contacting us! We will simulate a reply shortly.');">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px;">
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" required class="form-control" placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" required class="form-control" placeholder="name@example.com">
                </div>
            </div>

            <div class="form-group">
                <label>Subject</label>
                <input type="text" required class="form-control" placeholder="How can we help?">
            </div>

            <div class="form-group">
                <label>Message</label>
                <textarea required rows="6" class="form-control" placeholder="Please describe your issue or question in detail..." style="resize: vertical;"></textarea>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 16px; width: 100%; padding: 16px; font-size: 1.05rem;">Send Message</button>
        </form>
    </div>
    
</section>

<?php include 'includes/footer.php'; ?>
