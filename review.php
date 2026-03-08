<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?msg=login_required');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (int)($_POST['booking_id'] ?? 0);
$error = '';
$success = '';

// Fetch booking
$booking = null;
try {
    $stmt = $pdo->prepare("
        SELECT b.*, s.title AS service_name, t.name AS technician_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        LEFT JOIN technicians t ON b.technician_id = t.id
        WHERE b.id = ? AND b.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();
} catch (PDOException $e) {
}

if (!$booking || (string)$booking['status'] !== 'Completed' || empty($booking['technician_id'])) {
    header('Location: status.php');
    exit();
}

// Check existing review
$existing = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE booking_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$booking_id, $user_id]);
    $existing = $stmt->fetchColumn();
} catch (PDOException $e) {
}

if ($existing) {
    header('Location: status.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $review_text = trim($_POST['review_text'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $error = "Please choose a rating between 1 and 5.";
        } else {
            try {
                $pdo->beginTransaction();

                $ins = $pdo->prepare("INSERT INTO reviews (booking_id, user_id, technician_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$booking_id, $user_id, (int)$booking['technician_id'], $rating, $review_text]);

                // Update technician aggregates
                $techId = (int)$booking['technician_id'];
                $avg = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE technician_id = ?");
                $avg->execute([$techId]);
                $avgVal = (float)$avg->fetchColumn();

                $cnt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE technician_id = ?");
                $cnt->execute([$techId]);
                $cntVal = (int)$cnt->fetchColumn();

                $upd = $pdo->prepare("UPDATE technicians SET rating = ?, total_reviews = ? WHERE id = ?");
                $upd->execute([$avgVal, $cntVal, $techId]);

                // If low rating, create an admin-visible notification (user_id left NULL)
                if ($rating <= 2) {
                    try {
                        $bk = !empty($booking['booking_unique_id']) ? (string)$booking['booking_unique_id'] : ('#' . $booking_id);
                        $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (NULL, ?, 0)")
                            ->execute(["Low rating ({$rating}/5) received for booking {$bk}. Please follow up with customer."]);
                    } catch (PDOException $e) { /* ignore */ }
                }

                $pdo->commit();
                $success = ($rating <= 2)
                    ? "Sorry for inconvenience. Admin will contact you."
                    : "Thanks for your feedback!";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Unable to submit review right now. Please try again.";
            }
        }
    }
}

include 'includes/header.php';
?>

<section class="container" style="padding: 60px 20px;">
    <div class="form-container" style="max-width: 700px;">
        <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; text-align: center;">Rate Your Service</h2>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 26px;">
            <?php echo htmlspecialchars($booking['service_name']); ?> • Technician: <?php echo htmlspecialchars($booking['technician_name'] ?? '—'); ?>
        </p>

        <?php if(!empty($success)): ?>
            <div style="background-color: #ECFDF5; color: var(--success-color); padding: 14px; border-radius: var(--border-radius-sm); margin-bottom: 18px; border: 1px solid #A7F3D0;">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div style="text-align: center;">
                <a href="status.php" class="btn-primary" style="text-decoration: none;">Back to bookings</a>
            </div>
        <?php else: ?>
            <?php if(!empty($error)): ?>
                <div style="background-color: #FEF2F2; color: var(--danger-color); padding: 14px; border-radius: var(--border-radius-sm); margin-bottom: 18px; border: 1px solid #FECACA;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="booking_id" value="<?php echo (int)$booking_id; ?>">

                <div class="form-group">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating" class="form-control" required>
                        <option value="">Select</option>
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Good</option>
                        <option value="3">3 - Okay</option>
                        <option value="2">2 - Poor</option>
                        <option value="1">1 - Very bad</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="review_text">Review (optional)</label>
                    <textarea id="review_text" name="review_text" class="form-control" rows="4" placeholder="Share details about your experience..." style="resize: vertical;"></textarea>
                </div>

                <div style="display: flex; gap: 12px; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <a href="status.php" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); text-decoration: none;">
                        Back
                    </a>
                    <button type="submit" class="btn-primary">Submit Review</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

