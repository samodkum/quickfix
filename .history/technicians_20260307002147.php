<?php
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['service_id'])) {
    header("Location: services.php");
    exit();
}

$service_id = (int)$_GET['service_id'];

$stmt = $pdo->prepare("SELECT title FROM services WHERE id=?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    header("Location: services.php");
    exit();
}

$stmt = $pdo->prepare("
SELECT * FROM technicians
WHERE service_id=? 
AND status='available'
AND deleted_at IS NULL
");

$stmt->execute([$service_id]);
$technicians = $stmt->fetchAll();

include "includes/header.php";
?>

<style>

.page-title{
text-align:center;
font-size:32px;
margin-bottom:10px;
font-weight:700;
}

.subtitle{
text-align:center;
color:#777;
margin-bottom:40px;
}

.tech-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
gap:25px;
}

.tech-card{
background:white;
border-radius:12px;
box-shadow:0 4px 18px rgba(0,0,0,0.08);
padding:25px;
transition:0.3s;
}

.tech-card:hover{
transform:translateY(-5px);
box-shadow:0 6px 25px rgba(0,0,0,0.12);
}

.tech-header{
display:flex;
align-items:center;
gap:15px;
margin-bottom:15px;
}

.tech-img{
width:70px;
height:70px;
border-radius:50%;
object-fit:cover;
background:#eee;
}

.tech-name{
font-size:18px;
font-weight:700;
}

.rating{
color:#f5a623;
font-weight:600;
font-size:14px;
}

.tech-info{
font-size:14px;
color:#555;
margin-bottom:6px;
}

.select-btn{
margin-top:15px;
display:block;
width:100%;
text-align:center;
background:#0066ff;
color:white;
padding:12px;
border-radius:8px;
text-decoration:none;
font-weight:600;
}

.select-btn:hover{
background:#004ecc;
}

</style>

<div class="container" style="padding:60px 20px">

<h2 class="page-title">
Available Technicians
</h2>

<p class="subtitle">
Service: <strong><?php echo htmlspecialchars($service['title']); ?></strong>
</p>

<div class="tech-grid">

<?php if($technicians): ?>

<?php foreach($technicians as $tech): ?>

<div class="tech-card">

<div class="tech-header">

<img 
class="tech-img"
src="<?php echo $tech['photo'] ?: 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>"
>

<div>

<div class="tech-name">
<?php echo htmlspecialchars($tech['name']); ?>
</div>

<div class="rating">
⭐ <?php echo number_format($tech['rating'],1); ?>
(<?php echo $tech['total_reviews']; ?> reviews)
</div>

</div>

</div>

<div class="tech-info">
📞 <?php echo htmlspecialchars($tech['phone']); ?>
</div>

<div class="tech-info">
💼 Experience: <?php echo $tech['experience'] ?? 'N/A'; ?> years
</div>

<div class="tech-info">
🧰 Skills: <?php echo htmlspecialchars($tech['skills'] ?? 'General Service'); ?>
</div>

<div class="tech-info">
✔ Jobs Completed: <?php echo $tech['total_jobs_completed']; ?>
</div>

<a 
class="select-btn"
href="booking.php?service_id=<?php echo $service_id; ?>&tech_id=<?php echo $tech['id']; ?>"
>
Select Technician
</a>

</div>

<?php endforeach; ?>

<?php else: ?>

<p>No technicians available right now.</p>

<?php endif; ?>

</div>

</div>

<?php include "includes/footer.php"; ?>