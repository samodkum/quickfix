<?php
// Start the session to manage user login states globally across the app
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle location change if submitted
if (isset($_GET['set_location'])) {
    $_SESSION['location'] = htmlspecialchars($_GET['set_location']);
    // Removed redirect here to handle gracefully or let JS handle, but simple PHP works:
    // We can just set it.
}
$current_location = $_SESSION['location'] ?? '';
?>
<!-- We start the HTML document. This is standard for HTML5. -->
<!DOCTYPE html>
<!-- lang="en" helps search engines and screen readers understand the site language -->
<html lang="en">
<head>
    <!-- meta charset ensures symbols and emojis render correctly -->
    <meta charset="UTF-8">
    <!-- Viewport tag is CRITICAL for responsive design on mobile phones -->
    <!-- It tells the browser "Make the website exactly as wide as the device screen, and don't zoom out automatically" -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickFix - Emergency Home Services</title>
    
    <!-- Link to Google Fonts to fetch the modern 'Inter' font instead of using basic computer fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Link to FontAwesome for modern icons (using a CDN link for quick access) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Finally, link to our custom stylesheet we just created -->
    <!-- Important: The path assumes this file is included from the root folder (like index.php) -->
    <link rel="stylesheet" href="css/style.css">

    <script>
        (function () {
            try {
                const saved = localStorage.getItem('theme');
                if (saved === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body>

    <!-- The main header/navigation bar section -->
    <!-- using HTML5 <header> semantic tag which is good for SEO -->
    <header class="site-header">
        <!-- .container limits width and centers it -->
        <div class="container navbar">
            <!-- Our Logo -->
            <!-- The link to index.php ensures clicking the logo always takes you home -->
            <a href="index.php" class="logo">Quick<span>Fix</span></a>

            <div class="location-selector" style="display: flex; align-items: center; margin-left: 24px; gap: 8px;">
                <i class="fa-solid fa-location-dot" style="color: var(--text-muted);"></i>
                <form action="" method="GET" style="margin: 0;">
                    <!-- Maintain existing query params if any, but simplistic for now -->
                    <select name="set_location" onchange="this.form.submit()" style="border: none; background: transparent; font-weight: 500; font-size: 0.95rem; cursor: pointer; outline: none; color: var(--text-main);">
                        <option value="">All Locations</option>
                        <option value="New Delhi" <?php echo $current_location === 'New Delhi' ? 'selected' : ''; ?>>New Delhi</option>
                        <option value="Mumbai" <?php echo $current_location === 'Mumbai' ? 'selected' : ''; ?>>Mumbai</option>
                        <option value="Bangalore" <?php echo $current_location === 'Bangalore' ? 'selected' : ''; ?>>Bangalore</option>
                    </select>
                </form>
            </div>

            <!-- The Navigation Menu Links -->
            <!-- Using a <nav> tag and <ul> (unordered list) is the standard professional way to build menus -->
            <nav style="margin-left: auto;">
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="services.php">Services</a></li>
                    
                    <!-- PHP logic to show different menus depending on if someone is logged in -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- If a user IS logged in, show 'My Bookings' and 'Logout' -->
                        <li><a href="status.php" title="My Bookings"><i class="fa-solid fa-list-check" style="font-size: 1.2rem;"></i></a></li>
                        <li><a href="profile.php" title="Profile"><i class="fa-regular fa-user" style="font-size: 1.2rem;"></i></a></li>
                        <li><a href="logout.php" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket" style="font-size: 1.2rem; color: var(--danger-color);"></i></a></li>
                    <?php else: ?>
                        <!-- If user is NOT logged in, show 'Login' and 'Sign Up' links -->
                        <li><a href="login.php" style="font-weight: 600;">Login</a></li>
                        <!-- Use our primary blue button style for the main call-to-action (Sign Up) -->
                        <li><a href="register.php" class="btn-primary" style="padding: 10px 20px;">Sign Up</a></li>
                    <?php endif; ?>

                    <li>
                        <button id="themeToggle" type="button" title="Toggle dark mode" style="background: transparent; border: 1px solid var(--border-color); padding: 8px 10px; border-radius: 12px; cursor: pointer; color: var(--text-main);">
                            <i class="fa-solid fa-moon"></i>
                        </button>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <script>
        (function () {
            const btn = document.getElementById('themeToggle');
            if (!btn) return;
            btn.addEventListener('click', function () {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    document.documentElement.removeAttribute('data-theme');
                    try { localStorage.setItem('theme', 'light'); } catch (e) {}
                    btn.innerHTML = '<i class="fa-solid fa-moon"></i>';
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    try { localStorage.setItem('theme', 'dark'); } catch (e) {}
                    btn.innerHTML = '<i class="fa-solid fa-sun"></i>';
                }
            });

            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            btn.innerHTML = isDark ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
        })();
    </script>

    <!-- The <main> tag begins immediately after the header. -->
    <!-- This tag will be closed inside includes/footer.php -->
    <!-- So any specific page content will be sandwiched between header.php and footer.php -->
    <main>
