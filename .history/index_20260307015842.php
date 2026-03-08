<?php
// index.php is the Home Page (the very first page a user sees).

// We need to establish a connection to our database to fetch any dynamic records (like services)
// We use 'require_once' which means: "Go find this file and run its code here. If you can't find it, show a FATAL error and STOP everything."
require_once 'config/db.php';

// Now we want to load our global header (which includes the navbar and all our <head> CSS tags)
// We use 'include' here. 'include' is a bit softer than require. If the file is missing, it shows a warning but tries to continue running the script.
include 'includes/header.php';

// --- PHP DATA FETCHING ---
// We want to fetch 4 services from our database to show as "Featured Services" on the homepage.
// First, we prepare a simple SQL query. 'LIMIT 4' ensures it only grabs up to 4 rows from the table.
$stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 AND deleted_at IS NULL LIMIT 4");

// We fetch ALL the results found by the query and store them in the $services array variable.
// Because we set PDO::FETCH_ASSOC in our db.php, this array will look like: 
// [ 0 => ['id'=>1, 'title'=>'Electrician', ...], 1 => ['id'=>2, 'title'=>'Plumber', ...] ]
$services = $stmt->fetchAll();

// Most booked services (top 3)
$top_services = [];
try {
    $topStmt = $pdo->query("
        SELECT s.*, COUNT(b.id) AS total_bookings
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        WHERE s.is_active = 1 AND s.deleted_at IS NULL
        GROUP BY b.service_id
        ORDER BY total_bookings DESC
        LIMIT 3
    ");
    $top_services = $topStmt->fetchAll();
} catch (PDOException $e) {
    $top_services = [];
}
if (empty($top_services)) {
    try {
        $top_services = $pdo->query("SELECT *, 0 AS total_bookings FROM services WHERE is_active = 1 AND deleted_at IS NULL LIMIT 3")->fetchAll();
    } catch (PDOException $e) {
        $top_services = [];
    }
}
?>

<!-- ============================================== -->
<!-- HTML RENDER SECTION                            -->
<!-- ============================================== -->

<!-- 1. The Premium Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Home services,<br>on demand.</h1>
        <p>Verified professionals at your doorstep. Fast, reliable, and transparent pricing.</p>
        
        <div class="hero-search">
            <form action="services.php" method="GET" style="display: flex; width: 100%; margin: 0;">
                <input type="text" name="search" placeholder="Search for a service... (e.g. Electrician, AC Repair)" style="flex: 1;">
                <button type="submit" class="btn-primary">Search</button>
            </form>
        </div>
    </div>
</section>

<!-- 2. Quick Category Navigation -->
<section class="category-nav">
    <a href="services.php?category=Electrical" class="cat-item">
        <div class="cat-icon-box"><i class="fa-solid fa-bolt"></i></div>
        <span class="cat-name">Electrician</span>
    </a>
    <a href="services.php?category=Plumbing" class="cat-item">
        <div class="cat-icon-box"><i class="fa-solid fa-faucet-drip"></i></div>
        <span class="cat-name">Plumber</span>
    </a>
    <a href="services.php?category=Gas" class="cat-item">
        <div class="cat-icon-box"><i class="fa-solid fa-fire-burner"></i></div>
        <span class="cat-name">Gas Specialist</span>
    </a>
    <a href="services.php?category=Locksmith" class="cat-item">
        <div class="cat-icon-box"><i class="fa-solid fa-key"></i></div>
        <span class="cat-name">Lock Repair</span>
    </a>
    <a href="services.php" class="cat-item">
        <div class="cat-icon-box" style="color: var(--text-light);"><i class="fa-solid fa-ellipsis"></i></div>
        <span class="cat-name">View All</span>
    </a>
</section>

<!-- 2b. What are you looking for? -->
<section class="container" style="padding-top: 40px;">
    <h2 class="section-title">What are you looking for?</h2>
    <p style="color: var(--text-muted); margin-top: -10px; margin-bottom: 22px;">Choose a category to book instantly.</p>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
        <a href="services.php?category=Electrical" class="service-card" style="padding: 18px; border-radius: var(--border-radius-md); display: flex; gap: 12px; align-items: center;">
            <div class="cat-icon-box"><i class="fa-solid fa-bolt"></i></div>
            <div style="font-weight: 800;">Electrician</div>
        </a>
        <a href="services.php?category=Plumbing" class="service-card" style="padding: 18px; border-radius: var(--border-radius-md); display: flex; gap: 12px; align-items: center;">
            <div class="cat-icon-box"><i class="fa-solid fa-faucet-drip"></i></div>
            <div style="font-weight: 800;">Plumber</div>
        </a>
        <a href="services.php?category=Gas" class="service-card" style="padding: 18px; border-radius: var(--border-radius-md); display: flex; gap: 12px; align-items: center;">
            <div class="cat-icon-box"><i class="fa-solid fa-fire-burner"></i></div>
            <div style="font-weight: 800;">Gas Repair</div>
        </a>
        <a href="services.php?category=AC" class="service-card" style="padding: 18px; border-radius: var(--border-radius-md); display: flex; gap: 12px; align-items: center;">
            <div class="cat-icon-box"><i class="fa-solid fa-snowflake"></i></div>
            <div style="font-weight: 800;">AC Repair</div>
        </a>
        <a href="services.php?category=Cleaning" class="service-card" style="padding: 18px; border-radius: var(--border-radius-md); display: flex; gap: 12px; align-items: center;">
            <div style="font-weight: 800;">Cleaning</div>
        </a>
    </div>
</section>

<!-- 3. Services Display Section -->
<section class="container">
    <h2 class="section-title">Verified professionals</h2>

    <div class="services-grid">
        
        <?php foreach ($services as $service): ?>
            
            <div class="service-card">
                
                <!-- NEW: Premium Image Header -->
                <div class="service-img-container">
                    <!-- Use a placeholder image if it doesn't exist, otherwise use the database URL -->
                    <img src="<?php echo htmlspecialchars($service['image_url'] ?? 'https://images.unsplash.com/photo-1581092918056-0c4c3acd37be?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'); ?>" alt="<?php echo htmlspecialchars($service['title']); ?>" class="service-img">
                </div>
                
                <div class="service-content">
                    <!-- NEW: Star Rating -->
                    <div class="service-rating">
                        <i class="fa-solid fa-star"></i>
                        <span><?php echo number_format($service['rating'] ?? 4.5, 1); ?></span>
                    </div>

                    <h3 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h3>
                    
                    <p class="service-desc"><?php echo htmlspecialchars($service['description']); ?></p>
                    
                    <div class="service-footer">
                        <div class="service-price">
                            ₹<?php echo number_format($service['price'], 2); ?>
                        </div>
                       <a href="technicians.php?service_id=<?php echo $row['id']; ?>" class="book-btn">
Book Now
</a>
                    </div>
                </div>

            </div>
            
        <?php endforeach; ?>
        
    </div>

    <!-- Centered bottom button to view everything -->
    <div style="text-align: center; padding: 20px 0 80px 0;">
        <a href="services.php" class="btn-outline" style="min-width: 250px;">Explore all services</a>
    </div>

</section>

<!-- 4. Most Booked Services -->
<section class="container" style="padding-bottom: 30px;">
    <h2 class="section-title">Most Booked Services</h2>
    <div class="services-grid">
        <?php foreach ($top_services as $service): ?>
            <div class="service-card">
                <div class="service-img-container">
                    <img src="<?php echo htmlspecialchars($service['image_url'] ?? 'https://images.unsplash.com/photo-1581092918056-0c4c3acd37be?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'); ?>" alt="<?php echo htmlspecialchars($service['title']); ?>" class="service-img">
                </div>
                <div class="service-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                        <h3 class="service-title" style="margin: 0;"><?php echo htmlspecialchars($service['title']); ?></h3>
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700;">
                            <?php echo (int)($service['total_bookings'] ?? 0); ?> booked
                        </span>
                    </div>
                    <p class="service-desc"><?php echo htmlspecialchars($service['description']); ?></p>
                    <div class="service-footer">
                        <div class="service-price">₹<?php echo number_format($service['price'], 2); ?></div>
                        <a class="book-btn" href="booking.php?service_id=<?php echo (int)$service['id']; ?>" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                            Book Now
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- 5. Offers & Discounts -->
<section class="container" style="padding-bottom: 80px;">
    <h2 class="section-title">Offers & Discounts</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
        <div class="service-card" style="padding: 22px; border-radius: var(--border-radius-md);">
            <div style="font-weight: 900; font-size: 1.15rem; margin-bottom: 8px;">Flat ₹200 Off</div>
            <div style="color: var(--text-muted); margin-bottom: 14px;">On first booking above ₹999.</div>
            <div style="font-weight: 800; color: var(--accent-color);">Use: FIRST200</div>
        </div>
        <div class="service-card" style="padding: 22px; border-radius: var(--border-radius-md);">
            <div style="font-weight: 900; font-size: 1.15rem; margin-bottom: 8px;">10% Off</div>
            <div style="color: var(--text-muted); margin-bottom: 14px;">On AC repair this week.</div>
            <div style="font-weight: 800; color: var(--accent-color);">Use: AC10</div>
        </div>
        <div class="service-card" style="padding: 22px; border-radius: var(--border-radius-md);">
            <div style="font-weight: 900; font-size: 1.15rem; margin-bottom: 8px;">Free Inspection</div>
            <div style="color: var(--text-muted); margin-bottom: 14px;">With any electrician visit.</div>
            <div style="font-weight: 800; color: var(--accent-color);">Limited time</div>
        </div>
    </div>
</section>

<?php
// Finally, load the global footer (which closes our HTML tags properly)
include 'includes/footer.php';
?>
