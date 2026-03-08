<?php
// technicians.php — Premium technician selection page (Step 1 of booking)
date_default_timezone_set('Asia/Kolkata');
require_once 'config/db.php';

if (!isset($_GET['service_id'])) {
    header('Location: services.php');
    exit();
}

$service_id = (int)$_GET['service_id'];

// Fetch service info
$s_stmt = $pdo->prepare("SELECT id, title, price FROM services WHERE id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
$s_stmt->execute([$service_id]);
$service = $s_stmt->fetch();
if (!$service) {
    header('Location: services.php');
    exit();
}

// Fetch technicians for this service with booking counts
$stmt = $pdo->prepare("
    SELECT t.*,
        (SELECT COUNT(*) FROM bookings b WHERE b.technician_id = t.id AND b.status NOT IN ('Cancelled','Completed')) as active_jobs,
        (SELECT COUNT(*) FROM bookings b WHERE b.technician_id = t.id AND b.status = 'Completed') as completed_jobs
    FROM technicians t
    WHERE t.service_id = ?
      AND t.status != 'offline'
      AND t.deleted_at IS NULL
    ORDER BY t.rating DESC, t.total_jobs_completed DESC
");
$stmt->execute([$service_id]);
$techs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<style>
.tech-hero {
    background: linear-gradient(135deg, var(--primary-color) 0%, #1e3a8a 100%);
    color: white;
    padding: 60px 20px 40px;
    text-align: center;
}
.tech-hero h1 { font-size: 2rem; font-weight: 800; margin: 0 0 8px 0; }
.tech-hero p  { opacity: 0.8; font-size: 1rem; margin: 0; }
.tech-hero .breadcrumb { font-size: 0.85rem; opacity: 0.6; margin-bottom: 16px; }

.tech-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 28px;
    max-width: 1100px;
    margin: 50px auto;
    padding: 0 20px;
}

.tech-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #f1f5f9;
}
.tech-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.14);
}

.tech-card-header {
    position: relative;
    background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);
    padding: 36px 24px 16px;
    text-align: center;
}
.tech-avatar {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    margin: 0 auto 12px;
    display: block;
}
.tech-avatar-placeholder {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    font-size: 2rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    border: 4px solid white;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}
.tech-name { font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0 0 4px 0; }
.tech-specialty { font-size: 0.8rem; color: var(--accent-color); font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }

.status-dot {
    position: absolute;
    top: 16px;
    right: 16px;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
}
.status-available { background: rgba(16,185,129,0.1); color: #10B981; }
.status-busy { background: rgba(245,158,11,0.1); color: #F59E0B; }

.tech-stats {
    display: flex;
    justify-content: space-around;
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbff;
}
.stat-item { text-align: center; }
.stat-val { font-size: 1.2rem; font-weight: 800; color: var(--text-main); }
.stat-lbl { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }

.tech-card-body { padding: 20px 24px; }
.tech-info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 10px;
}
.tech-info-row i { width: 18px; color: var(--primary-color); }

.rating-stars { color: #F59E0B; font-size: 0.95rem; }

.select-btn {
    display: block;
    text-align: center;
    background: var(--primary-color);
    color: white;
    padding: 13px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.2s;
    margin-top: 16px;
}
.select-btn:hover { background: #1d4ed8; transform: translateY(-1px); }

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--text-muted);
    max-width: 500px;
    margin: 0 auto;
}
.empty-state i { font-size: 4rem; opacity: 0.3; margin-bottom: 20px; }
</style>

<!-- Hero -->
<div class="tech-hero">
    <p class="breadcrumb">
        <a href="services.php" style="color: white; text-decoration: none; opacity: 0.7;">Services</a>
        &nbsp;›&nbsp; <?php echo htmlspecialchars($service['title']); ?>
    </p>
    <h1>Choose Your <?php echo htmlspecialchars($service['title']); ?></h1>
    <p>Select a verified professional to continue booking. Each provider has independent availability.</p>
</div>

<!-- Grid -->
<div class="tech-grid">

<?php if (empty($techs)): ?>
    <div class="empty-state" style="grid-column: 1/-1;">
        <i class="fa-solid fa-helmet-safety"></i>
        <h3 style="font-size: 1.3rem; color: var(--text-main); margin-bottom: 8px;">No providers available right now</h3>
        <p>All technicians for this service are currently offline. Please check back soon.</p>
        <a href="services.php" class="select-btn" style="display: inline-block; width: auto; padding: 12px 28px; margin-top: 20px;">← Back to Services</a>
    </div>
<?php else: ?>
    <?php foreach ($techs as $t): ?>
    <div class="tech-card">

        <!-- Card Header -->
        <div class="tech-card-header">
            <span class="status-dot <?= $t['status'] === 'available' ? 'status-available' : 'status-busy' ?>">
                <?= $t['status'] === 'available' ? '● Available' : '● Busy' ?>
            </span>

            <?php
            $hasPhoto = !empty($t['photo']) && file_exists(__DIR__ . '/' . $t['photo']);
            if ($hasPhoto): ?>
                <img src="<?= htmlspecialchars($t['photo']) ?>" alt="<?= htmlspecialchars($t['name']) ?>" class="tech-avatar">
            <?php else: ?>
                <!-- Random portrait placeholder -->
                <img src="https://randomuser.me/api/portraits/men/<?php echo ($t['id'] % 90) + 1; ?>.jpg" alt="<?= htmlspecialchars($t['name']) ?>" class="tech-avatar">
            <?php endif; ?>

            <p class="tech-name"><?= htmlspecialchars($t['name']) ?></p>
            <p class="tech-specialty"><?= htmlspecialchars($t['specialty'] ?: 'Service Professional') ?></p>
        </div>

        <!-- Stats Bar -->
        <div class="tech-stats">
            <div class="stat-item">
                <div class="stat-val"><?= number_format((float)($t['rating'] ?? 4.5), 1) ?></div>
                <div class="stat-lbl">Rating</div>
            </div>
            <div class="stat-item">
                <div class="stat-val"><?= (int)($t['total_jobs_completed'] ?? $t['completed_jobs'] ?? 0) ?></div>
                <div class="stat-lbl">Jobs Done</div>
            </div>
            <div class="stat-item">
                <div class="stat-val"><?= (int)($t['experience'] ?? 0) ?>yr</div>
                <div class="stat-lbl">Experience</div>
            </div>
        </div>

        <!-- Body -->
        <div class="tech-card-body">
            <!-- Rating stars -->
            <div class="tech-info-row">
                <span class="rating-stars">
                    <?php
                    $r = round((float)($t['rating'] ?? 4.5));
                    for ($i = 1; $i <= 5; $i++) echo $i <= $r ? '★' : '☆';
                    ?>
                </span>
                <span style="font-weight: 600; color: var(--text-main);">
                    <?= number_format((float)($t['rating'] ?? 4.5), 1) ?>
                </span>
                <span style="color: var(--text-muted);">(<?= (int)($t['total_reviews'] ?? 0) ?> reviews)</span>
            </div>

            <!-- Contact masked -->
            <div class="tech-info-row">
                <i class="fa-solid fa-phone"></i>
                <?php
                $phone = $t['phone'] ?? '';
                echo $phone ? substr($phone, 0, 5) . '*****' : 'Contact on booking';
                ?>
            </div>

            <!-- Skills -->
            <?php if (!empty($t['skills'])): ?>
            <div class="tech-info-row" style="flex-wrap: wrap; gap: 6px;">
                <i class="fa-solid fa-wrench" style="align-self: flex-start; margin-top: 2px;"></i>
                <span><?= htmlspecialchars($t['skills']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Select Button -->
            <a class="select-btn" href="booking.php?service_id=<?= (int)$service_id ?>&tech_id=<?= (int)$t['id'] ?>">
                <i class="fa-solid fa-calendar-check" style="margin-right: 6px;"></i> Select & Book
            </a>
        </div>

    </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>