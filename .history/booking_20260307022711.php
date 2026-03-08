

$slots = mysqli_query($conn,"SELECT * FROM booking_slots 
WHERE tech_id=$tech_id AND status='available'");
?>

<!DOCTYPE html>
<html>
<head>

<title>Book Technician</title>

<style>

body{
font-family:Segoe UI;
background:#f5f6fa;
}

.container{
width:600px;
margin:auto;
background:white;
padding:30px;
margin-top:50px;
border-radius:10px;
box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

.name{
font-size:22px;
font-weight:bold;
margin-bottom:10px;
}

.rating{
color:orange;
margin-bottom:20px;
}

.slot-grid{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:10px;
}

.slot{
padding:10px;
border:1px solid #ddd;
text-align:center;
border-radius:6px;
cursor:pointer;
}

.slot:hover{
background:black;
color:white;
}

.btn{
margin-top:20px;
width:100%;
padding:12px;
background:black;
color:white;
border:none;
border-radius:6px;
cursor:pointer;
}

</style>

</head>

<body>

<div class="container">

<div class="name">
<?php echo $tech_data['name']; ?>
</div>

<div class="rating">
⭐ <?php echo $tech_data['rating']; ?>
</div>

<form method="POST">

<div class="slot-grid">

<?php
while($row=mysqli_fetch_assoc($slots)){
?>

<label class="slot">
<input type="radio" name="slot_id" value="<?php echo $row['id']; ?>" required>
<?php echo $row['slot_time']; ?>
</label>

<?php } ?>

</div>

<button class="btn" name="book">
Confirm Booking
</button>

</form>

</div>

</body>
</html>

<?php

if(isset($_POST['book'])){

$slot_id = intval($_POST['slot_id']);

mysqli_query($conn,"UPDATE booking_slots 
SET status='booked' 
WHERE id=$slot_id");

echo "<script>alert('Booking Confirmed');</script>";

}
?>