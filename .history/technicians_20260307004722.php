<?php
require_once 'config/db.php';
include 'includes/header.php';

$service_id = $_GET['service_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM technicians 
WHERE service_id=? AND status='available' AND deleted_at IS NULL");

$stmt->execute([$service_id]);
$technicians = $stmt->fetchAll();
?>

<section class="container" style="padding:60px 20px">

<h1 style="text-align:center;margin-bottom:50px">
Available Technicians
</h1>

<div style="
display:grid;
grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
gap:25px;
">

<?php foreach($technicians as $tech): ?>

<div style="
background:#fff;
border-radius:10px;
box-shadow:0 5px 15px rgba(0,0,0,0.1);
overflow:hidden;
transition:0.3s;
">

<img src="<?php echo $tech['photo'] ?: 'images/default-tech.jpg'; ?>"
style="width:100%;height:200px;object-fit:cover">

<div style="padding:20px">

<h3 style="margin-bottom:5px">
<?php echo htmlspecialchars($tech['name']); ?>
</h3>

<p style="color:gray;margin-bottom:10px">
<?php echo htmlspecialchars($tech['specialty']); ?>
</p>

<p>
⭐ <?php echo $tech['rating']; ?>
(<?php echo $tech['total_reviews']; ?> reviews)
</p>

<p>
Jobs Completed: <?php echo $tech['total_jobs_completed']; ?>
</p>

<p>
Experience: <?php echo $tech['experience']; ?> years
</p>

<p style="font-size:14px;color:#555">
<?php echo htmlspecialchars($tech['skills']); ?>
</p>

<br>

<a href="booking.php?service_id=<?php echo $service_id ?>&technician_id=<?php echo $tech['id'] ?>"

style="
display:block;
text-align:center;
background:#000;
color:#fff;
padding:10px;
border-radius:6px;
text-decoration:none;
font-weight:600;
">

Select Technician

</a>

</div>
</div>

<?php endforeach; ?>

</div>

</section>

<?php include 'includes/footer.php'; ?>