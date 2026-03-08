<?php
include 'db.php';

$query = "SELECT * FROM technicians";
$result = mysqli_query($conn,$query);
?>

<!DOCTYPE html>
<html>
<head>
<title>Technicians</title>

<style>

body{
font-family: Arial;
background:#f5f5f5;
margin:0;
padding:0;
}

.container{
width:90%;
margin:auto;
padding:30px;
}

.title{
text-align:center;
font-size:30px;
margin-bottom:30px;
}

.grid{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:25px;
}

.card{
background:white;
border-radius:12px;
padding:20px;
box-shadow:0 5px 15px rgba(0,0,0,0.1);
text-align:center;
transition:0.3s;
}

.card:hover{
transform:translateY(-5px);
}

.card img{
width:120px;
height:120px;
border-radius:50%;
object-fit:cover;
margin-bottom:10px;
}

.name{
font-size:20px;
font-weight:bold;
}

.info{
color:#666;
font-size:14px;
margin:5px 0;
}

.rating{
color:orange;
font-size:16px;
}

.btn{
margin-top:10px;
padding:10px 20px;
background:#007bff;
color:white;
border:none;
border-radius:6px;
cursor:pointer;
}

.btn:hover{
background:#0056b3;
}

</style>

</head>

<body>

<div class="container">

<h2 class="title">Available Technicians</h2>

<div class="grid">

<?php
while($row=mysqli_fetch_assoc($result)){
?>

<div class="card">

<img src="uploads/<?php echo $row['photo']; ?>">

<div class="name"><?php echo $row['name']; ?></div>

<div class="rating">⭐ <?php echo $row['rating']; ?></div>

<div class="info">Experience: <?php echo $row['experience']; ?> years</div>

<div class="info">Jobs Completed: <?php echo $row['jobs_completed']; ?></div>

<div class="info">Reviews: <?php echo $row['reviews']; ?></div>

<div class="info">Contact: <?php echo $row['contact']; ?></div>

<a href="technician_profile.php?id=<?php echo $row['id']; ?>">
<button class="btn">Book Now</button>
</a>

</div>

<?php
}
?>

</div>

</div>

</body>
</html>