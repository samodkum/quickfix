<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/sms.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?msg=login_required');
    exit();
}

if (empty($_SESSION['pending_booking']) || !is_array($_SESSION['pending_booking'])) {
    header('Location: services.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// Fetch user contact points (email for OTP delivery fallback)
$user = null;
try {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
}

function generateOtp(): string {
    return (string)random_int(100000, 999999);
}

function sendOtpEmail(string $toEmail, string $toName, string $otp): bool {
    $subject = "Your QuickFix OTP";
    $body = "Hello {$toName},\n\nYour OTP to confirm the booking is: {$otp}\n\nThis OTP is valid for 10 minutes.\n\n- QuickFix";
    $headers = "From: QuickFix <no-reply@quickfix.local>\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($toEmail, $subject, $body, $headers);
}

function otpIsValid(): bool {
    if (empty($_SESSION['otp_hash']) || empty($_SESSION['otp_expires_at'])) {
        return false;
    }
    return time() <= (int)$_SESSION['otp_expires_at'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'send_otp') {
            $otp = generateOtp();
            $_SESSION['otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
            $_SESSION['otp_expires_at'] = time() + 600; // 10 min
            $_SESSION['otp_attempts'] = 0;
            unset($_SESSION['otp_verified']);

            $sent = false;
            $toPhone = (string)($_SESSION['pending_booking']['contact'] ?? '');
            if ($toPhone !== '') {
                $sent = send_sms($pdo, $toPhone, "Your QuickFix OTP is {$otp}. Valid for 10 minutes.");
            }
            if ($user && !empty($user['email'])) {
                $sent = $sent || sendOtpEmail($user['email'], $user['name'] ?? 'Customer', $otp);
            }
            $success = $sent ? "OTP sent. Please check your SMS/email." : "OTP generated. Please check your email (mail/SMS may not be configured on this server).";
        } elseif ($action === 'verify_otp') {
            $entered = trim($_POST['otp'] ?? '');
            if (!otpIsValid()) {
                $error = "OTP expired. Please resend OTP.";
            } elseif ($entered === '') {
                $error = "Please enter the OTP.";
            } else {
                $_SESSION['otp_attempts'] = (int)($_SESSION['otp_attempts'] ?? 0) + 1;
                if ($_SESSION['otp_attempts'] > 5) {
                    $error = "Too many attempts. Please resend OTP.";
                } elseif (password_verify($entered, (string)$_SESSION['otp_hash'])) {
                    $_SESSION['otp_verified'] = true;
                    header('Location: payment.php');
                    exit();
                } else {
                    $error = "Invalid OTP. Please try again.";
                }
            }
        }
    }
}

// Auto-send OTP if none exists or expired.
if (empty($_SESSION['otp_verified']) && !otpIsValid()) {
    $otp = generateOtp();
    $_SESSION['otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
    $_SESSION['otp_expires_at'] = time() + 600;
    $_SESSION['otp_attempts'] = 0;

    $sent = false;
    $toPhone = (string)($_SESSION['pending_booking']['contact'] ?? '');
    if ($toPhone !== '') {
        $sent = send_sms($pdo, $toPhone, "Your QuickFix OTP is {$otp}. Valid for 10 minutes.");
    }
    if ($user && !empty($user['email'])) {
        $sent = $sent || sendOtpEmail($user['email'], $user['name'] ?? 'Customer', $otp);
    }
    $success = $sent ? "OTP sent. Please check your SMS/email." : "OTP generated. Please check your email (mail/SMS may not be configured on this server).";
}

include 'includes/header.php';
?>

<section class="container" style="padding: 60px 20px;">
    <div class="form-container" style="max-width: 520px;">
        <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; text-align: center;">Verify OTP</h2>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 28px;">
            We verify bookings to prevent fake requests.
        </p>

        <?php if(!empty($success)): ?>
            <div style="background-color: #ECFDF5; color: var(--success-color); padding: 14px; border-radius: var(--border-radius-sm); margin-bottom: 18px; border: 1px solid #A7F3D0;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div style="background-color: #FEF2F2; color: var(--danger-color); padding: 14px; border-radius: var(--border-radius-sm); margin-bottom: 18px; border: 1px solid #FECACA;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" style="margin-top: 10px;">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="verify_otp">

            <div class="form-group">
                <label for="otp">Enter OTP</label>
                <input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="form-control" placeholder="6-digit OTP" required>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; font-size: 1.05rem;">
                Verify & Continue
            </button>
        </form>

        <form method="POST" style="margin-top: 14px; text-align: center;">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="send_otp">
            <button type="submit" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main);">
                Resend OTP
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center;">
            <a href="booking.php?service_id=<?php echo (int)($_SESSION['pending_booking']['service_id'] ?? 0); ?>" style="color: var(--text-muted); font-weight: 500;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i>Back to booking form
            </a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

