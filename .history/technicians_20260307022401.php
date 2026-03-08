<?php
// database connection
require_once 'config/db.php';

// service id check
if(!isset($_GET['service_id'])){
    echo "Service not selected";
    exit;
}

$service_id = (int)$_GET['service_id'];

// fetch technicians for that service
$stmt = $pdo->prepare("SELECT * FROM technicians WHERE service_id=? AND status='available'");
$stmt->execute([$service_id]);
$technicians = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>

<title>Available Technicians</title>

<style>

body{
font-family: Arial;
background:#f4f6f9;
margin:0;
}

.container{
width:90%;
margin:auto;
margin-top:40px;
display:flex;
gap:20px;
flex-wrap:wrap;
justify-content:center;
}

.card{
width:280px;
background:white;
border-radius:10px;
overflow:hidden;
box-shadow:0 5px 15px rgba(0,0,0,0.1);
transition:0.3s;
}

.card:hover{
transform:translateY(-5px);
}

.card img{
width:100%;
height:180px;
object-fit:cover;
}

.card-body{
padding:15px;
}

.name{
font-size:18px;
font-weight:bold;
}

.rating{
color:#f39c12;
margin-top:5px;
}

.badge{
display:inline-block;
background:#27ae60;
color:white;
padding:4px 8px;
border-radius:5px;
font-size:12px;
margin-top:6px;
}

.btn{
display:block;
text-align:center;
background:#000;
color:white;
padding:10px;
border-radius:6px;
text-decoration:none;
margin-top:10px;
}

.btn:hover{
background:#333;
}

.empty{
text-align:center;
margin-top:100px;
font-size:20px;
color:#777;
}

</style>

</head>

<body>

<h2 style="text-align:center;margin-top:30px;">Available Technicians</h2>

<div class="container">

<?php if(empty($technicians)){ ?>

<div class="empty">
No technicians available
</div>

<?php } ?>

<?php foreach($technicians as $tech){ ?>

<div class="card">

<img src="<?php echo $tech['photo'] ?: 'https://via.placeholder.com/300'; ?>">

<div class="card-body">

<div class="name">
<?php echo htmlspecialchars($tech['name']); ?>
</div>

<div class="rating">
⭐ <?php echo $tech['rating']; ?> (<?php echo $tech['total_reviews']; ?> reviews)
</div>

<div class="badge">
<?php echo $tech['experience']; ?> Years Experience
</div>

<p>Jobs Completed: <?php echo $tech['total_jobs_completed']; ?></p>

<p>Contact: <?php echo $tech['phone']; ?></p>

<a href="booking.php?tech_id=<?php echo $tech['id']; ?>" class="btn">
Select & Book
</a>

</div>

</div>

<?php } ?>

</div>

</body>
</html>