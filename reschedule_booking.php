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
        $new_date = trim($_POST['service_date'] ?? '');
        $new_time = trim($_POST['service_time'] ?? '');
        if ($new_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
            $error = "Please select a valid date.";
        } elseif ($new_time === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $new_time)) {
            $error = "Please select a valid time slot.";
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

                $serviceId = (int)$b['service_id'];
                $techCount = (int)($b['technician_count'] ?? 1);
                $old_date = $b['service_date'] ?? null;
                $old_time = $b['service_time'] ?? null;

                // Lock new slot row
                $newSlotStmt = $pdo->prepare("SELECT id, available_count FROM booking_slots WHERE service_id = ? AND date = ? AND time = ? FOR UPDATE");
                $newSlotStmt->execute([$serviceId, $new_date, $new_time]);
                $newSlot = $newSlotStmt->fetch();
                if (!$newSlot || (int)$newSlot['available_count'] < $techCount) {
                    $pdo->rollBack();
                    $error = "No technicians available at selected time. Please choose another slot.";
                } else {
                    // Restore old slot capacity (if old slot exists)
                    if (!empty($old_date) && !empty($old_time)) {
                        $oldSlotStmt = $pdo->prepare("SELECT id FROM booking_slots WHERE service_id = ? AND date = ? AND time = ? FOR UPDATE");
                        $oldSlotStmt->execute([$serviceId, $old_date, $old_time]);
                        $oldSlotId = $oldSlotStmt->fetchColumn();
                        if ($oldSlotId) {
                            $pdo->prepare("UPDATE booking_slots SET available_count = available_count + ? WHERE id = ?")->execute([$techCount, (int)$oldSlotId]);
                        } else {
                            $pdo->prepare("INSERT INTO booking_slots (service_id, date, time, available_count) VALUES (?, ?, ?, ?)")->execute([$serviceId, $old_date, $old_time, $techCount]);
                        }
                    }

                    // Decrement new slot
                    $pdo->prepare("UPDATE booking_slots SET available_count = available_count - ? WHERE id = ?")->execute([$techCount, (int)$newSlot['id']]);

                    $preferred_time = $new_date . ' ' . $new_time;
                    $upd = $pdo->prepare("UPDATE bookings SET service_date = ?, service_time = ?, preferred_time = ? WHERE id = ?");
                    $upd->execute([$new_date, $new_time, $preferred_time, $booking_id]);

                    try {
                        $note = "Rescheduled from " . ($old_date ? ($old_date . ' ' . $old_time) : 'N/A') . " to {$new_date} {$new_time}";
                        $pdo->prepare("INSERT INTO booking_logs (booking_id, old_status, new_status, note, changed_by_user_id) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$booking_id, (string)$b['status'], (string)$b['status'], $note, $user_id]);
                    } catch (PDOException $e) { /* ignore */ }

                    try {
                        $bk = !empty($b['booking_unique_id']) ? (string)$b['booking_unique_id'] : ('#' . $booking_id);
                        $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)")
                            ->execute([$user_id, "Booking {$bk} rescheduled to {$new_date} {$new_time}."]);
                    } catch (PDOException $e) { /* ignore */ }

                    $pdo->commit();
                    header('Location: status.php?msg=rescheduled');
                    exit();
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Unable to reschedule right now. Please try again.";
            }
        }
    }
}

include 'includes/header.php';
?>

<section class="container" style="padding: 60px 20px;">
    <div class="form-container" style="max-width: 720px;">
        <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; text-align: center;">Reschedule Booking</h2>
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

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">
                <div class="form-group">
                    <label for="service_date">New Date</label>
                    <input type="date" id="service_date" name="service_date" required class="form-control" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($booking['service_date'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="service_time">New Time Slot</label>
                    <select id="service_time" name="service_time" required class="form-control">
                        <option value="">Select a slot</option>
                        <?php
                            $slots = [
                                '09:00:00' => '09:00 AM', '10:00:00' => '10:00 AM', '11:00:00' => '11:00 AM',
                                '12:00:00' => '12:00 PM', '13:00:00' => '01:00 PM', '14:00:00' => '02:00 PM',
                                '15:00:00' => '03:00 PM', '16:00:00' => '04:00 PM', '17:00:00' => '05:00 PM',
                                '18:00:00' => '06:00 PM'
                            ];
                            $currentTime = $booking['service_time'] ?? '';
                            foreach ($slots as $val => $label) {
                                $sel = ($currentTime === $val) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($val) . "\" {$sel}>" . htmlspecialchars($label) . "</option>";
                            }
                        ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-top: 6px;">
                <a href="status.php" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); text-decoration: none;">
                    Back
                </a>
                <button type="submit" class="btn-primary">
                    Confirm Reschedule
                </button>
            </div>
        </form>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

