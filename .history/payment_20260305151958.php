<?php
// payment.php collects payment method, applies coupon, and confirms booking.
require_once 'config/db.php';
require_once 'includes/csrf.php';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['pending_booking'])) {
    header('Location: services.php');
    exit();
}

if (empty($_SESSION['otp_verified'])) {
    header('Location: otp.php');
    exit();
}

$error = '';
$success = '';

$pending = $_SESSION['pending_booking'];
$service_id = (int)($pending['service_id'] ?? 0);
$technician_count = (int)($pending['technician_count'] ?? 1);
$service_date = (string)($pending['service_date'] ?? '');
$service_time = (string)($pending['service_time'] ?? '');

// Fetch service details
$service = null;
try {
    $stmt = $pdo->prepare("SELECT id, title, price FROM services WHERE id = ? LIMIT 1");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
} catch (PDOException $e) {
}

if (!$service) {
    header('Location: services.php');
    exit();
}

function couponDiscount(PDO $pdo, string $code, float $subtotal): array {
    $code = trim($code);
    if ($code === '') {
        return ['code' => '', 'discount' => 0.0, 'label' => ''];
    }
    $stmt = $pdo->prepare("SELECT code, discount_type, discount_value, expiry_date, is_active FROM coupons WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    $c = $stmt->fetch();
    if (!$c) {
        return ['code' => '', 'discount' => 0.0, 'label' => 'Invalid coupon'];
    }
    if (empty($c['is_active']) || strtotime((string)$c['expiry_date']) < strtotime(date('Y-m-d'))) {
        return ['code' => '', 'discount' => 0.0, 'label' => 'Coupon expired'];
    }
    $type = (string)$c['discount_type'];
    $value = (float)$c['discount_value'];
    $discount = 0.0;
    if ($type === 'percentage') {
        $discount = $subtotal * ($value / 100.0);
    } elseif ($type === 'fixed') {
        $discount = $value;
    }
    if ($discount < 0) $discount = 0.0;
    if ($discount > $subtotal) $discount = $subtotal;
    return ['code' => (string)$c['code'], 'discount' => $discount, 'label' => 'Applied'];
}

$unit_price = (float)$service['price'];
$subtotal = $unit_price * max(1, $technician_count);
$applied_code = (string)($_SESSION['applied_coupon']['code'] ?? '');
$coupon = ['code' => $applied_code, 'discount' => 0.0, 'label' => ''];
if ($applied_code !== '') {
    try {
        $coupon = couponDiscount($pdo, $applied_code, $subtotal);
        if ($coupon['code'] === '') {
            unset($_SESSION['applied_coupon']);
        }
    } catch (PDOException $e) {
        unset($_SESSION['applied_coupon']);
    }
}
$discount_amount = (float)($coupon['discount'] ?? 0.0);
$total = $subtotal - $discount_amount;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
        $action = $_POST['action'] ?? 'complete';
        $coupon_code = trim($_POST['coupon_code'] ?? '');

        if ($action === 'apply_coupon') {
            try {
                $c = couponDiscount($pdo, $coupon_code, $subtotal);
                if ($c['code'] === '') {
                    $error = $c['label'] ?: "Invalid coupon.";
                    unset($_SESSION['applied_coupon']);
                } else {
                    $_SESSION['applied_coupon'] = ['code' => $c['code']];
                    header('Location: payment.php');
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Unable to apply coupon right now.";
            }
        } else {
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $allowed = ['upi', 'card', 'netbanking', 'cash'];
            if (!in_array($payment_method, $allowed, true)) {
                $error = "Please select a valid payment method.";
            } else {
                // Recalculate coupon at confirmation time.
                $finalCoupon = ['code' => '', 'discount' => 0.0, 'label' => ''];
                try {
                    $finalCoupon = couponDiscount($pdo, $coupon_code, $subtotal);
                } catch (PDOException $e) {
                }
                $finalDiscount = (float)($finalCoupon['discount'] ?? 0.0);
                $finalTotal = $subtotal - $finalDiscount;

                $user_id = (int)$_SESSION['user_id'];
                $payment_status = ($payment_method === 'cash') ? 'pending' : 'completed';

                try {
                    $pdo->beginTransaction();

                    // Slot locking + decrement (atomic)
                    $slotStmt = $pdo->prepare(
                        "SELECT id, available_count FROM booking_slots
                         WHERE service_id = ? AND date = ? AND time = ? FOR UPDATE"
                    );
                    $slotStmt->execute([$service_id, $service_date, $service_time]);
                    $slot = $slotStmt->fetch();
                    if (!$slot) {
                        // Initialize slot if not pre-seeded, based on currently available technicians.
                        $cnt = $pdo->prepare("SELECT COUNT(*) FROM technicians WHERE service_id = ? AND status = 'available' AND deleted_at IS NULL");
                        $cnt->execute([$service_id]);
                        $totalTech = (int)$cnt->fetchColumn();
                        if ($totalTech > 0) {
                            $ins = $pdo->prepare(
                                "INSERT INTO booking_slots (service_id, date, time, available_count)
                                 VALUES (?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE available_count = available_count"
                            );
                            $ins->execute([$service_id, $service_date, $service_time, $totalTech]);
                            $slotStmt->execute([$service_id, $service_date, $service_time]);
                            $slot = $slotStmt->fetch();
                        }
                    }
                    if (!$slot || (int)$slot['available_count'] < $technician_count) {
                        $pdo->rollBack();
                        $error = "No technicians available at selected time. Please choose another slot.";
                    } else {
                        $upd = $pdo->prepare(
                            "UPDATE booking_slots
                             SET available_count = available_count - ?
                             WHERE id = ?"
                        );
                        $upd->execute([$technician_count, (int)$slot['id']]);

                        // Generate booking unique id via sequence table
                        $year = (int)date('Y');
                        $seqStmt = $pdo->prepare("SELECT next_number FROM booking_sequences WHERE year = ? FOR UPDATE");
                        $seqStmt->execute([$year]);
                        $next = $seqStmt->fetchColumn();
                        if ($next === false) {
                            $seqNum = 1;
                            $insSeq = $pdo->prepare("INSERT INTO booking_sequences (year, next_number) VALUES (?, ?)");
                            $insSeq->execute([$year, 2]);
                        } else {
                            $seqNum = (int)$next;
                            $updSeq = $pdo->prepare("UPDATE booking_sequences SET next_number = ? WHERE year = ?");
                            $updSeq->execute([$seqNum + 1, $year]);
                        }
                        $booking_unique_id = 'BK' . $year . str_pad((string)$seqNum, 3, '0', STR_PAD_LEFT);

                        $insert = $pdo->prepare(
                            "INSERT INTO bookings
                             (booking_unique_id, user_id, service_id, technician_count, service_date, service_time,
                              emergency_level, address, full_address, state, city, area, pincode, flat_no, landmark,
                              latitude, longitude, contact, contact_number, preferred_time,
                              payment_method, payment_status, coupon_code, discount_amount, subtotal_amount, total_amount,
                              problem_description, status)
                             VALUES
                             (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Requested')"
                        );

                        $preferred_time = $service_date . ' ' . $service_time;
                        $fullAddress = (string)($pending['full_address'] ?? '');
                        if ($fullAddress === '') {
                            $fullAddress = (string)($pending['address'] ?? '');
                        }
                        $insert->execute([
                            $booking_unique_id,
                            $user_id,
                            $service_id,
                            $technician_count,
                            $service_date,
                            $service_time,
                            (string)($pending['emergency_level'] ?? 'Medium'),
                            $fullAddress, // legacy address column kept for compatibility
                            $fullAddress,
                            (string)($pending['state'] ?? ''),
                            (string)($pending['city'] ?? ''),
                            (string)($pending['area'] ?? ''),
                            (string)($pending['pincode'] ?? ''),
                            (string)($pending['flat_no'] ?? ''),
                            (string)($pending['landmark'] ?? ''),
                            $pending['latitude'] ?? null,
                            $pending['longitude'] ?? null,
                            (string)($pending['contact'] ?? ''),
                            (string)($pending['contact'] ?? ''),
                            $preferred_time,
                            $payment_method,
                            $payment_status,
                            $finalCoupon['code'] ?: null,
                            $finalDiscount,
                            $subtotal,
                            $finalTotal,
                            (string)($pending['problem_description'] ?? '')
                        ]);
                        $booking_id = (int)$pdo->lastInsertId();

                        // Log initial status
                        $log = $pdo->prepare("INSERT INTO booking_logs (booking_id, old_status, new_status, note, changed_by_user_id) VALUES (?, NULL, 'Requested', NULL, ?)");
                        $log->execute([$booking_id, $user_id]);

                        // User notification
                        $msg = "Booking confirmed: {$booking_unique_id} (" . (string)$service['title'] . ")";
                        try {
                            $n = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                            $n->execute([$user_id, $msg]);
                        } catch (PDOException $e) {
                        }

                        $pdo->commit();

                        // Send confirmation email (best-effort)
                        try {
                            $u = $pdo->prepare("SELECT email, name FROM users WHERE id = ? LIMIT 1");
                            $u->execute([$user_id]);
                            $usr = $u->fetch();
                            if ($usr && !empty($usr['email'])) {
                                $subject = "QuickFix Booking Confirmed - {$booking_unique_id}";
                                $body = "Hello " . ($usr['name'] ?? 'Customer') . ",\n\n"
                                    . "Your booking is confirmed.\n\n"
                                    . "Booking ID: {$booking_unique_id}\n"
                                    . "Service: " . (string)$service['title'] . "\n"
                                    . "Date/Time: {$service_date} {$service_time}\n"
                                    . "Technicians: {$technician_count}\n"
                                    . "Address: " . $fullAddress . "\n"
                                    . "Payment: {$payment_method} ({$payment_status})\n"
                                    . "Total: ₹" . number_format($finalTotal, 2) . "\n\n"
                                    . "- QuickFix";
                                $headers = "From: QuickFix <no-reply@quickfix.local>\r\n" .
                                           "Content-Type: text/plain; charset=UTF-8\r\n";
                                @mail($usr['email'], $subject, $body, $headers);
                            }
                        } catch (PDOException $e) {
                        }

                        // Cleanup session
                        unset($_SESSION['pending_booking'], $_SESSION['otp_verified'], $_SESSION['otp_hash'], $_SESSION['otp_expires_at'], $_SESSION['otp_attempts'], $_SESSION['applied_coupon']);

                        header('Location: thank_you.php?bk=' . urlencode($booking_unique_id));
                        exit();
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Booking Failed. Please try again.";
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<section class="container" style="padding: 60px 20px;">
    
    <div style="display: grid; grid-template-columns: 1fr 350px; gap: 40px; max-width: 1000px; margin: 0 auto;">
        
        <div>
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 24px;">Payment Selection</h2>
            
            <?php if(!empty($error)): ?>
                <div style="background-color: #FEF2F2; color: var(--danger-color); padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 24px; font-size: 0.95rem; border: 1px solid #FECACA;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="form-container" style="max-width: none; margin: 0;">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                
                <h3 style="font-size: 1.2rem; margin-bottom: 20px; font-weight: 700;">How would you like to pay?</h3>
                
                <!-- Coupon -->
                <div style="display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: end; margin-bottom: 20px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="coupon_code">Coupon code (optional)</label>
                        <input type="text" id="coupon_code" name="coupon_code" class="form-control" value="<?php echo htmlspecialchars($applied_code); ?>" placeholder="e.g. SAVE10">
                    </div>
                    <button type="submit" name="action" value="apply_coupon" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); height: 46px;">
                        Apply
                    </button>
                </div>

                <!-- Payment Options -->
                <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 32px;">
                    
                    <label style="border: 2px solid var(--primary-color); border-radius: var(--border-radius-sm); padding: 20px; display: flex; align-items: center; cursor: pointer; background: #FAFAFA;">
                        <input type="radio" name="payment_method" value="cash" checked style="width: 20px; height: 20px; accent-color: var(--primary-color); margin-right: 16px;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 700; font-size: 1.1rem; color: var(--text-main); margin-bottom: 4px;">Cash on Service / Pay Later</span>
                            <span style="font-size: 0.9rem; color: var(--text-muted);">Pay directly to the professional after the service is completed.</span>
                        </div>
                    </label>
                    
                    <label style="border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); padding: 20px; display: flex; align-items: center; cursor: pointer;">
                        <input type="radio" name="payment_method" value="upi" style="width: 20px; height: 20px; margin-right: 16px; accent-color: var(--primary-color);">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 700; font-size: 1.1rem; color: var(--text-main); margin-bottom: 4px;">UPI</span>
                            <span style="font-size: 0.9rem; color: var(--text-muted);">Pay using UPI (simulated).</span>
                        </div>
                    </label>

                    <label style="border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); padding: 20px; display: flex; align-items: center; cursor: pointer;">
                        <input type="radio" name="payment_method" value="card" style="width: 20px; height: 20px; margin-right: 16px; accent-color: var(--primary-color);">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 700; font-size: 1.1rem; color: var(--text-main); margin-bottom: 4px;">Card</span>
                            <span style="font-size: 0.9rem; color: var(--text-muted);">Pay via card (simulated).</span>
                        </div>
                        <div style="margin-left: auto; display: flex; gap: 8px; font-size: 1.5rem; color: var(--text-light);">
                            <i class="fa-brands fa-cc-visa"></i>
                            <i class="fa-brands fa-cc-mastercard"></i>
                        </div>
                    </label>

                    <label style="border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); padding: 20px; display: flex; align-items: center; cursor: pointer;">
                        <input type="radio" name="payment_method" value="netbanking" style="width: 20px; height: 20px; margin-right: 16px; accent-color: var(--primary-color);">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 700; font-size: 1.1rem; color: var(--text-main); margin-bottom: 4px;">Netbanking</span>
                            <span style="font-size: 0.9rem; color: var(--text-muted);">Pay via netbanking (simulated).</span>
                        </div>
                    </label>
                    
                </div>
                
                <div style="background: #FFFBEB; border: 1px solid #FDE68A; padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 32px; display: flex; gap: 12px; align-items: flex-start;">
                    <i class="fa-solid fa-circle-info" style="color: #D97706; margin-top: 2px;"></i>
                    <p style="font-size: 0.9rem; color: #92400E; margin: 0;">By clicking "Complete Booking", you agree to our Terms of Service and Cancellation Policy.</p>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <a href="booking.php?service_id=<?php echo (int)$service_id; ?>" style="color: var(--text-muted); font-weight: 500;"><i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i>Back to Details</a>
                    
                    <button type="submit" class="btn-primary" style="font-size: 1.05rem; padding: 16px 40px; min-width: 250px;">
                        Confirm Booking • ₹<?php echo number_format($total, 2); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Order Summary Sidebar -->
        <div>
            <div class="service-card" style="padding: 24px; border-radius: var(--border-radius-md); position: sticky; top: 100px;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">Final Summary</h3>
                
                <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px dashed var(--border-color);">
                    <div style="display: flex; justify-content: space-between; font-size: 0.95rem;">
                        <span style="color: var(--text-muted);"><?php echo htmlspecialchars($service['title']); ?></span>
                        <span style="font-weight: 600; color: var(--text-main);">₹<?php echo number_format($unit_price, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.95rem;">
                        <span style="color: var(--text-muted);">Technicians</span>
                        <span style="font-weight: 600; color: var(--text-main);"><?php echo (int)$technician_count; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.95rem;">
                        <span style="color: var(--text-muted);">Date & Time</span>
                        <span style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($service_date . ' ' . $service_time); ?></span>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: var(--text-muted); margin-bottom: 12px;">
                    <span>Subtotal</span>
                    <span>₹<?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: var(--text-muted); margin-bottom: 16px;">
                    <span>Coupon</span>
                    <span><?php echo $applied_code !== '' && $discount_amount > 0 ? ('-₹' . number_format($discount_amount, 2)) : '—'; ?></span>
                </div>
                
                <div style="border-top: 1px solid var(--primary-color); padding-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 800; font-size: 1.2rem;">Total Amount</span>
                    <span style="font-weight: 800; font-size: 1.5rem; color: var(--primary-color);">₹<?php echo number_format($total, 2); ?></span>
                </div>

            </div>
        </div>
        
    </div>
</section>

<?php include 'includes/footer.php'; ?>
