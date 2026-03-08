<?php
// admin/includes/header.php - Dedicated header for the Admin Panel
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CRITICAL SECURITY: Ensure ONLY admins can view these pages
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If they aren't logged in, or they are logged in but NOT an admin...
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../../includes/csrf.php';

// Session Timeout (30 mins)
$timeout_duration = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?msg=timeout');
    exit();
}
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickFix - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main styles -->
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Dark Mode Variables using data-theme attribute */
        :root[data-theme="dark"] {
            --bg-color: #0F172A; /* Slate 900 */
            --card-bg: #1E293B;  /* Slate 800 */
            --text-main: #F8FAFC; /* Slate 50 */
            --text-muted: #94A3B8; /* Slate 400 */
            --border-color: #334155; /* Slate 700 */
            --primary-color: #020617; /* Slate 950 for sidebar */
        }

        body {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-main);
            transition: background-color 0.3s, color 0.3s;
            margin: 0;
            overflow: hidden; /* Hide main body scroll, handle scroll in inner content container */
        }

        body.collapsed-sidebar {
            grid-template-columns: 80px 1fr;
        }

        /* SIDEBAR STYLES */
        .admin-sidebar {
            background-color: var(--primary-color);
            color: white;
            display: flex;
            flex-direction: column;
            height: 100vh;
            transition: width 0.3s;
            z-index: 100;
        }

        .sidebar-header {
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            height: 76px;
            box-sizing: border-box;
        }

        .sidebar-header .logo-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            white-space: nowrap;
        }

        body.collapsed-sidebar .sidebar-header .logo-text {
            display: none;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 4px;
            transition: color 0.2s;
        }
        .toggle-btn:hover { color: white; }

        body.collapsed-sidebar .sidebar-header .toggle-btn {
            margin: 0 auto;
        }

        .sidebar-menu {
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-grow: 1;
            overflow-y: auto;
        }

        .sidebar-menu a {
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 16px;
            color: #CBD5E1;
            transition: all 0.2s;
            text-decoration: none;
            white-space: nowrap;
            font-weight: 500;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar-menu a i {
            font-size: 1.25rem;
            min-width: 24px;
            text-align: center;
        }

        body.collapsed-sidebar .sidebar-menu a span {
            display: none;
        }
        
        body.collapsed-sidebar .sidebar-menu a {
            justify-content: center;
            padding: 12px 0;
            gap: 0;
        }

        /* MAIN AREA STYLES */
        .admin-main {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .top-navbar {
            height: 76px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-sizing: border-box;
            box-shadow: var(--shadow-sm);
            z-index: 10;
        }

        .main-content-scrollable {
            padding: 32px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .icon-btn {
            background: rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.2s;
        }

        :root[data-theme="dark"] .icon-btn {
            background: rgba(255,255,255,0.05);
        }

        .icon-btn:hover {
            color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .badge-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 10px;
            height: 10px;
            background-color: var(--danger-color);
            border-radius: 50%;
            border: 2px solid var(--card-bg);
        }

        /* Dashboard UI tweaks to support Theme */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            padding: 24px;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border-color);
        }

        .chart-box {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
        }
        
        .admin-table th, .admin-table td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }
        
        .admin-table th {
            background-color: rgba(0,0,0,0.02);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
        }

        :root[data-theme="dark"] .admin-table th {
            background-color: rgba(255,255,255,0.02);
        }
    </style>
</head>
<body>

    <!-- Left Sidebar Menu -->
    <aside class="admin-sidebar" id="sidebar">
        
        <div class="sidebar-header">
            <a href="index.php" style="text-decoration: none;" class="logo-text">
                Quick<span style="color: var(--accent-color);">Fix</span>
            </a>
            <button class="toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
        
        <!-- Menu Links -->
        <div class="sidebar-menu">
            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
            
            <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span>
            </a>
            <a href="bookings.php" class="<?php echo $current_page == 'bookings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-check"></i> <span>Bookings</span>
            </a>
            <a href="services.php" class="<?php echo $current_page == 'services.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-tools"></i> <span>Services</span>
            </a>
            <a href="service-areas.php" class="<?php echo $current_page == 'service-areas.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-location-dot"></i> <span>Service Areas</span>
            </a>
            <a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i> <span>Users</span>
            </a>
            <a href="technicians.php" class="<?php echo $current_page == 'technicians.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-helmet-safety"></i> <span>Technicians</span>
            </a>
            <a href="reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-invoice"></i> <span>Reports</span>
            </a>
            <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i> <span>Settings</span>
            </a>
            
            <div style="flex-grow: 1;"></div>
            
            <a href="../index.php" style="color: var(--accent-color);">
                <i class="fa-solid fa-globe"></i> <span>Public Site</span>
            </a>
            <a href="logout.php" style="color: #FCA5A5;">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area Starts Here -->
    <main class="admin-main">
        
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div style="font-weight: 600; font-size: 1.1rem;">
                <span id="greeting" style="color: var(--text-muted);">Welcome back, </span>
                <span style="color: var(--text-main);"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
            </div>
            
            <div style="display: flex; gap: 16px; align-items: center;">
                <!-- Dark Mode Toggle -->
                <button class="icon-btn" onclick="toggleTheme()" title="Toggle Dark Mode">
                    <i class="fa-solid fa-moon" id="theme-icon"></i>
                </button>
                
                <!-- Notifications -->
                <a href="notifications.php" class="icon-btn" title="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <?php 
                        // Quick check for unread notifications (will build full logic later)
                        $unread_check = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = FALSE")->fetchColumn();
                        if($unread_check > 0): 
                    ?>
                        <div class="badge-dot"></div>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <div class="main-content-scrollable">
