<?php
include 'db.php';

$id = $_GET['id'];

$query = "SELECT * FROM technicians WHERE id='$id'";
$result = mysqli_query($conn,$query);
$data = mysqli_fetch_assoc($result);

?>

<!DOCTYPE html>
<html>
<head>
<title>Technician Profile</title>

<style>

body{
font-family:Arial;
background:#f5f5f5;
}

.container{
width:600px;
margin:auto;
background:white;
padding:30px;
margin-top:50px;
border-radius:10px;
box-shadow:0 5px 15px rgba(0,0,0,0.1);
text-align:center;
}

img{
width:150px;
height:150px;
border-radius:50%;
object-fit:cover;
}

.slot{
display:inline-block;
margin:8px;
padding:10px 15px;
background:#28a745;
color:white;
border-radius:6px;
cursor:pointer;
}

.slot:hover{
background:#218838;
}

</style>

</head>

<body>

<div class="container">

<img src="uploads/<?php echo $data['photo']; ?>">

<h2><?php echo $data['name']; ?></h2>

<p>⭐ Rating: <?php echo $data['rating']; ?></p>
<p>Experience: <?php echo $data['experience']; ?> years</p>
<p>Jobs Completed: <?php echo $data['jobs_completed']; ?></p>
<p>Reviews: <?php echo $data['reviews']; ?></p>

<h3>Available Time Slots</h3>

<div class="slot">9:00 AM</div>
<div class="slot">11:00 AM</div>
<div class="slot">1:00 PM</div>
<div class="slot">3:00 PM</div>
<div class="slot">5:00 PM</div>

<br><br>

<a href="book.php?id=<?php echo $data['id']; ?>">
<button style="padding:10px 20px;background:#007bff;color:white;border:none;border-radius:6px;">Continue Booking</button>
</a>

</div>

</body>
</html>