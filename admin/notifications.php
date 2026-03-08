<?php
// admin/notifications.php - Admin Notification Center
require_once '../config/db.php';
include 'includes/header.php';

// Mark all as read action
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $pdo->query("UPDATE notifications SET is_read = TRUE WHERE is_read = FALSE");
    header("Location: notifications.php");
    exit();
}

// Mark single as read
if (isset($_GET['read_id'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
    $stmt->execute([(int)$_GET['read_id']]);
    header("Location: notifications.php");
    exit();
}

// Fetch limit
$limit = 50;
$notifications = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT $limit")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; font-weight: 800;">Notification Center</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Recent alerts for new bookings and system events.</p>
    </div>
    
    <div>
        <a href="?action=mark_all_read" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); text-decoration: none; padding: 10px 16px;">
            <i class="fa-solid fa-check-double"></i> Mark All as Read
        </a>
    </div>
</div>

<div class="chart-box" style="padding: 0; overflow: hidden;">
    <?php if(empty($notifications)): ?>
        <div style="text-align: center; padding: 60px; color: var(--text-muted);">
            <i class="fa-regular fa-bell-slash" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
            <h3>No notifications yet</h3>
            <p>You're all caught up!</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column;">
            <?php foreach($notifications as $notif): ?>
                <div style="display: flex; gap: 20px; padding: 20px 24px; border-bottom: 1px solid var(--border-color); <?php if(!$notif['is_read']) echo 'background: rgba(59, 130, 246, 0.03); border-left: 4px solid #3B82F6;'; ?>">
                    
                    <!-- Icon -->
                    <div style="flex-shrink: 0; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
                        <?php 
                            $notifType = $notif['type'] ?? 'info';
                            if($notifType === 'new_booking') echo 'background: rgba(16, 185, 129, 0.1); color: #10B981;';
                            elseif($notifType === 'user_signup') echo 'background: rgba(147, 51, 234, 0.1); color: #9333EA;';
                            else echo 'background: rgba(59, 130, 246, 0.1); color: #3B82F6;';
                        ?>">
                        <?php if(($notif['type'] ?? '') === 'new_booking'): ?>
                            <i class="fa-solid fa-calendar-plus"></i>
                        <?php elseif(($notif['type'] ?? '') === 'user_signup'): ?>
                            <i class="fa-solid fa-user-plus"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-exclamation"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Content -->
                    <div style="flex-grow: 1;">
                        <h4 style="margin: 0 0 6px 0; font-size: 1.05rem; font-weight: 600; <?php if(!$notif['is_read']) echo 'color: var(--text-main); font-weight: 700;'; else echo 'color: var(--text-muted);'; ?>">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </h4>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                <i class="fa-regular fa-clock" style="margin-right: 4px;"></i> 
                                <?php 
                                    // Make date readable
                                    $time_diff = time() - strtotime($notif['created_at']);
                                    if ($time_diff < 3600) echo floor($time_diff / 60) . ' mins ago';
                                    elseif ($time_diff < 86400) echo floor($time_diff / 3600) . ' hours ago';
                                    else echo date('M d, g:i A', strtotime($notif['created_at']));
                                ?>
                            </span>
                            
                            <!-- Action Link -->
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <?php if(($notif['type'] ?? '') === 'new_booking' && !empty($notif['related_id'])): ?>
                                    <a href="bookings.php?search=<?php echo htmlspecialchars($notif['related_id']); ?>" style="font-size: 0.85rem; color: var(--accent-color); font-weight: 600; text-decoration: none;">View Booking &rarr;</a>
                                <?php endif; ?>
                                
                                <?php if(!$notif['is_read']): ?>
                                    <span style="color: var(--border-color);">&bull;</span>
                                    <a href="?read_id=<?php echo $notif['id']; ?>" style="font-size: 0.8rem; color: var(--text-muted); text-decoration: none;">Mark as read</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
