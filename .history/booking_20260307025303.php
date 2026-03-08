<?php
require_once 'config/db.php';

if(!isset($_GET['tech_id'])){
die("Technician not found");
}

$tech_id = (int)$_GET['tech_id'];
$user_id = 1; // temporary test user
$stmt = $pdo->prepare("SELECT * FROM technicians WHERE id=?");
$stmt->execute([$tech_id]);

$tech = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$tech){
die("Technician not found");
}

if($_SERVER['REQUEST_METHOD']=="POST"){

$date = $_POST['date'];
$time = $_POST['time'];

$insert = $pdo->prepare("
INSERT INTO bookings
(service_id,technician_count,service_date,service_time,status)
VALUES (?,?,?,?,'Requested')
");

$insert->execute([
$tech['service_id'],
1,
$date,
$time
]);

echo "<h3>Booking Successful</h3>";
exit;
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Booking</title>

<style>

body{
font-family:Arial;
background:#f5f5f5;
}

.box{
width:400px;
margin:80px auto;
background:white;
padding:25px;
border-radius:10px;
box-shadow:0 8px 20px rgba(0,0,0,0.1);
}

button{
width:100%;
padding:12px;
background:#111;
color:white;
border:none;
border-radius:6px;
}

</style>

</head>

<body>

<div class="box">

<h2>Book <?= $tech['name'] ?></h2>

<form method="POST">

<label>Date</label>
<input type="date" name="date" required>

<br><br>

<label>Time</label>
<select name="time">

<option>09:00 AM</option>
<option>11:00 AM</option>
<option>02:00 PM</option>
<option>05:00 PM</option>

</select>

<br><br>

<button>Confirm Booking</button>

</form>

</div>

</body>
</html>