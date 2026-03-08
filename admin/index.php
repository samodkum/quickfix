<?php
// admin/index.php - The visual dashboard demonstrating Chart.js integration
require_once '../config/db.php';

// Include the secure admin header (which handles session checking)
include 'includes/header.php';

// ==========================================
// 1. DATE FILTER LOGIC
// ==========================================
// Get the first day of the current month and today's date format
$default_start = date('Y-m-01');
$default_end = date('Y-m-d');

$start_date = $_GET['start_date'] ?? $default_start;
$end_date = $_GET['end_date'] ?? $default_end;

// Using prepared statements for security against injection
$date_where = "DATE(created_at) BETWEEN :start_date AND :end_date";
$date_params = ['start_date' => $start_date, 'end_date' => $end_date];

// ==========================================
// 2. FETCH SUMMARY STATISTICS
// ==========================================
// Global stats unaffected by date date
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_services = $pdo->query("SELECT COUNT(*) FROM services WHERE is_active = 1")->fetchColumn();

// Filtered Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) BETWEEN :start_date AND :end_date");
$stmt->execute($date_params);
$total_bookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE (status = 'Requested' OR status = 'Pending') AND DATE(created_at) BETWEEN :start_date AND :end_date");
$stmt->execute($date_params);
$pending_bookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'Completed' AND DATE(created_at) BETWEEN :start_date AND :end_date");
$stmt->execute($date_params);
$completed_bookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE emergency_level = 'High' AND DATE(created_at) BETWEEN :start_date AND :end_date");
$stmt->execute($date_params);
$high_priority = $stmt->fetchColumn();

// Cancellation rate
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'Cancelled' AND DATE(created_at) BETWEEN :start_date AND :end_date");
$stmt->execute($date_params);
$cancelled_bookings = (int)$stmt->fetchColumn();
$cancellation_rate = $total_bookings > 0 ? round(($cancelled_bookings / $total_bookings) * 100, 2) : 0;

// Most booked service in date range
$mostBooked = $pdo->prepare("
    SELECT s.title, COUNT(b.id) AS cnt
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE DATE(b.created_at) BETWEEN :start_date AND :end_date
    GROUP BY b.service_id
    ORDER BY cnt DESC
    LIMIT 1
");
$mostBooked->execute($date_params);
$most_booked_service = $mostBooked->fetch();

// Most active technician in date range
$mostTech = $pdo->prepare("
    SELECT t.name, COUNT(b.id) AS cnt
    FROM bookings b
    JOIN technicians t ON b.technician_id = t.id
    WHERE b.technician_id IS NOT NULL AND DATE(b.created_at) BETWEEN :start_date AND :end_date
    GROUP BY b.technician_id
    ORDER BY cnt DESC
    LIMIT 1
");
try {
    $mostTech->execute($date_params);
    $most_active_tech = $mostTech->fetch();
} catch (PDOException $e) {
    $most_active_tech = null;
}

// Revenue Calculation (Completed & Paid bookings)
// We join with services because the price is on the service table.
$revenueQuery = "
    SELECT SUM(COALESCE(b.total_amount, s.price)) 
    FROM bookings b 
    JOIN services s ON b.service_id = s.id 
    WHERE b.payment_status = 'completed' AND DATE(b.created_at) BETWEEN :start_date AND :end_date
";
$stmt = $pdo->prepare($revenueQuery);
$stmt->execute($date_params);
$revenue = $stmt->fetchColumn() ?: 0; // fallback to 0 if null

// ==========================================
// 3. FETCH GRAPHS
// ==========================================
// Bar Data - Note aliasing and joining with date filter
$barQuery = $pdo->prepare("
    SELECT s.title as label, COUNT(b.id) as value 
    FROM services s 
    LEFT JOIN bookings b ON s.id = b.service_id AND DATE(b.created_at) BETWEEN :start_date AND :end_date 
    GROUP BY s.id 
    ORDER BY value DESC LIMIT 5
");
$barQuery->execute($date_params);
$barData = $barQuery->fetchAll();
$bar_labels = json_encode(array_column($barData, 'label'));
$bar_values = json_encode(array_column($barData, 'value'));

// Pie Data
$pieQuery = $pdo->prepare("SELECT status as label, COUNT(*) as value FROM bookings WHERE DATE(created_at) BETWEEN :start_date AND :end_date GROUP BY status");
$pieQuery->execute($date_params);
$pieData = $pieQuery->fetchAll();
$pie_labels = json_encode(array_column($pieData, 'label'));
$pie_values = json_encode(array_column($pieData, 'value'));

// Line Data: Monthly growth doesn't use the simple date filter because it needs historical context.
$lineQuery = "
    SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as value 
    FROM bookings 
    GROUP BY DATE_FORMAT(created_at, '%b %Y'), YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
    LIMIT 6
";
$lineData = $pdo->query($lineQuery)->fetchAll();
$line_labels = json_encode(array_column($lineData, 'month'));
$line_values = json_encode(array_column($lineData, 'value'));

// ==========================================
// 4. FETCH RECENT ACTIVITY
// ==========================================
$recentActivity = $pdo->query("
    SELECT b.id, u.name as customer, s.title as service, b.status, b.created_at 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    JOIN services s ON b.service_id = s.id 
    ORDER BY b.created_at DESC LIMIT 5
")->fetchAll();

?>

<!-- Header Section with Date Filters -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="margin: 0; font-weight: 800; font-size: 1.8rem;">Overview Dashboard</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Analytics spanning across the date range below.</p>
    </div>
    
    <form method="GET" style="display: flex; gap: 8px; align-items: center; background: var(--card-bg); padding: 8px 16px; border-radius: 50px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control" style="border: none; background: transparent; padding: 4px; box-shadow: none;">
        <span style="color: var(--text-muted);">to</span>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control" style="border: none; background: transparent; padding: 4px; box-shadow: none;">
        <button type="submit" class="btn-primary" style="padding: 6px 16px; border-radius: 50px;">Apply</button>
    </form>
</div>

<!-- ============================================== -->
<!-- UI: TOP STATUS CARDS                           -->
<!-- ============================================== -->
<div class="stat-grid">
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: rgba(59, 130, 246, 0.1); color: #3B82F6;">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div>
            <h3 style="font-size: 1.6rem; margin: 0; font-weight: 800;"><?php echo $total_bookings; ?></h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0; font-weight: 500;">Total Bookings</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10B981;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <h3 style="font-size: 1.6rem; margin: 0; font-weight: 800;"><?php echo $completed_bookings; ?></h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0; font-weight: 500;">Completed</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #F59E0B;">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div>
            <h3 style="font-size: 1.6rem; margin: 0; font-weight: 800;"><?php echo $pending_bookings; ?></h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0; font-weight: 500;">Pending</p>
        </div>
    </div>

    <div class="stat-card" style="border: 1px solid rgba(239, 68, 68, 0.3);">
        <div class="stat-icon" style="background-color: rgba(239, 68, 68, 0.1); color: var(--danger-color);">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
            <h3 style="font-size: 1.6rem; margin: 0; color: var(--danger-color); font-weight: 800;"><?php echo $high_priority; ?></h3>
            <p style="color: var(--danger-color); font-size: 0.85rem; margin: 0; font-weight: 600;">High Priority</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background-color: rgba(239, 68, 68, 0.1); color: var(--danger-color);">
            <i class="fa-solid fa-ban"></i>
        </div>
        <div>
            <h3 style="font-size: 1.6rem; margin: 0; font-weight: 800;"><?php echo $cancellation_rate; ?>%</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0; font-weight: 500;">Cancellation Rate</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background-color: rgba(147, 51, 234, 0.1); color: #9333EA;">
            <i class="fa-solid fa-fire"></i>
        </div>
        <div>
            <h3 style="font-size: 1.05rem; margin: 0; font-weight: 800; line-height: 1.2;">
                <?php echo htmlspecialchars($most_booked_service['title'] ?? '—'); ?>
            </h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 2px 0 0 0; font-weight: 500;">Most Booked Service</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10B981;">
            <i class="fa-solid fa-user-gear"></i>
        </div>
        <div>
            <h3 style="font-size: 1.05rem; margin: 0; font-weight: 800; line-height: 1.2;">
                <?php echo htmlspecialchars($most_active_tech['name'] ?? '—'); ?>
            </h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 2px 0 0 0; font-weight: 500;">Most Active Technician</p>
        </div>
    </div>
    
    <div class="stat-card" style="grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, var(--card-bg), rgba(16, 185, 129, 0.05));">
        <div style="display: flex; gap: 20px; align-items: center;">
            <div class="stat-icon" style="background-color: #10B981; color: white; width: 60px; height: 60px; border-radius: 16px;">
                <i class="fa-solid fa-sack-dollar"></i>
            </div>
            <div>
                <p style="color: var(--text-muted); font-size: 1rem; margin: 0 0 4px 0; font-weight: 600;">Total Revenue</p>
                <h3 style="font-size: 2.5rem; margin: 0; font-weight: 800; color: var(--text-main);">$<?php echo number_format($revenue, 2); ?></h3>
            </div>
        </div>
        <div style="text-align: right; margin-right: 20px;">
             <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Users: <strong><?php echo $total_users; ?></strong></p>
             <p style="color: var(--text-muted); font-size: 0.85rem; margin: 4px 0 0 0;">Services: <strong><?php echo $total_services; ?></strong></p>
        </div>
    </div>
    
</div>

<!-- ============================================== -->
<!-- UI: CHART.JS & RECENT ACTIVITY                 -->
<!-- ============================================== -->

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px; margin-bottom: 32px;">
    
    <!-- Bar Chart Box -->
    <div class="chart-box">
        <h3 style="margin: 0 0 24px 0; font-weight: 700;">Bookings Per Service</h3>
        <canvas id="barChart" height="150"></canvas>
    </div>
    
    <!-- Pie Chart Box -->
    <div class="chart-box">
        <h3 style="margin: 0 0 24px 0; font-weight: 700;">Status Breakdown</h3>
        <canvas id="pieChart" height="200"></canvas>
    </div>
    
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px;">
    
    <!-- Line chart -->
    <div class="chart-box">
        <h3 style="margin: 0 0 24px 0; font-weight: 700;">Monthly Booking Growth</h3>
        <canvas id="lineChart" height="150"></canvas>
    </div>

    <!-- Recent Activity Table -->
    <div class="chart-box" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
        <div style="padding: 24px; border-bottom: 1px solid var(--border-color);">
            <h3 style="margin: 0; font-weight: 700;">Recent Activity</h3>
        </div>
        <div style="flex-grow: 1; overflow-y: auto;">
            <table class="admin-table">
                <tbody>
                    <?php if(empty($recentActivity)): ?>
                        <tr><td style="text-align:center; padding: 24px;">No recent bookings found.</td></tr>
                    <?php else: ?>
                        <?php foreach($recentActivity as $activity): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($activity['customer']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php echo date('M d, H:i a', strtotime($activity['created_at'])); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($activity['service']); ?></td>
                            <td style="text-align: right;">
                                <span style="font-size: 0.8rem; font-weight: 600; padding: 4px 8px; border-radius: 5px; border: 1px solid var(--border-color);
                                <?php 
                                    if($activity['status']==='Completed') echo 'background: rgba(16, 185, 129, 0.1); color: #10B981;';
                                    elseif($activity['status']==='In Progress') echo 'background: rgba(245, 158, 11, 0.1); color: #F59E0B;';
                                    else echo 'background: rgba(59, 130, 246, 0.1); color: #3B82F6;';
                                ?>">
                                    <?php echo htmlspecialchars($activity['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- JAVASCRIPT: INITIALIZING THE GRAPHS            -->
<!-- ============================================== -->

<script>
    // Grab theme colors from CSS variables for Chart styling dynamically based on light/dark mode
    const getChartColor = (varName) => getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
    
    function initCharts() {
        const textColor = getChartColor('--text-muted');
        const gridColor = getChartColor('--border-color');
        
        // Defaults for all charts
        Chart.defaults.color = textColor;
        Chart.defaults.font.family = "'Inter', sans-serif";

        const barLabels = <?php echo $bar_labels; ?>;
        const barValues = <?php echo $bar_values; ?>;
        const pieLabels = <?php echo $pie_labels; ?>;
        const pieValues = <?php echo $pie_values; ?>;
        const lineLabels = <?php echo $line_labels; ?>;
        const lineValues = <?php echo $line_values; ?>;

        // 1. Render Bar Chart
        if(document.getElementById('barChart')) {
            new Chart(document.getElementById('barChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: barLabels,
                    datasets: [{
                        label: 'Bookings',
                        data: barValues,
                        backgroundColor: '#3B82F6',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    scales: { 
                        y: { beginAtZero: true, grid: { color: gridColor }, border: { dash: [4, 4] } },
                        x: { grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

        // 2. Render Doughnut Chart
        if(document.getElementById('pieChart')) {
            new Chart(document.getElementById('pieChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: pieValues,
                        backgroundColor: ['#3B82F6', '#9333EA', '#F59E0B', '#10B981'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '75%',
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // 3. Render Line Chart
        if(document.getElementById('lineChart')) {
            new Chart(document.getElementById('lineChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: lineLabels,
                    datasets: [{
                        label: 'Growth',
                        data: lineValues,
                        borderColor: '#10B981',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        backgroundColor: 'rgba(16, 185, 129, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: { 
                        y: { beginAtZero: false, grid: { color: gridColor } },
                        x: { grid: { display: false } }
                    },
                    plugins: { legend: { display: false }, elements: { point: { radius: 0 } } }
                }
            });
        }
    }

    // Initialize!
    document.addEventListener("DOMContentLoaded", initCharts);
</script>

<?php include 'includes/footer.php'; ?>
