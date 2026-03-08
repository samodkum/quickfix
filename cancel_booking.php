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
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['booking_id'] ?? 0);
$error = '';

// Load booking
$booking = null;
try {
    $stmt = $pdo->prepare(
        "SELECT b.*, s.title AS service_name
         FROM bookings b
         JOIN services s ON b.service_id = s.id
         WHERE b.id = ? AND b.user_id = ? LIMIT 1"
    );
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();
} catch (PDOException $e) {
}

if (!$booking) {
    header('Location: status.php');
    exit();
}

$allowed = ['Requested', 'Accepted'];
if (!in_array((string)$booking['status'], $allowed, true)) {
    header('Location: status.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
        $reason = trim($_POST['cancel_reason'] ?? '');
        if ($reason === '') {
            $error = "Please provide a cancellation reason.";
        } else {
            try {
                $pdo->beginTransaction();

                $lock = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE");
                $lock->execute([$booking_id, $user_id]);
                $b = $lock->fetch();
                if (!$b || !in_array((string)$b['status'], $allowed, true)) {
                    $pdo->rollBack();
                    header('Location: status.php');
                    exit();
                }

                $techCount = (int)($b['technician_count'] ?? 1);
                $serviceId = (int)$b['service_id'];
                $date = $b['service_date'] ?? null;
                $time = $b['service_time'] ?? null;

                if (!empty($date) && !empty($time)) {
                    // Restore slot capacity
                    $slotSel = $pdo->prepare("SELECT id FROM booking_slots WHERE service_id = ? AND date = ? AND time = ? FOR UPDATE");
                    $slotSel->execute([$serviceId, $date, $time]);
                    $slotId = $slotSel->fetchColumn();
                    if ($slotId) {
                        $pdo->prepare("UPDATE booking_slots SET available_count = available_count + ? WHERE id = ?")->execute([$techCount, (int)$slotId]);
                    } else {
                        $pdo->prepare("INSERT INTO booking_slots (service_id, date, time, available_count) VALUES (?, ?, ?, ?)")->execute([$serviceId, $date, $time, $techCount]);
                    }
                }

                if (!empty($b['technician_id'])) {
                    try {
                        $pdo->prepare("UPDATE technicians SET status = 'available' WHERE id = ?")->execute([(int)$b['technician_id']]);
                    } catch (PDOException $e) { /* ignore */ }
                }

                $upd = $pdo->prepare("UPDATE bookings SET status = 'Cancelled', cancel_reason = ?, cancelled_at = NOW() WHERE id = ?");
                $upd->execute([$reason, $booking_id]);

                try {
                    $log = $pdo->prepare("INSERT INTO booking_logs (booking_id, old_status, new_status, note, changed_by_user_id) VALUES (?, ?, 'Cancelled', ?, ?)");
                    $log->execute([$booking_id, (string)$b['status'], $reason, $user_id]);
                } catch (PDOException $e) { /* ignore */ }

                try {
                    $bk = !empty($b['booking_unique_id']) ? (string)$b['booking_unique_id'] : ('#' . $booking_id);
                    $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)")
                        ->execute([$user_id, "Booking {$bk} cancelled."]);
                } catch (PDOException $e) { /* ignore */ }

                $pdo->commit();
                header('Location: status.php?msg=cancelled');
                exit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Unable to cancel booking right now. Please try again.";
            }
        }
    }
}

include 'includes/header.php';
?>

<section class="container" style="padding: 60px 20px;">
    <div class="form-container" style="max-width: 620px;">
        <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; text-align: center;">Cancel Booking</h2>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 26px;">
            <?php echo htmlspecialchars($booking['service_name']); ?> • <?php echo htmlspecialchars($booking['booking_unique_id'] ?? ('#' . $booking_id)); ?>
        </p>

        <?php if(!empty($error)): ?>
            <div style="background-color: #FEF2F2; color: var(--danger-color); padding: 14px; border-radius: var(--border-radius-sm); margin-bottom: 18px; border: 1px solid #FECACA;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="booking_id" value="<?php echo (int)$booking_id; ?>">

            <div class="form-group">
                <label for="cancel_reason">Reason</label>
                <textarea id="cancel_reason" name="cancel_reason" class="form-control" rows="3" required placeholder="Tell us why you're cancelling..." style="resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <a href="status.php" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); text-decoration: none;">
                    Back
                </a>
                <button type="submit" class="btn-primary" style="background: var(--danger-color); border-color: var(--danger-color);">
                    Confirm Cancellation
                </button>
            </div>
        </form>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

