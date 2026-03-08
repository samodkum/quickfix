<?php
require_once "config/db.php";

$service_id = $_GET['service_id'] ?? 0;

$stmt = $pdo->prepare("
SELECT name,phone,photo,experience,rating,total_reviews,total_jobs_completed
FROM technicians
WHERE service_id=? AND status='available'
");

$stmt->execute([$service_id]);
$technicians = $stmt->fetchAll();
?>

<h2>Available Technicians</h2>

<style>

.tech-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
gap:20px;
}

.tech-card{
border:1px solid #ddd;
border-radius:10px;
padding:20px;
text-align:center;
box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

.tech-card img{
width:80px;
height:80px;
border-radius:50%;
object-fit:cover;
margin-bottom:10px;
}

.btn{
background:#4CAF50;
color:white;
padding:10px 20px;
border:none;
border-radius:6px;
cursor:pointer;
}

</style>

<div class="tech-grid">

<?php foreach($technicians as $tech): ?>

<div class="tech-card">

<img src="<?php echo $tech['photo']; ?>">

<h3><?php echo $tech['name']; ?></h3>

<p>⭐ <?php echo $tech['rating']; ?> (<?php echo $tech['total_reviews']; ?> reviews)</p>

<p>Experience: <?php echo $tech['experience']; ?> yrs</p>

<p>Jobs Done: <?php echo $tech['total_jobs_completed']; ?></p>

<p>📞 <?php echo $tech['phone']; ?></p>

<a href="booking.php?service_id=<?php echo $service_id; ?>">
<button class="btn">Select</button>
</a>

</div>

<?php endforeach; ?>

</div>