<?php
require_once 'config/db.php';
include 'includes/header.php';

$stmt = $pdo->query("SELECT * FROM services WHERE is_active=1 AND deleted_at IS NULL");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.services-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
gap:25px;
margin-top:40px;
}

.service-card{
background:white;
border-radius:14px;
box-shadow:0 8px 20px rgba(0,0,0,0.08);
overflow:hidden;
transition:0.3s;
}

.service-card:hover{
transform:translateY(-5px);
}

.service-img{
width:100%;
height:180px;
object-fit:cover;
}

.service-body{
padding:18px;
}

.book-btn{
display:inline-block;
background:#111;
color:white;
padding:10px 16px;
border-radius:6px;
text-decoration:none;
margin-top:10px;
}
</style>

<div class="container" style="padding:60px">

<h2>All Services</h2>

<div class="services-grid">

<?php foreach($services as $s): ?>

<div class="service-card">

<img class="service-img" src="<?= $s['image_url'] ?>">

<div class="service-body">

<h3><?= htmlspecialchars($s['title']) ?></h3>

<p><?= htmlspecialchars($s['description']) ?></p>

<b>₹<?= $s['price'] ?></b>

<br>

<a class="book-btn"
href="technicians.php?service_id=<?= $s['id'] ?>">
Book Now
</a>

</div>
</div>

<?php endforeach; ?>

</div>
</div>

<?php include 'includes/footer.php'; ?>