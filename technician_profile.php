<?php
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tech_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tech_id <= 0) {
    header('Location: index.php');
    exit();
}

$tech = null;
$reviews = [];

try {
    $stmt = $pdo->prepare("
        SELECT t.*, s.title AS service_name
        FROM technicians t
        LEFT JOIN services s ON t.service_id = s.id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->execute([$tech_id]);
    $tech = $stmt->fetch();
} catch (PDOException $e) {
}

if (!$tech) {
    header('Location: index.php');
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.name AS user_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.technician_id = ?
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$tech_id]);
    $reviews = $stmt->fetchAll();
} catch (PDOException $e) {
    $reviews = [];
}

include 'includes/header.php';
?>

<section class="container" style="padding: 60px 20px;">
    <div style="max-width: 900px; margin: 0 auto;">
        <div class="service-card" style="padding: 28px; border-radius: var(--border-radius-md);">
            <div style="display: flex; gap: 18px; align-items: center; flex-wrap: wrap;">
                <div style="width: 96px; height: 96px; border-radius: 50%; overflow: hidden; background: #E5E5E5; border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-weight: 900; color: var(--text-muted); font-size: 2rem;">
                    <?php if(!empty($tech['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($tech['photo']); ?>" alt="Technician" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr((string)$tech['name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>

                <div style="flex: 1; min-width: 240px;">
                    <h1 style="margin: 0 0 6px 0; font-size: 2rem; font-weight: 900;"><?php echo htmlspecialchars($tech['name']); ?></h1>
                    <div style="color: var(--text-muted); font-weight: 600;">
                        <?php echo htmlspecialchars($tech['service_name'] ?? 'Technician'); ?>
                        <?php if(!empty($tech['experience'])): ?>
                            • <?php echo (int)$tech['experience']; ?> yrs experience
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 10px; display: flex; gap: 14px; flex-wrap: wrap; align-items: center;">
                        <span style="font-weight: 800;"><i class="fa-solid fa-star" style="color:#F59E0B; margin-right: 6px;"></i><?php echo number_format((float)($tech['rating'] ?? 0), 2); ?></span>
                        <span style="color: var(--text-muted);"><?php echo (int)($tech['total_reviews'] ?? 0); ?> reviews</span>
                        <span style="color: var(--text-muted);"><?php echo (int)($tech['total_jobs_completed'] ?? 0); ?> jobs completed</span>
                        <span style="color: var(--text-muted);">Status: <strong><?php echo htmlspecialchars($tech['status']); ?></strong></span>
                    </div>
                </div>
            </div>

            <?php if(!empty($tech['skills'])): ?>
                <div style="margin-top: 18px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px;">
                    <div style="font-weight: 800; margin-bottom: 8px;">Skills</div>
                    <div style="color: var(--text-muted); line-height: 1.6;"><?php echo htmlspecialchars($tech['skills']); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 22px;">
            <h2 style="font-size: 1.4rem; font-weight: 900; margin: 0 0 12px 0;">Reviews</h2>

            <?php if(empty($reviews)): ?>
                <div class="service-card" style="padding: 26px; border-radius: var(--border-radius-md); color: var(--text-muted);">
                    No reviews yet.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach($reviews as $r): ?>
                        <div class="service-card" style="padding: 18px; border-radius: var(--border-radius-md);">
                            <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                                <div style="font-weight: 800;"><?php echo htmlspecialchars($r['user_name']); ?></div>
                                <div style="color: var(--text-muted); font-size: 0.9rem;"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                            </div>
                            <div style="margin-top: 8px; font-weight: 800;">
                                <?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?>
                            </div>
                            <?php if(!empty($r['review_text'])): ?>
                                <div style="margin-top: 8px; color: var(--text-muted); line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($r['review_text'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 20px;">
            <a href="status.php" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); text-decoration: none;">
                Back to bookings
            </a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

