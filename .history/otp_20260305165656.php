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

// Fetch user email
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
    $body = "Hello {$toName},\n\nYour OTP to confirm the booking is: {$otp}\n\nValid for 10 minutes.\n\n- QuickFix";
    $headers = "From: QuickFix <no-reply@quickfix.local>\r\n";
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
        $error = "Security check failed.";
    } 
    else {

        $action = $_POST['action'] ?? '';

        // SEND OTP
        if ($action === 'send_otp') {

            $otp = generateOtp();

            $_SESSION['otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
            $_SESSION['otp_expires_at'] = time() + 600;
            $_SESSION['otp_attempts'] = 0;

            unset($_SESSION['otp_verified']);

            // TEST MODE (show OTP on screen)
            $success = "Your OTP is: " . $otp . " (Testing Mode)";
        }

        // VERIFY OTP
        elseif ($action === 'verify_otp') {

            $entered = trim($_POST['otp'] ?? '');

            if (!otpIsValid()) {
                $error = "OTP expired. Please resend OTP.";
            } 
            elseif ($entered === '') {
                $error = "Please enter the OTP.";
            } 
            else {

                $_SESSION['otp_attempts'] = (int)($_SESSION['otp_attempts'] ?? 0) + 1;

                if ($_SESSION['otp_attempts'] > 5) {
                    $error = "Too many attempts. Please resend OTP.";
                } 
                elseif (password_verify($entered, (string)$_SESSION['otp_hash'])) {

                    $_SESSION['otp_verified'] = true;

                    header('Location: payment.php');
                    exit();
                } 
                else {
                    $error = "Invalid OTP.";
                }
            }
        }
    }
}

// AUTO SEND OTP
if (empty($_SESSION['otp_verified']) && !otpIsValid()) {

    $otp = generateOtp();

    $_SESSION['otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
    $_SESSION['otp_expires_at'] = time() + 600;
    $_SESSION['otp_attempts'] = 0;

    $success = "Your OTP is: " . $otp . " (Testing Mode)";
}

include 'includes/header.php';
?>

<section class="container" style="padding:60px 20px;">
<div class="form-container" style="max-width:520px;">

<h2 style="text-align:center;">Verify OTP</h2>
<p style="text-align:center;color:#666;">We verify bookings to prevent fake requests.</p>

<?php if(!empty($success)): ?>
<div style="background:#ECFDF5;padding:12px;margin-bottom:15px;">
<?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if(!empty($error)): ?>
<div style="background:#FEF2F2;padding:12px;margin-bottom:15px;">
<?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
<input type="hidden" name="action" value="verify_otp">

<div class="form-group">
<label>Enter OTP</label>
<input name="otp" maxlength="6" required class="form-control">
</div>

<button type="submit" class="btn-primary" style="width:100%;">
Verify & Continue
</button>

</form>

<form method="POST" style="margin-top:10px;text-align:center;">

<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
<input type="hidden" name="action" value="send_otp">

<button type="submit" class="btn-outline">
Resend OTP
</button>

</form>

<div style="margin-top:20px;text-align:center;">
<a href="booking.php?service_id=<?php echo (int)($_SESSION['pending_booking']['service_id'] ?? 0); ?>">
Back to booking
</a>
</div>

</div>
</section>

<?php include 'includes/footer.php'; ?>