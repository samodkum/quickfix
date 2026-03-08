<?php
// status.php lets users view all their past and current emergency requests
require_once 'config/db.php';

// Session tracking to ensure they are logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Kick out guests attempting to view private bookings
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Admin block removed for testing

// Variables setup
$user_id = $_SESSION['user_id'];
$bookings = [];
$reviewed_booking_ids = [];

try {
    // 1. COMPLEX QUERY (JOIN)
    // We need data from TWO tables simultaneously: the bookings table (dates, status, address)
    // AND the services table (what they actually booked, title, price).
    // Using a JOIN connects "bookings.service_id" to "services.id"
    $query = "SELECT bookings.*, services.title AS service_name, services.category 
              FROM bookings 
              JOIN services ON bookings.service_id = services.id 
              WHERE bookings.user_id = ? 
              ORDER BY bookings.created_at DESC"; // DESC shows the newest bookings at the top
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    
    // Grab all rows belonging to this user
    $bookings = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

// Fetch already-reviewed bookings (so we can show "Rate" CTA only once)
try {
    $r = $pdo->prepare("SELECT booking_id FROM reviews WHERE user_id = ?");
    $r->execute([(int)$user_id]);
    $reviewed_booking_ids = array_map('intval', array_column($r->fetchAll(), 'booking_id'));
} catch(PDOException $e) {
    $reviewed_booking_ids = [];
}

// Load global UI header
include 'includes/header.php';
?>

<!-- HTML Section -->
<section class="container" style="padding: 60px 20px;">
    
    <div style="text-align: center; margin-bottom: 50px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 15px;">My Bookings</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem;">Track the status of your service requests below.</p>
    </div>

    <!-- Optional Success message from booking.php redirect (Deprecated, now using thank_you.php but kept for legacy fallback) -->
    <?php if(isset($_GET['booking']) && $_GET['booking'] === 'success'): ?>
        <div style="background-color: #ECFDF5; color: var(--success-color); padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 32px; text-align: center; max-width: 800px; margin-left: auto; margin-right: auto; border: 1px solid #A7F3D0;">
            <strong>Success!</strong> Your booking request has been received. Our professionals are reviewing it now.
        </div>
    <?php endif; ?>

    <!-- Booking Cards Container -->
    <div style="display: flex; flex-direction: column; gap: 24px; max-width: 800px; margin: 0 auto;">
        
        <?php if(empty($bookings)): ?>
            <div class="service-card" style="text-align: center; padding: 60px 20px; border-radius: var(--border-radius-md);">
                <i class="fa-solid fa-calendar-xmark" style="font-size: 3rem; color: #E5E5E5; margin-bottom: 16px;"></i>
                <h3 style="color: var(--text-muted); margin-bottom: 24px;">You haven't made any bookings yet.</h3>
                <a href="services.php" class="btn-primary">Browse Services</a>
            </div>
            
        <?php else: ?>
            
            <?php foreach($bookings as $booking): ?>
                
                <div class="service-card" style="display: flex; flex-direction: column; text-align: left; padding: 32px; border-radius: var(--border-radius-md);">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
                        
                        <div>
                            <h3 style="font-size: 1.4rem; margin-bottom: 8px; font-weight: 800;"><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                            
                            <?php if(!empty($booking['booking_unique_id'])): ?>
                                <div style="display: inline-block; font-size: 0.85rem; font-weight: 800; padding: 4px 10px; border-radius: 999px; background: #FAFAFA; border: 1px solid var(--border-color); color: var(--text-main); margin-bottom: 8px;">
                                    <?php echo htmlspecialchars($booking['booking_unique_id']); ?>
                                </div>
                            <?php endif; ?>

                            <span style="font-size: 0.9rem; color: var(--text-muted);">
                                Requested: <?php echo date('M d, Y \a\t h:i A', strtotime($booking['created_at'])); ?>
                            </span>
                        </div>
                        
                        <div>
                            <?php 
                                $badge_style = "padding: 6px 16px; border-radius: 50px; font-weight: 600; font-size: 0.85rem; display: inline-block;";
                                
                                switch($booking['status']) {
                                    case 'Requested':
                                        $badge_style .= " background-color: #F1F5F9; color: var(--text-muted); border: 1px solid #E2E8F0;";
                                        break;
                                    case 'Accepted':
                                        $badge_style .= " background-color: #F8FAFC; color: var(--primary-color); border: 1px solid var(--primary-color);";
                                        break;
                                    case 'Technician Assigned':
                                        $badge_style .= " background-color: rgba(147, 51, 234, 0.08); color: #9333EA; border: 1px solid rgba(147, 51, 234, 0.35);";
                                        break;
                                    case 'In Progress':
                                        $badge_style .= " background-color: #FFFBEB; color: #D97706; border: 1px solid #FDE68A;";
                                        break;
                                    case 'Completed':
                                        $badge_style .= " background-color: #ECFDF5; color: var(--success-color); border: 1px solid #A7F3D0;";
                                        break;
                                    case 'Cancelled':
                                        $badge_style .= " background-color: rgba(239, 68, 68, 0.08); color: var(--danger-color); border: 1px solid rgba(239, 68, 68, 0.35);";
                                        break;
                                }
                            ?>
                            
                            <span style="<?php echo $badge_style; ?>">
                                <?php if($booking['status']==='Completed'): ?>
                                    <i class="fa-solid fa-check-circle" style="margin-right: 6px;"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-clock" style="margin-right: 6px;"></i>
                                <?php endif; ?>
                                
                                <?php echo htmlspecialchars($booking['status']); ?>
                            </span>
                            
                        </div>
                    </div>

                    <?php
                        $steps = ['Requested', 'Accepted', 'Technician Assigned', 'In Progress', 'Completed'];
                        $status = (string)$booking['status'];
                        $stepIndex = array_search($status, $steps, true);
                        if ($status === 'Cancelled') {
                            $stepIndex = 0;
                        }
                        if ($stepIndex === false) {
                            $stepIndex = 0;
                        }
                        $percent = (count($steps) > 1) ? (int)round(($stepIndex / (count($steps) - 1)) * 100) : 0;
                    ?>

                    <div style="margin: 6px 0 22px 0;">
                        <div style="height: 10px; background: #F1F5F9; border-radius: 999px; overflow: hidden; border: 1px solid var(--border-color);">
                            <div style="height: 100%; width: <?php echo (int)$percent; ?>%; background: <?php echo $status === 'Cancelled' ? 'var(--danger-color)' : 'var(--primary-color)'; ?>;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.8rem; color: var(--text-muted); gap: 10px; flex-wrap: wrap;">
                            <?php foreach($steps as $s): ?>
                                <span style="<?php echo ($s === $status) ? 'font-weight:700;color:var(--text-main);' : ''; ?>">
                                    <?php echo htmlspecialchars($s); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php if($status === 'In Progress'): ?>
                            <div style="margin-top: 12px; background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; padding: 12px 14px; border-radius: 10px; font-weight: 600;">
                                Technician will arrive in 30 minutes.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; font-size: 0.95rem;">
                        
                        <div>
                            <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">Priority Level</strong>
                            <?php 
                                $pri_color = ($booking['emergency_level'] === 'High') ? 'var(--danger-color)' : 'var(--text-main)';
                            ?>
                            <span style="color: <?php echo $pri_color; ?>; font-weight: 600;">
                                <?php echo htmlspecialchars($booking['emergency_level']); ?>
                            </span>
                        </div>
                        
                        <div>
                            <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">Preferred Time</strong>
                            <?php
                                $when = '';
                                if (!empty($booking['service_date']) && !empty($booking['service_time'])) {
                                    $when = $booking['service_date'] . ' ' . $booking['service_time'];
                                } else {
                                    $when = (string)$booking['preferred_time'];
                                }
                            ?>
                            <span style="color: var(--text-main); font-weight: 500;"><?php echo htmlspecialchars($when); ?></span>
                        </div>
                        
                        <div>
                            <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">Contact Number</strong>
                            <span style="color: var(--text-main); font-weight: 500;"><?php echo htmlspecialchars($booking['contact'] ?? $booking['contact_number']); ?></span>
                        </div>
                        
                        <div style="grid-column: 1 / -1;">
                            <strong style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">Service Address</strong>
                            <?php
                                $addr = $booking['full_address'] ?? '';
                                if (empty($addr)) $addr = $booking['address'] ?? '';
                            ?>
                            <span style="color: var(--text-main); font-weight: 500;"><?php echo htmlspecialchars($addr); ?></span>
                        </div>
                        
                        <div style="grid-column: 1 / -1; background-color: #FAFAFA; padding: 20px; border-radius: var(--border-radius-sm); margin-top: 8px; border: 1px solid var(--border-color);">
                            <strong style="display: block; color: var(--text-main); font-size: 0.95rem; margin-bottom: 8px; font-weight: 700;">Problem Description</strong>
                            <span style="color: var(--text-muted); line-height: 1.6;"><?php echo nl2br(htmlspecialchars($booking['problem_description'])); ?></span>
                        </div>

                        <?php if(in_array($booking['status'], ['Requested', 'Accepted'], true)): ?>
                            <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                                <a href="reschedule_booking.php?id=<?php echo (int)$booking['id']; ?>" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); text-decoration: none;">
                                    Reschedule
                                </a>
                                <a href="cancel_booking.php?id=<?php echo (int)$booking['id']; ?>" class="btn-primary" style="background: var(--danger-color); border-color: var(--danger-color); text-decoration: none;">
                                    Cancel Booking
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if(($booking['status'] === 'Completed') && !empty($booking['technician_id'])): ?>
                            <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                                <?php if(!in_array((int)$booking['id'], $reviewed_booking_ids, true)): ?>
                                    <a href="review.php?booking_id=<?php echo (int)$booking['id']; ?>" class="btn-primary" style="text-decoration: none;">
                                        Rate & Review
                                    </a>
                                <?php else: ?>
                                    <span style="padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-muted); font-weight: 600; background: #FAFAFA;">
                                        Feedback submitted
                                    </span>
                                <?php endif; ?>
                                <a href="technician_profile.php?id=<?php echo (int)$booking['technician_id']; ?>" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); text-decoration: none;">
                                    View Technician
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
                
            <?php endforeach; ?>
            
        <?php endif; ?>
        
    </div>
</section>

<?php 
// Load global footer
include 'includes/footer.php'; 
?>
