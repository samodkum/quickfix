<?php
// admin/bookings.php - Advanced Booking Management
require_once '../config/db.php';
require_once '../includes/csrf.php';

// Include the secure admin header BEFORE any output, but we need to intercept CSV export first.
// So we must handle session manually if we export CSV before header included.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// ==========================================
// 1. HANDLE UPDATES (Form Submissions)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_booking') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = $_POST['new_status'];
    $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
    $internal_notes = htmlspecialchars($_POST['internal_notes']);
    
    // Check if we also want to mark payment status (we can add it if needed, sticking to status for now)
    try {
        // Load current booking for logs/notifications/tech status transitions
        $currStmt = $pdo->prepare("SELECT id, user_id, status, technician_id, booking_unique_id FROM bookings WHERE id = ? LIMIT 1");
        $currStmt->execute([$booking_id]);
        $curr = $currStmt->fetch();
        if (!$curr) {
            throw new PDOException("Booking not found.");
        }

        if ($new_status === 'Technician Assigned' && empty($technician_id)) {
            throw new PDOException("Please assign a technician before setting status to Technician Assigned.");
        }

        $pdo->beginTransaction();

        $old_status = (string)$curr['status'];
        $old_technician_id = !empty($curr['technician_id']) ? (int)$curr['technician_id'] : null;

        $update_stmt = $pdo->prepare("UPDATE bookings SET status = ?, technician_id = ?, internal_notes = ? WHERE id = ?");
        $update_stmt->execute([$new_status, $technician_id, $internal_notes, $booking_id]);

        // Status history
        try {
            $log = $pdo->prepare("INSERT INTO booking_logs (booking_id, old_status, new_status, note, changed_by_user_id) VALUES (?, ?, ?, ?, ?)");
            $log->execute([$booking_id, $old_status, $new_status, $internal_notes ?: null, (int)$_SESSION['user_id']]);
        } catch(PDOException $e) { /* Non-blocking */ }

        // Technician status management
        if ($old_technician_id && $old_technician_id !== $technician_id) {
            try {
                $pdo->prepare("UPDATE technicians SET status = 'available' WHERE id = ?")->execute([$old_technician_id]);
            } catch(PDOException $e) { /* ignore */ }
        }
        if (!empty($technician_id)) {
            if ($new_status === 'Completed' || $new_status === 'Cancelled') {
                try {
                    $pdo->prepare("UPDATE technicians SET status = 'available' WHERE id = ?")->execute([$technician_id]);
                    if ($new_status === 'Completed') {
                        $pdo->prepare("UPDATE technicians SET total_jobs_completed = COALESCE(total_jobs_completed,0) + 1 WHERE id = ?")->execute([$technician_id]);
                    }
                } catch(PDOException $e) { /* ignore */ }
            } else {
                try {
                    $pdo->prepare("UPDATE technicians SET status = 'busy' WHERE id = ?")->execute([$technician_id]);
                } catch(PDOException $e) { /* ignore */ }
            }
        }

        // User notification (best-effort)
        $bk = !empty($curr['booking_unique_id']) ? (string)$curr['booking_unique_id'] : ('#' . $booking_id);
        $notifMsg = "Booking {$bk} updated: {$new_status}";
        try {
            $n = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
            $n->execute([(int)$curr['user_id'], $notifMsg]);
        } catch(PDOException $e) { /* ignore */ }

        $pdo->commit();

        $success = "Booking {$bk} successfully updated.";
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Failed to update booking: " . $e->getMessage();
    }
    }
}

// ==========================================
// 2. FETCH AVAILABLE TECHNICIANS
// ==========================================
$technicians = [];
try {
    $technicians = $pdo->query("SELECT id, name FROM technicians WHERE status != 'offline' ORDER BY name ASC")->fetchAll();
} catch(PDOException $e) {
    // If table doesn't exist yet, it will just be empty.
}

// ==========================================
// 3. FETCH AND FILTER ALL BOOKINGS
// ==========================================
$search = $_GET['search'] ?? '';
$filter_priority = $_GET['filter_priority'] ?? 'All';
$filter_status = $_GET['filter_status'] ?? 'All';
$filter_state_id = isset($_GET['filter_state_id']) ? (int)$_GET['filter_state_id'] : 0;
$filter_city_id = isset($_GET['filter_city_id']) ? (int)$_GET['filter_city_id'] : 0;

// Fetch states for filtering dropdown
$states = [];
try {
    $states = $pdo->query("SELECT id, state_name FROM states ORDER BY state_name ASC")->fetchAll();
} catch(PDOException $e) {
    $states = [];
}

// Base Query
$query = "SELECT b.*, u.name as customer_name, s.title as service_name, t.name as technician_name 
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN services s ON b.service_id = s.id
          LEFT JOIN technicians t ON b.technician_id = t.id
          WHERE 1=1";

$params = [];

if ($search !== '') {
    $query .= " AND (u.name LIKE ? OR s.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_priority !== 'All') {
    $query .= " AND b.emergency_level = ?";
    $params[] = $filter_priority;
}
if ($filter_status !== 'All') {
    $query .= " AND b.status = ?";
    $params[] = $filter_status;
}

// Filter by state/city (stored as strings in bookings)
if ($filter_state_id > 0) {
    try {
        $st = $pdo->prepare("SELECT state_name FROM states WHERE id = ? LIMIT 1");
        $st->execute([$filter_state_id]);
        $stateName = $st->fetchColumn();
        if ($stateName) {
            $query .= " AND b.state = ?";
            $params[] = (string)$stateName;
        }
    } catch(PDOException $e) {}
}
if ($filter_city_id > 0) {
    try {
        $ct = $pdo->prepare("SELECT city_name FROM cities WHERE id = ? LIMIT 1");
        $ct->execute([$filter_city_id]);
        $cityName = $ct->fetchColumn();
        if ($cityName) {
            $query .= " AND b.city = ?";
            $params[] = (string)$cityName;
        }
    } catch(PDOException $e) {}
}

$query .= " ORDER BY b.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch bookings: " . $e->getMessage();
    $bookings = [];
}

// ==========================================
// 4. CSV EXPORT LOGIC
// ==========================================
// Check if export was requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="quickfix_bookings_export.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV Headers
    fputcsv($output, [
        'Booking DB ID',
        'Booking Unique ID',
        'Customer Name',
        'Contact',
        'Service',
        'Priority',
        'Service Date',
        'Service Time',
        'Preferred Time',
        'State',
        'City',
        'Area',
        'Pincode',
        'Flat No',
        'Landmark',
        'Full Address',
        'Status',
        'Technician',
        'Payment Method',
        'Payment Status',
        'Total Amount',
        'Internal Notes',
        'Date Created'
    ]);
    
    // Add rows
    foreach ($bookings as $b) {
        fputcsv($output, [
            $b['id'], 
            $b['booking_unique_id'] ?? '',
            $b['customer_name'], 
            $b['contact'] ?? $b['contact_number'],
            $b['service_name'], 
            $b['emergency_level'], 
            $b['service_date'] ?? '',
            $b['service_time'] ?? '',
            $b['preferred_time'], 
            $b['state'] ?? '',
            $b['city'] ?? '',
            $b['area'] ?? '',
            $b['pincode'] ?? '',
            $b['flat_no'] ?? '',
            $b['landmark'] ?? '',
            $b['full_address'] ?? ($b['address'] ?? ''),
            $b['status'], 
            $b['technician_name'] ?? 'Unassigned', 
            $b['payment_method'] ?? '',
            $b['payment_status'] ?? '',
            $b['total_amount'] ?? '',
            $b['internal_notes'], 
            $b['created_at']
        ]);
    }
    
    fclose($output);
    exit(); // Stop parsing the rest of the HTML page!
}

// Now include header for HTML output
include 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
    <div>
        <h1 style="margin: 0; font-weight: 800;">Manage Bookings</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Filter, assign technicians, and update status.</p>
    </div>
    
    <!-- Action Buttons -->
    <div style="display: flex; gap: 12px;">
        <a href="?<?php echo $_SERVER['QUERY_STRING']; ?>&export=csv" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main);">
            <i class="fa-solid fa-download"></i> Export CSV
        </a>
    </div>
</div>

<!-- Advanced Filter Bar -->
<div class="chart-box" style="margin-bottom: 30px; padding: 20px;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
        
        <div style="flex-grow: 1; min-width: 200px;">
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Search Name or Service</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search..." style="margin: 0;">
        </div>
        
        <div style="min-width: 150px;">
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Priority</label>
            <select name="filter_priority" class="form-control" style="margin: 0;">
                <option value="All" <?php if($filter_priority==='All') echo 'selected'; ?>>All Priorities</option>
                <option value="Low" <?php if($filter_priority==='Low') echo 'selected'; ?>>Low</option>
                <option value="Medium" <?php if($filter_priority==='Medium') echo 'selected'; ?>>Medium</option>
                <option value="High" <?php if($filter_priority==='High') echo 'selected'; ?>>High Priority</option>
            </select>
        </div>
        
        <div style="min-width: 150px;">
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Status</label>
            <select name="filter_status" class="form-control" style="margin: 0;">
                <option value="All" <?php if($filter_status==='All') echo 'selected'; ?>>All Statuses</option>
                <option value="Requested" <?php if($filter_status==='Requested') echo 'selected'; ?>>Requested</option>
                <option value="Accepted" <?php if($filter_status==='Accepted') echo 'selected'; ?>>Accepted</option>
                <option value="Technician Assigned" <?php if($filter_status==='Technician Assigned') echo 'selected'; ?>>Technician Assigned</option>
                <option value="In Progress" <?php if($filter_status==='In Progress') echo 'selected'; ?>>In Progress</option>
                <option value="Completed" <?php if($filter_status==='Completed') echo 'selected'; ?>>Completed</option>
                <option value="Cancelled" <?php if($filter_status==='Cancelled') echo 'selected'; ?>>Cancelled</option>
            </select>
        </div>

        <div style="min-width: 170px;">
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">State</label>
            <select id="filter_state_id" name="filter_state_id" class="form-control" style="margin: 0;">
                <option value="0">All States</option>
                <?php foreach($states as $st): ?>
                    <option value="<?php echo (int)$st['id']; ?>" <?php if($filter_state_id === (int)$st['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($st['state_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width: 170px;">
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">City</label>
            <select id="filter_city_id" name="filter_city_id" class="form-control" style="margin: 0;" <?php echo $filter_state_id > 0 ? '' : 'disabled'; ?>>
                <option value="0">All Cities</option>
            </select>
        </div>
        
        <div style="align-self: flex-end;">
            <button type="submit" class="btn-primary" style="padding: 10px 24px; height: 42px;">Filter Results</button>
            <?php if(isset($_GET['search'])): ?>
                <a href="bookings.php" class="btn-outline" style="border: none; color: var(--danger-color);">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    (function () {
        const stateEl = document.getElementById('filter_state_id');
        const cityEl = document.getElementById('filter_city_id');
        if (!stateEl || !cityEl) return;

        const selectedCityId = <?php echo (int)$filter_city_id; ?>;

        async function loadCities(stateId) {
            cityEl.innerHTML = '<option value="0">All Cities</option>';
            cityEl.disabled = true;
            if (!stateId || stateId === '0') return;
            const res = await fetch('../api/cities.php?state_id=' + encodeURIComponent(stateId), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.ok) return;
            for (const c of data.cities) {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.city_name;
                if (selectedCityId && Number(c.id) === Number(selectedCityId)) opt.selected = true;
                cityEl.appendChild(opt);
            }
            cityEl.disabled = false;
        }

        stateEl.addEventListener('change', function () {
            // reset city selection on state change
            cityEl.value = '0';
            loadCities(stateEl.value);
        });

        // initial load (page refresh)
        loadCities(stateEl.value);
    })();
</script>

<!-- Alert Boxes -->
<?php if($success): ?>
    <div style="background-color: rgba(16, 185, 129, 0.1); color: #10B981; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.3);">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if($error): ?>
    <div style="background-color: rgba(239, 68, 68, 0.1); color: var(--danger-color); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.3);">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Professional Data Grid -->
<div style="display: grid; gap: 20px;">
    <?php if(empty($bookings)): ?>
        <div class="chart-box" style="text-align: center; padding: 60px; color: var(--text-muted);">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
            <h3>No bookings found</h3>
            <p>Try adjusting your search or filters.</p>
        </div>
    <?php else: ?>
        
        <?php foreach($bookings as $booking): ?>
            <!-- Complex Booking Card Layout for advanced management without massive tables -->
            <div class="chart-box" style="padding: 24px; position: relative; <?php if($booking['emergency_level'] === 'High') echo 'border-left: 4px solid var(--danger-color);'; else echo 'border-left: 4px solid var(--accent-color);'; ?>">
                
                <!-- ID & Badges Top Row -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                    <div style="font-weight: 700; color: var(--text-muted); font-size: 1.1rem;">#<?php echo $booking['id']; ?> - <?php echo htmlspecialchars($booking['service_name']); ?></div>
                    <div style="display: flex; gap: 10px;">
                        <!-- Priority Badge -->
                        <?php if($booking['emergency_level'] === 'High'): ?>
                            <span style="background: rgba(239, 68, 68, 0.1); color: var(--danger-color); padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;">H-Priority</span>
                        <?php endif; ?>
                        
                        <!-- Status Badge -->
                        <span style="padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;
                            <?php 
                                if($booking['status']==='Completed') echo 'background: rgba(16, 185, 129, 0.1); color: #10B981;';
                                elseif($booking['status']==='In Progress') echo 'background: rgba(245, 158, 11, 0.1); color: #F59E0B;';
                                elseif($booking['status']==='Accepted') echo 'background: rgba(147, 51, 234, 0.1); color: #9333EA;';
                                else echo 'background: rgba(59, 130, 246, 0.1); color: #3B82F6;'; // Requested
                            ?>">
                            <?php echo htmlspecialchars(strtoupper($booking['status'])); ?>
                        </span>
                    </div>
                </div>

                <!-- Three Column Info / Edit Split -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 24px;">
                    
                    <!-- Col 1: Customer Details -->
                    <div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.05em;">Customer</p>
                        <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 1.1rem; color: var(--text-main);"><i class="fa-solid fa-user" style="color: var(--text-muted); font-size: 0.9rem; margin-right: 6px;"></i> <?php echo htmlspecialchars($booking['customer_name']); ?></p>
                        <p style="margin: 0 0 16px 0; color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-phone" style="margin-right: 6px;"></i> <?php echo htmlspecialchars($booking['contact_number']); ?></p>
                        
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.05em;">Pref. Time</p>
                        <p style="margin: 0; color: var(--text-main); font-weight: 500;"><i class="fa-solid fa-clock" style="color: var(--text-muted); margin-right: 6px;"></i> <?php echo htmlspecialchars($booking['preferred_time']); ?></p>
                    </div>

                    <!-- Col 2: Assignment -->
                    <div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.05em;">Assigned Technician</p>
                        <?php if($booking['technician_name']): ?>
                            <div style="display: flex; align-items: center; gap: 10px; background: var(--bg-color); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color);">
                                <div style="width: 32px; height: 32px; background: var(--accent-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                    <?php echo substr($booking['technician_name'], 0, 1); ?>
                                </div>
                                <span style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($booking['technician_name']); ?></span>
                            </div>
                        <?php else: ?>
                            <div style="background: rgba(245, 158, 11, 0.1); color: #F59E0B; padding: 10px 12px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; border: 1px dashed #F59E0B;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Unassigned
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Col 3: Management Form -->
                    <div style="background: var(--bg-color); padding: 16px; border-radius: var(--border-radius-md); border: 1px solid var(--border-color);">
                        <form method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                            <input type="hidden" name="action" value="update_booking">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px;">Update Status</label>
                                    <select name="new_status" class="form-control" style="margin: 0; padding: 8px; font-size: 0.9rem;">
                                        <option value="Requested" <?php if($booking['status']==='Requested') echo 'selected'; ?>>Requested</option>
                                        <option value="Accepted" <?php if($booking['status']==='Accepted') echo 'selected'; ?>>Accepted</option>
                                        <option value="Technician Assigned" <?php if($booking['status']==='Technician Assigned') echo 'selected'; ?>>Technician Assigned</option>
                                        <option value="In Progress" <?php if($booking['status']==='In Progress') echo 'selected'; ?>>In Progress</option>
                                        <option value="Completed" <?php if($booking['status']==='Completed') echo 'selected'; ?>>Completed</option>
                                        <option value="Cancelled" <?php if($booking['status']==='Cancelled') echo 'selected'; ?>>Cancelled</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px;">Assign Tech</label>
                                    <select name="technician_id" class="form-control" style="margin: 0; padding: 8px; font-size: 0.9rem;">
                                        <option value="">-- None --</option>
                                        <?php foreach($technicians as $tech): ?>
                                            <option value="<?php echo $tech['id']; ?>" <?php if($booking['technician_id'] == $tech['id']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($tech['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px;">Internal Notes</label>
                                <input type="text" name="internal_notes" value="<?php echo htmlspecialchars($booking['internal_notes']); ?>" class="form-control" style="margin: 0; padding: 8px; font-size: 0.9rem;" placeholder="e.g. Needs specific tools...">
                            </div>
                            
                            <button type="submit" class="btn-primary" style="padding: 8px; font-size: 0.9rem;">Save Updates</button>
                        </form>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
