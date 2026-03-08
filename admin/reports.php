<?php
// admin/reports.php - Professional Reporting Suite
require_once '../config/db.php';

// Quick CSV Exporter logic placed before header to prevent "headers already sent" errors
if (isset($_GET['download'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') exit('Unauthorized');
    
    $type = $_GET['download'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="quickfix_'.$type.'_report.csv"');
    $output = fopen('php://output', 'w');
    
    if ($type === 'revenue') {
        fputcsv($output, ['Date Paid', 'Booking ID', 'Customer', 'Service', 'Amount USD']);
        $rows = $pdo->query("SELECT b.created_at, b.id, u.name as customer, s.title, s.price FROM bookings b JOIN users u ON b.user_id = u.id JOIN services s ON b.service_id = s.id WHERE b.payment_status = 'completed' ORDER BY b.created_at DESC")->fetchAll();
        foreach($rows as $r) {
            fputcsv($output, [date('Y-m-d', strtotime($r['created_at'])), $r['id'], $r['customer'], $r['title'], $r['price']]);
        }
    }
    elseif ($type === 'users') {
        fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Status', 'Registered Date']);
        $rows = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();
        foreach($rows as $r) {
            fputcsv($output, [$r['id'], $r['name'], $r['email'], $r['role'], $r['status'], $r['created_at']]);
        }
    }
    fclose($output);
    exit();
}

// Generate Printable Report Views handling
$view_print = isset($_GET['view']) ? $_GET['view'] : false;

if ($view_print === 'pdf_booking') {
    // Generate a clean HTML snapshot for printing to PDF via browser
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') exit('Unauthorized');
    
    $bookings = $pdo->query("SELECT b.*, u.name, s.title FROM bookings b JOIN users u ON b.user_id=u.id JOIN services s ON b.service_id = s.id ORDER BY b.created_at DESC")->fetchAll();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Booking Report</title>
        <style>
            body { font-family: sans-serif; color: #333; padding: 40px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
            th { background: #f4f4f4; }
            h1 { text-align: center; }
            .print-btn { display: block; margin: 0 auto 30px auto; padding: 10px 20px; background: #000; color: #fff; cursor: pointer; border: none; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body onload="window.print()">
        <button class="print-btn" onclick="window.print()">Save as PDF / Print</button>
        <h1>QuickFix Master Booking Report</h1>
        <p><strong>Generated:</strong> <?php echo date('F d, Y H:i:s'); ?></p>
        <table>
            <thead><tr><th>ID</th><th>Date</th><th>Customer</th><th>Service</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach($bookings as $b): ?>
                <tr>
                    <td><?php echo $b['id']; ?></td>
                    <td><?php echo date('m/d/Y', strtotime($b['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                    <td><?php echo htmlspecialchars($b['title']); ?></td>
                    <td><?php echo htmlspecialchars($b['emergency_level']); ?></td>
                    <td><?php echo htmlspecialchars($b['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit();
}

// STANDARD PAGE RENDER
include 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; font-weight: 800;">Reports Studio</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Generate CSV and Printable PDF reports of platform activity.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px;">

    <!-- Booking Reports -->
    <div class="chart-box" style="padding: 32px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 16px;">
        <div style="width: 80px; height: 80px; background: rgba(59, 130, 246, 0.1); color: #3B82F6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
            <i class="fa-solid fa-file-invoice"></i>
        </div>
        <h3 style="margin: 0; font-weight: 800; font-size: 1.4rem;">Master Bookings</h3>
        <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin: 0;">Comprehensive history of all bookings ever made through the system, complete with priority and status.</p>
        
        <div style="display: flex; gap: 12px; width: 100%; margin-top: auto; padding-top: 16px;">
            <a href="reports.php?view=pdf_booking" target="_blank" class="btn-primary" style="flex-grow: 1; text-align: center; text-decoration: none; padding: 12px;">
                <i class="fa-solid fa-file-pdf" style="margin-right: 6px;"></i> Print PDF
            </a>
            <a href="bookings.php?export=csv" class="btn-outline" style="flex-grow: 1; text-align: center; text-decoration: none; padding: 12px; border-color: var(--border-color); color: var(--text-main);">
                <i class="fa-solid fa-file-csv" style="margin-right: 6px;"></i> CSV
            </a>
        </div>
    </div>

    <!-- Revenue Reports -->
    <div class="chart-box" style="padding: 32px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 16px;">
        <div style="width: 80px; height: 80px; background: rgba(16, 185, 129, 0.1); color: #10B981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <h3 style="margin: 0; font-weight: 800; font-size: 1.4rem;">Financial Revenue</h3>
        <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin: 0;">Detailed CSV export of all COMPLETED and PAID bookings for accounting and bookkeeping purposes.</p>
        
        <div style="display: flex; gap: 12px; width: 100%; margin-top: auto; padding-top: 16px;">
            <a href="reports.php?download=revenue" class="btn-outline" style="flex-grow: 1; text-align: center; text-decoration: none; padding: 12px; border-color: #10B981; color: #10B981; background: rgba(16, 185, 129, 0.05);">
                <i class="fa-solid fa-download" style="margin-right: 6px;"></i> Download CSV Log
            </a>
        </div>
    </div>

    <!-- User Audit -->
    <div class="chart-box" style="padding: 32px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 16px;">
        <div style="width: 80px; height: 80px; background: rgba(147, 51, 234, 0.1); color: #9333EA; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
            <i class="fa-solid fa-users-viewfinder"></i>
        </div>
        <h3 style="margin: 0; font-weight: 800; font-size: 1.4rem;">Active Users List</h3>
        <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin: 0;">Export your entire customer database including their contact information and current account standing.</p>
        
        <div style="display: flex; gap: 12px; width: 100%; margin-top: auto; padding-top: 16px;">
            <a href="reports.php?download=users" class="btn-outline" style="flex-grow: 1; text-align: center; text-decoration: none; padding: 12px; border-color: #9333EA; color: #9333EA; background: rgba(147, 51, 234, 0.05);">
                <i class="fa-solid fa-cloud-arrow-down" style="margin-right: 6px;"></i> Export Accounts
            </a>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
