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
<section class="hero" style="background: radial-gradient(ellipse at 70% 50%, #EBF0FB 0%, #FFFFFF 70%); color: var(--text-main); padding: 120px 0 100px 0;">
    <div class="container" style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 60px; align-items: center; max-width: 1100px; margin: 0 auto;">
        <div class="hero-content">
            <span style="display: inline-block; background: var(--primary-light); color: var(--primary-color); padding: 6px 16px; border-radius: var(--border-radius-pill); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 20px;">
                <i class="fa-solid fa-star" style="margin-right: 6px;"></i> Verified Professionals
            </span>
            <h1 style="font-size: 3.5rem; line-height: 1.1; margin-bottom: 20px; font-weight: 800; color: var(--text-main);">
                Home services,<br>on <span style="position: relative; z-index: 1;">demand.<span style="position: absolute; bottom: 8px; left: 0; width: 100%; height: 12px; background: rgba(244, 162, 39, 0.3); z-index: -1;"></span></span>
            </h1>
            <p style="font-size: 1.15rem; color: var(--text-muted); margin-bottom: 40px; max-width: 500px;">
                Experience premium home maintenance with our expert team. Fast, reliable, and transparent pricing.
            </p>
            
            <div class="hero-search" style="background: white; border: 1.5px solid var(--border-color); border-radius: var(--border-radius-pill); padding: 6px; display: flex; align-items: center; max-width: 600px; box-shadow: var(--shadow-md);">
                <form action="services.php" method="GET" style="display: flex; width: 100%; margin: 0; align-items: center;">
                    <i class="fa-solid fa-magnifying-glass" style="margin-left: 20px; color: var(--text-light);"></i>
                    <input type="text" name="search" placeholder="What service do you need?" style="flex: 1; border: none; padding: 14px 16px; font-size: 1rem; outline: none; background: transparent;">
                    <button type="submit" class="btn-primary" style="padding: 12px 28px;">Search</button>
                </form>
            </div>
        </div>
        
        <div class="hero-image" style="position: relative; display: flex; justify-content: center;">
            <div style="position: relative; width: 100%; max-width: 400px; overflow: hidden; border-radius: 24px; box-shadow: var(--shadow-lg);">
                <div class="hero-carousel" id="heroCarousel" style="display: flex; transition: transform 0.8s ease-in-out; width: 100%;">
                    <div class="carousel-slide" style="min-width: 100%;">
                        <img src="https://images.unsplash.com/photo-1581578731548-c64695cc6952?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Plumbing Service" style="width: 100%; display: block;">
                    </div>
                    <div class="carousel-slide" style="min-width: 100%;">
                        <img src="https://plus.unsplash.com/premium_photo-1664303228186-a61e7dc91597?auto=format&fit=crop&w=800&q=80" alt="Electrical Service" style="width: 100%; display: block;">
                    </div>
                    <div class="carousel-slide" style="min-width: 100%;">
                        <img src="https://images.unsplash.com/photo-1504148455328-c376907d081c?auto=format&fit=crop&w=800&q=80" alt="Home Repair" style="width: 100%; display: block;">
                    </div>
                </div>

                <!-- Carousel Controls -->
                <button onclick="prevSlide()" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.8); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-main); z-index: 10; font-size: 0.8rem; box-shadow: var(--shadow-sm);">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button onclick="nextSlide()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.8); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-main); z-index: 10; font-size: 0.8rem; box-shadow: var(--shadow-sm);">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
                
                <!-- Floating Widget 1 -->
                <div style="position: absolute; top: 10px; right: 10px; background: white; padding: 10px 16px; border-radius: 12px; box-shadow: var(--shadow-md); display: flex; align-items: center; gap: 10px; z-index: 11;">
                    <div style="width: 32px; height: 32px; background: #E8F0FE; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-size: 0.75rem;">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.6rem; color: var(--text-muted); font-weight: 600;">Satisfaction</div>
                        <div style="font-size: 0.75rem; font-weight: 700;">100% Guaranteed</div>
                    </div>
                </div>

                <!-- Floating Widget 2 -->
                <div style="position: absolute; bottom: 10px; left: 10px; background: white; padding: 10px 16px; border-radius: 12px; box-shadow: var(--shadow-md); display: flex; align-items: center; gap: 10px; z-index: 11;">
                    <div style="width: 32px; height: 32px; background: #FFF7ED; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--accent-color); font-size: 0.75rem;">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.6rem; color: var(--text-muted); font-weight: 600;">Top Rated</div>
                        <div style="font-size: 0.75rem; font-weight: 700;">4.9/5 Average</div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            let currentSlide = 0;
            const carousel = document.getElementById('heroCarousel');
            const totalSlides = 3;
            let autoPlayInterval;

            function updateCarousel() {
                carousel.style.transform = `translateX(-${currentSlide * 100}%)`;
            }

            function nextSlide() {
                currentSlide = (currentSlide + 1) % totalSlides;
                updateCarousel();
                resetAutoPlay();
            }

            function prevSlide() {
                currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                updateCarousel();
                resetAutoPlay();
            }

            function startAutoPlay() {
                autoPlayInterval = setInterval(() => {
                    nextSlide();
                }, 3000);
            }

            function resetAutoPlay() {
                clearInterval(autoPlayInterval);
                startAutoPlay();
            }

            // Start autoplay on load
            window.addEventListener('load', startAutoPlay);
        </script>
    </div>
</section>

<!-- 2. Quick Category Navigation -->
<section class="category-nav" style="background: var(--bg-alt); padding: 60px 0; border-bottom: 1px solid var(--border-color);">
    <div class="container">
        <h2 class="section-title" style="text-align: center; margin-bottom: 40px;">What are you looking for?</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 24px;">
            <a href="services.php?category=Electrical" class="cat-item-new" style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; text-align: center; text-decoration: none; transition: all 0.3s ease; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 12px;"><i class="fa-solid fa-bolt"></i></div>
                <span style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">Electrician</span>
            </a>
            <a href="services.php?category=Plumbing" class="cat-item-new" style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; text-align: center; text-decoration: none; transition: all 0.3s ease; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 12px;"><i class="fa-solid fa-faucet-drip"></i></div>
                <span style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">Plumber</span>
            </a>
            <a href="services.php?category=Gas" class="cat-item-new" style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; text-align: center; text-decoration: none; transition: all 0.3s ease; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 12px;"><i class="fa-solid fa-fire-burner"></i></div>
                <span style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">Gas Repair</span>
            </a>
            <a href="services.php?category=AC" class="cat-item-new" style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; text-align: center; text-decoration: none; transition: all 0.3s ease; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 12px;"><i class="fa-solid fa-snowflake"></i></div>
                <span style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">AC Repair</span>
            </a>
            <a href="services.php?category=Cleaning" class="cat-item-new" style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; text-align: center; text-decoration: none; transition: all 0.3s ease; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 12px;"><i class="fa-solid fa-broom"></i></div>
                <span style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">Cleaning</span>
            </a>
            <a href="services.php" class="cat-item-new" style="background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; text-align: center; text-decoration: none; transition: all 0.3s ease; box-shadow: var(--shadow-sm);">
                <div style="font-size: 2rem; color: var(--text-light); margin-bottom: 12px;"><i class="fa-solid fa-ellipsis"></i></div>
                <span style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">View All</span>
            </a>
        </div>
    </div>
</section>

<!-- 2c. How It Works -->
<section style="background: var(--primary-color); padding: 80px 0; color: white;">
    <div class="container">
        <h2 style="text-align: center; color: white; margin-bottom: 60px; font-weight: 800; font-size: 2.2rem;">How QuickFix Works</h2>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 40px; position: relative;">
            <!-- Connector Dashed Line (Desktop) -->
            <div style="position: absolute; top: 40px; left: 10%; width: 80%; height: 2px; border-top: 2px dashed rgba(255,255,255,0.3); z-index: 0; display: block;" class="desktop-only"></div>
            
            <!-- Step 1 -->
            <div style="flex: 1; text-align: center; position: relative; z-index: 1;">
                <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto; font-size: 1.5rem; font-weight: 800; border: 2px solid white;">1</div>
                <h3 style="color: white; margin-bottom: 12px; font-size: 1.25rem;">Choose Service</h3>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem;">Select from our range of verified home services.</p>
            </div>
            
            <!-- Step 2 -->
            <div style="flex: 1; text-align: center; position: relative; z-index: 1;">
                <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto; font-size: 1.5rem; font-weight: 800; border: 2px solid white;">2</div>
                <h3 style="color: white; margin-bottom: 12px; font-size: 1.25rem;">Book Professional</h3>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem;">Pick a time slot that works best for you.</p>
            </div>
            
            <!-- Step 3 -->
            <div style="flex: 1; text-align: center; position: relative; z-index: 1;">
                <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px auto; font-size: 1.5rem; font-weight: 800; border: 2px solid white;">3</div>
                <h3 style="color: white; margin-bottom: 12px; font-size: 1.25rem;">Get it Fixed</h3>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem;">Our expert arrives and fixes everything perfectly.</p>
            </div>
        </div>
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
                        
                        <a class="book-btn" href="booking.php?service_id=<?php echo (int)$service['id']; ?>" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
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
