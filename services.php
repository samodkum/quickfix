<?php
// services.php displays all available emergency services

// 1. Database connection is required
require_once 'config/db.php';

// 2. Load the header
include 'includes/header.php';

// 3. Fetch ALL services from the database with filtering
$where_clauses = [];
$params = [];

if (!empty($_GET['category'])) {
    $where_clauses[] = "category = ?";
    $params[] = $_GET['category'];
}

if (!empty($_GET['search'])) {
    $where_clauses[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_SESSION['location'])) {
    $where_clauses[] = "(location = ? OR location = 'All Locations')";
    $params[] = $_SESSION['location'];
}

$sql = "SELECT * FROM services WHERE is_active = 1 AND deleted_at IS NULL";
if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY category ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Dynamic title
$page_title = "All Services";
if (!empty($_GET['category'])) {
    $page_title = htmlspecialchars($_GET['category']) . " Services";
} elseif (!empty($_GET['search'])) {
    $page_title = "Search Results for '" . htmlspecialchars($_GET['search']) . "'";
}

?>

<!-- Main Services Section -->
<section class="container" style="padding: 60px 20px;">
    
    <div style="text-align: center; margin-bottom: 50px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 15px;"><?php echo $page_title; ?></h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Select a service below to book an immediate professional visit. We guarantee fast response times.</p>
    </div>

    <!-- Reusing the services-grid from style.css for consistent design -->
    <div class="services-grid">
        
        <?php foreach ($services as $service): ?>
            <div class="service-card">
                
                <!-- Premium Image Header -->
                <div class="service-img-container">
                    <img src="<?php echo htmlspecialchars($service['image_url'] ?? 'https://images.unsplash.com/photo-1581092918056-0c4c3acd37be?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'); ?>" alt="<?php echo htmlspecialchars($service['title']); ?>" class="service-img">
                </div>
                
                <div class="service-content">
                    <!-- Star Rating -->
                    <div class="service-rating">
                        <i class="fa-solid fa-star"></i>
                        <span><?php echo number_format($service['rating'] ?? 4.5, 1); ?></span>
                        <span style="color: var(--text-light); margin-left: 4px; font-weight: normal; font-size: 0.8rem;">(<?php echo rand(120, 950); ?>)</span>
                    </div>

                    <!-- Display Service Category -->
                    <div style="margin-bottom: 6px; color: var(--text-muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php echo htmlspecialchars($service['category']); ?>
                    </div>
                    
                    <h3 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h3>
                    
                    <p class="service-desc"><?php echo htmlspecialchars($service['description']); ?></p>
                    
                    <div class="service-footer">
                        <div class="service-price">
                            ₹<?php echo number_format($service['price'], 2); ?>
                        </div>
                        
                        <a class="book-btn" href="technicians.php?service_id=<?php echo (int)$service['id']; ?>" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                            Book Now
                        </a>
                    </div>
                </div>
                
            </div>
        <?php endforeach; ?>
        
    </div>
</section>

<?php include 'includes/footer.php'; ?>
