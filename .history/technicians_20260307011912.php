<?php
// technicians.php
// This page shows technicians and their available time slots

require_once 'config/db.php';
include 'includes/header.php';

// Get service id from URL
$service_id = $_GET['service_id'] ?? 0;

// Fetch service info
$stmt = $pdo->prepare("SELECT * FROM services WHERE id=?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if(!$service){
    echo "<h2>Service not found</h2>";
    exit();
}

// Fetch technicians for this service
$stmt = $pdo->prepare("SELECT * FROM technicians WHERE service_id=? AND status='available'");
$stmt->execute([$service_id]);
$technicians = $stmt->fetchAll();

// Fetch available booking slots
$slotStmt = $pdo->prepare("SELECT * FROM booking_slots WHERE service_id=? AND available_count>0 ORDER BY time ASC");
$slotStmt->execute([$service_id]);
$slots = $slotStmt->fetchAll();

?>

<section class="container" style="padding:60px 20px;">

<div style="text-align:center;margin-bottom:40px;">
<h1><?php echo htmlspecialchars($service['title']); ?></h1>
<p>Select a technician and available time slot</p>
</div>


<div class="technician-grid">

<?php foreach($technicians as $tech): ?>

<div class="tech-card">

<!-- Technician Photo -->
<div class="tech-img">
<img src="<?php echo $tech['photo'] ?: 'https://via.placeholder.com/200'; ?>">
</div>

<div class="tech-content">

<h3><?php echo htmlspecialchars($tech['name']); ?></h3>

<div class="rating">
⭐ <?php echo $tech['rating']; ?>
(<?php echo $tech['total_reviews']; ?> reviews)
</div>

<p><b>Experience:</b> <?php echo $tech['experience']; ?> years</p>

<p><b>Jobs Completed:</b> <?php echo $tech['total_jobs_completed']; ?></p>

<p class="skills">
<?php echo htmlspecialchars($tech['skills']); ?>
</p>

<!-- Available Slots -->
<div class="slots">

<?php foreach($slots as $slot): ?>

<a class="slot-btn"
href="booking.php?service_id=<?php echo $service_id ?>&time=<?php echo $slot['time'] ?>">
<?php echo $slot['time'] ?>
</a>

<?php endforeach; ?>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</section>


<style>

/* Grid layout */

.technician-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
gap:25px;
}

/* Card */

.tech-card{
background:#fff;
border-radius:14px;
box-shadow:0 10px 25px rgba(0,0,0,0.08);
overflow:hidden;
transition:0.3s;
}

.tech-card:hover{
transform:translateY(-6px);
box-shadow:0 20px 40px rgba(0,0,0,0.12);
}

/* Image */

.tech-img img{
width:100%;
height:200px;
object-fit:cover;
}

/* Content */

.tech-content{
padding:20px;
}

.tech-content h3{
margin-bottom:6px;
}

.rating{
color:#ff9800;
margin-bottom:10px;
}

.skills{
font-size:14px;
color:#666;
margin-bottom:10px;
}

/* Slots */

.slots{
margin-top:10px;
display:flex;
flex-wrap:wrap;
gap:10px;
}

.slot-btn{
background:#0d6efd;
color:#fff;
padding:8px 14px;
border-radius:6px;
font-size:14px;
text-decoration:none;
}

.slot-btn:hover{
background:#0b5ed7;
}

</style>

<?php include 'includes/footer.php'; ?>