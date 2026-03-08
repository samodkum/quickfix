<?php
require_once 'config/db.php';

if(!isset($_GET['service_id'])){
die("Service not found");
}

$service_id = (int)$_GET['service_id'];

$stmt = $pdo->prepare("
SELECT * FROM technicians
WHERE service_id = ?
AND deleted_at IS NULL
");

$stmt->execute([$service_id]);
$techs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Technicians</title>

<style>

body{
font-family:Arial;
background:#f5f7fb;
}

.container{
width:90%;
margin:auto;
margin-top:40px;
display:grid;
grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
gap:25px;
}

.card{
background:white;
border-radius:12px;
box-shadow:0 10px 25px rgba(0,0,0,0.08);
overflow:hidden;
transition:0.3s;
}

.card:hover{
transform:translateY(-6px);
}

.card img{
width:100%;
height:180px;
object-fit:cover;
}

.body{
padding:16px;
}

.name{
font-size:18px;
font-weight:bold;
}

.rating{
color:#ff9800;
}

.badge{
background:#eef3ff;
padding:4px 8px;
border-radius:6px;
font-size:12px;
}

.btn{
display:block;
text-align:center;
background:#111;
color:white;
padding:10px;
border-radius:6px;
text-decoration:none;
margin-top:10px;
}

.empty{
text-align:center;
font-size:18px;
margin-top:60px;
}

</style>
</head>

<body>

<h2 style="text-align:center">Available Technicians</h2>

<?php if(!$techs): ?>

<div class="empty">
No technician available
</div>

<?php else: ?>

<div class="container">

<?php foreach($techs as $t): ?>

<div class="card">

<img src="<?= $t['photo'] ?: 'images/default-tech.jpg' ?>">

<div class="body">

<div class="name"><?= htmlspecialchars($t['name']) ?></div>

<p class="rating">
⭐ <?= $t['rating'] ?> (<?= $t['total_reviews'] ?> reviews)
</p>

<p>
Experience:
<span class="badge"><?= $t['experience'] ?> years</span>
</p>

<p>
Jobs completed: <?= $t['total_jobs_completed'] ?>
</p>

<p>
Contact: <?= substr($t['phone'],0,5) ?>*****
</p>

<a class="btn"
href="booking.php?tech_id=<?= $t['id'] ?>">
Select & Book
</a>

</div>
</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</body>
</html>