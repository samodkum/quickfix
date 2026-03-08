<?php
require_once 'config/db.php';
require_once 'includes/csrf.php';

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

$pending = $_SESSION['pending_booking'];

$service_id = (int)$pending['service_id'];

$stmt = $pdo->prepare("SELECT title,price FROM services WHERE id=?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

$price = $service['price'];
$technicians = $pending['technician_count'];

$total = $price * $technicians;

$error="";
?>

<?php include "includes/header.php"; ?>

<style>

.payment-container{
max-width:500px;
margin:80px auto;
background:white;
padding:35px;
border-radius:10px;
box-shadow:0 10px 30px rgba(0,0,0,0.1);
font-family:Arial;
}

.payment-title{
text-align:center;
font-size:28px;
font-weight:bold;
margin-bottom:20px;
}

.service-box{
background:#f5f7fb;
padding:15px;
border-radius:8px;
margin-bottom:20px;
}

.service-box p{
margin:6px 0;
font-size:15px;
}

.total{
font-size:20px;
font-weight:bold;
color:#0d6efd;
margin-top:10px;
}

.payment-method{
margin-top:20px;
}

.payment-method label{
display:block;
padding:12px;
border:1px solid #ddd;
border-radius:8px;
margin-bottom:10px;
cursor:pointer;
transition:0.3s;
}

.payment-method label:hover{
background:#f1f4ff;
border-color:#0d6efd;
}

.pay-btn{
width:100%;
padding:14px;
background:#0d6efd;
color:white;
border:none;
border-radius:8px;
font-size:16px;
font-weight:bold;
margin-top:20px;
cursor:pointer;
transition:0.3s;
}

.pay-btn:hover{
background:#0b5ed7;
}

</style>


<div class="payment-container">

<div class="payment-title">
Confirm Payment
</div>

<div class="service-box">

<p><b>Service:</b> <?php echo $service['title']; ?></p>

<p><b>Technicians:</b> <?php echo $technicians; ?></p>

<p><b>Price per technician:</b> ₹<?php echo $price; ?></p>

<p class="total">Total: ₹<?php echo $total; ?></p>

</div>


<form method="POST" action="payment_process.php">

<div class="payment-method">

<label>
<input type="radio" name="payment_method" value="cash" checked>
 Cash on Service
</label>

<label>
<input type="radio" name="payment_method" value="upi">
 UPI Payment
</label>

<label>
<input type="radio" name="payment_method" value="card">
 Debit / Credit Card
</label>

<label>
<input type="radio" name="payment_method" value="netbanking">
 Net Banking
</label>

</div>

<button class="pay-btn">
Confirm Booking ₹<?php echo $total; ?>
</button>

</form>

</div>

<?php include "includes/footer.php"; ?>