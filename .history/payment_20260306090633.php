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

$error = '';

$pending = $_SESSION['pending_booking'];

$service_id = (int)$pending['service_id'];
$technician_count = (int)$pending['technician_count'];
$service_date = $pending['service_date'];
$service_time = $pending['service_time'];

$stmt = $pdo->prepare("SELECT id,title,price FROM services WHERE id=?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if(!$service){
    header("Location: services.php");
    exit();
}

$unit_price = (float)$service['price'];
$subtotal = $unit_price * $technician_count;

$coupon_code = $_POST['coupon_code'] ?? '';
$discount = 0;
$total = $subtotal;

if($_SERVER['REQUEST_METHOD']=='POST'){

if(!csrf_validate($_POST['_csrf'] ?? null)){
    $error="Security check failed";
}else{

$payment_method = $_POST['payment_method'] ?? 'cash';
$payment_status = ($payment_method=='cash') ? 'pending' : 'completed';

$user_id = $_SESSION['user_id'];

try{

$pdo->beginTransaction();

$slotStmt = $pdo->prepare("SELECT id,available_count FROM booking_slots 
WHERE service_id=? AND date=? AND time=? FOR UPDATE");

$slotStmt->execute([$service_id,$service_date,$service_time]);
$slot=$slotStmt->fetch();

if(!$slot || $slot['available_count'] < $technician_count){

$pdo->rollBack();
$error="No technicians available";

}else{

$updateSlot=$pdo->prepare("UPDATE booking_slots 
SET available_count=available_count-? WHERE id=?");

$updateSlot->execute([$technician_count,$slot['id']]);


$year=date("Y");

$seq=$pdo->prepare("SELECT next_number FROM booking_sequences WHERE year=? FOR UPDATE");
$seq->execute([$year]);
$num=$seq->fetchColumn();

if(!$num){

$seqNum=1;

$pdo->prepare("INSERT INTO booking_sequences(year,next_number) VALUES(?,2)")
->execute([$year]);

}else{

$seqNum=$num;

$pdo->prepare("UPDATE booking_sequences SET next_number=? WHERE year=?")
->execute([$seqNum+1,$year]);

}

$booking_unique_id="BK".$year.str_pad($seqNum,3,"0",STR_PAD_LEFT);

$preferred_time=$service_date." ".$service_time;

$fullAddress=$pending['full_address'];

$insert=$pdo->prepare("
INSERT INTO bookings
(booking_unique_id,user_id,service_id,technician_count,service_date,service_time,
emergency_level,address,full_address,state,city,area,pincode,flat_no,landmark,
latitude,longitude,contact,contact_number,preferred_time,
payment_method,payment_status,coupon_code,discount_amount,subtotal_amount,total_amount,
problem_description,status)

VALUES
(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$insert->execute([

$booking_unique_id,
$user_id,
$service_id,
$technician_count,
$service_date,
$service_time,
$pending['emergency_level'],
$fullAddress,
$fullAddress,
$pending['state'],
$pending['city'],
$pending['area'],
$pending['pincode'],
$pending['flat_no'],
$pending['landmark'],
$pending['latitude'] ?? null,
$pending['longitude'] ?? null,
$pending['contact'],
$pending['contact'],
$preferred_time,
$payment_method,
$payment_status,
$coupon_code ?: null,
$discount,
$subtotal,
$total,
$pending['problem_description'],
"Requested"

]);

$booking_id=$pdo->lastInsertId();

$log=$pdo->prepare("
INSERT INTO booking_logs
(booking_id,old_status,new_status,changed_by_user_id)
VALUES(?,NULL,'Requested',?)
");

$log->execute([$booking_id,$user_id]);

$pdo->commit();

unset($_SESSION['pending_booking']);
unset($_SESSION['otp_verified']);

header("Location: thank_you.php?bk=".$booking_unique_id);
exit();

}

}catch(PDOException $e){

if($pdo->inTransaction()){
$pdo->rollBack();
}

$error="Booking Failed";

}

}

}

include "includes/header.php";
?>

<div class="container" style="padding:60px">

<h2>Payment</h2>

<?php if($error): ?>

<div style="color:red"><?php echo $error ?></div>

<?php endif; ?>

<form method="POST">

<input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

<h3>Select Payment</h3>

<label>
<input type="radio" name="payment_method" value="cash" checked>
Cash on Service
</label>

<br><br>

<label>
<input type="radio" name="payment_method" value="upi">
UPI
</label>

<br><br>

<label>
<input type="radio" name="payment_method" value="card">
Card
</label>

<br><br>

<label>
<input type="radio" name="payment_method" value="netbanking">
Netbanking
</label>

<br><br>

<button type="submit">Confirm Booking ₹<?php echo $total ?></button>

</form>

</div>

<?php include "includes/footer.php"; ?>