<?php
include 'db.php';

$result = mysqli_query($conn, "SELECT * FROM technicians");
?>

<!DOCTYPE html>
<html>
<head>
<title>Technicians</title>

<style>

body{
font-family: Arial;
background:#f4f6f9;
margin:0;
padding:0;
}

.container{
width:90%;
margin:auto;
margin-top:40px;
}

h2{
text-align:center;
margin-bottom:30px;
}

.grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
gap:20px;
}

.card{
background:white;
padding:20px;
border-radius:10px;
box-shadow:0 4px 10px rgba(0,0,0,0.1);
text-align:center;
}

.card h3{
margin:10px 0;
}

.card p{
color:gray;
}

.btn{
background:#007bff;
color:white;
border:none;
padding:10px 20px;
border-radius:5px;
cursor:pointer;
}

.btn:hover{
background:#0056b3;
}

.slots{
display:none;
margin-top:15px;
}

.slot-btn{
background:#28a745;
color:white;
border:none;
padding:6px 12px;
margin:5px;
border-radius:4px;
cursor:pointer;
}

.slot-btn:hover{
background:#1e7e34;
}

</style>

<script>

function showSlots(id){
var x = document.getElementById("slots"+id);

if(x.style.display==="none" || x.style.display===""){
x.style.display="block";
}else{
x.style.display="none";
}

}

</script>

</head>

<body>

<div class="container">

<h2>Available Technicians</h2>

<div class="grid">

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<div class="card">

<h3><?php echo $row['name']; ?></h3>

<p><?php echo $row['service']; ?></p>

<p>⭐ Rating: <?php echo $row['rating']; ?></p>

<button class="btn" onclick="showSlots(<?php echo $row['id']; ?>)">
Book Now
</button>

<div class="slots" id="slots<?php echo $row['id']; ?>">

<p>Select Time:</p>

<button class="slot-btn">10:00 AM</button>
<button class="slot-btn">12:00 PM</button>
<button class="slot-btn">2:00 PM</button>
<button class="slot-btn">4:00 PM</button>

</div>

</div>

<?php } ?>

</div>

</div>

</body>
</html>