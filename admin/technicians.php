<?php
// admin/technicians.php - Advanced Technician Management
require_once '../config/db.php';
include 'includes/header.php';

$success = '';
$error = '';

// Fetch services for assignment dropdown
$services = [];
try {
    $services = $pdo->query("SELECT id, title FROM services ORDER BY title ASC")->fetchAll();
} catch(PDOException $e) {
    $services = [];
}

// ==========================================
// 1. ADD NEW TECHNICIAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
    
    if ($_POST['action'] === 'add_technician') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $specialty = trim($_POST['specialty']);
        $experience = isset($_POST['experience']) && $_POST['experience'] !== '' ? (int)$_POST['experience'] : null;
        $skills = trim($_POST['skills'] ?? '');
        $service_id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;

        $photoPath = null;
        if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowedExt, true)) {
                $targetDir = __DIR__ . '/../uploads/technicians';
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }
                $fileName = 'tech_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = $targetDir . '/' . $fileName;
                if (@move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                    $photoPath = 'uploads/technicians/' . $fileName;
                }
            }
        }
        
        if (empty($name) || empty($email)) {
            $error = "Name and Email are required fields.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO technicians (name, email, phone, specialty, photo, experience, skills, service_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $email, $phone, $specialty, $photoPath, $experience, $skills, $service_id])) {
                    $success = "Technician '$name' added successfully.";
                }
            } catch(PDOException $e) {
                // Handle duplicate email error gracefully
                if ($e->errorInfo[1] == 1062) {
                    $error = "A technician with this email address already exists.";
                } else {
                    $error = "Failed to add technician: " . $e->getMessage();
                }
            }
        }
    }
    
    // ==========================================
    // 2. TOGGLE STATUS
    // ==========================================
    elseif ($_POST['action'] === 'update_status') {
        $tech_id = (int)$_POST['tech_id'];
        $status = $_POST['new_status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE technicians SET status = ? WHERE id = ?");
            if ($stmt->execute([$status, $tech_id])) {
                $success = "Technician status changed to " . strtoupper($status) . ".";
            }
        } catch(PDOException $e) {
            $error = "Failed to update status.";
        }
    }
    
    // ==========================================
    // 3. DELETE TECHNICIAN
    // ==========================================
    elseif ($_POST['action'] === 'delete_tech') {
        $tech_id = (int)$_POST['tech_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE technicians SET status = 'offline', deleted_at = NOW() WHERE id = ?");
            if ($stmt->execute([$tech_id])) {
                $success = "Technician removed (soft deleted).";
            }
        } catch(PDOException $e) {
            $error = "Failed to delete technician: " . $e->getMessage();
        }
    }
    }
}

// ==========================================
// FETCH ALL TECHNICIANS WITH WORKLOAD
// ==========================================
$technicians = [];
try {
    // We join bookings to count how many ACTIVE jobs they have
    $query = "
        SELECT t.*, s.title AS service_name,
        (SELECT COUNT(*) FROM bookings WHERE technician_id = t.id AND (status = 'Accepted' OR status = 'In Progress')) as active_jobs,
        (SELECT COUNT(*) FROM bookings WHERE technician_id = t.id AND status = 'Completed') as completed_jobs
        FROM technicians t
        LEFT JOIN services s ON t.service_id = s.id
        WHERE t.deleted_at IS NULL
        ORDER BY t.name ASC
    ";
    $technicians = $pdo->query($query)->fetchAll();
} catch(PDOException $e) {
    if(!$error) $error = "Failed to load technicians.";
}

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; font-weight: 800;">Manage Technicians</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Add staff, monitor workloads, and assign roles.</p>
    </div>
</div>

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

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 32px; align-items: start;">
    
    <!-- LEFT SIDE: ADD FORM -->
    <div class="chart-box" style="position: sticky; top: 100px;">
        <h3 style="margin: 0 0 24px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; font-weight: 700;">Add Technician</h3>
        
        <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" name="action" value="add_technician">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Full Name *</label>
                <input type="text" name="name" required class="form-control" style="margin: 0;">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Email Address *</label>
                <input type="email" name="email" required class="form-control" style="margin: 0;">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Phone Number</label>
                <input type="text" name="phone" class="form-control" style="margin: 0;" placeholder="(123) 456-7890">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Specialty / Trading</label>
                <select name="specialty" class="form-control" style="margin: 0;">
                    <option value="General">General Handyman</option>
                    <option value="Plumber">Plumber</option>
                    <option value="Electrician">Electrician</option>
                    <option value="HVAC">HVAC Technician</option>
                    <option value="Appliance">Appliance Repair</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Assign Service</label>
                <select name="service_id" class="form-control" style="margin: 0;">
                    <option value="">-- None --</option>
                    <?php foreach($services as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Experience (years)</label>
                <input type="number" name="experience" min="0" max="60" class="form-control" style="margin: 0;" placeholder="e.g. 5">
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Skills</label>
                <input type="text" name="skills" class="form-control" style="margin: 0;" placeholder="e.g. Wiring, Switchboard, Fan install">
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Photo</label>
                <input type="file" name="photo" accept="image/*" class="form-control" style="margin: 0; padding: 10px;">
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">Create Profile</button>
        </form>
    </div>

    <!-- RIGHT SIDE: STAFF GRID -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
        <?php if(empty($technicians)): ?>
            <div class="chart-box" style="text-align: center; padding: 60px; color: var(--text-muted); grid-column: 1 / -1;">
                <i class="fa-solid fa-helmet-safety" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <p>No technicians on staff yet. Add one to the left.</p>
            </div>
        <?php else: ?>
            <?php foreach($technicians as $tech): ?>
                <div class="chart-box" style="padding: 24px; display: flex; flex-direction: column; gap: 16px; <?php if($tech['status'] === 'offline') echo 'opacity: 0.6;'; ?>">
                    
                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="display: flex; gap: 16px; align-items: center;">
                            <div style="width: 48px; height: 48px; background: var(--bg-color); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 1.25rem; font-weight: 800; color: var(--text-muted); border: 2px solid var(--border-color); overflow: hidden;">
                                <?php if(!empty($tech['photo']) && file_exists('../' . $tech['photo'])): ?>
                                    <img src="../<?php echo htmlspecialchars($tech['photo']); ?>" alt="Tech" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <!-- Random portrait placeholder -->
                                    <img src="https://randomuser.me/api/portraits/men/<?php echo ($tech['id'] % 90) + 1; ?>.jpg" alt="Tech" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;"><?php echo htmlspecialchars($tech['name']); ?></h3>
                                <p style="margin: 2px 0 0 0; color: var(--accent-color); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">
                                    <i class="fa-solid fa-wrench" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($tech['specialty']); ?>
                                </p>
                                <?php if(!empty($tech['service_name'])): ?>
                                    <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">
                                        Assigned: <?php echo htmlspecialchars($tech['service_name']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Status Indicator -->
                        <div style="width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 0 4px var(--card-bg);
                            <?php 
                                if($tech['status'] === 'available') echo 'background: #10B981;';
                                elseif($tech['status'] === 'busy') echo 'background: #F59E0B;';
                                else echo 'background: #94A3B8;'; 
                            ?>">
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <div style="background: var(--bg-color); padding: 12px; border-radius: 8px; font-size: 0.85rem;">
                        <div style="margin-bottom: 6px;"><i class="fa-solid fa-envelope" style="color: var(--text-muted); width: 20px;"></i> <?php echo htmlspecialchars($tech['email']); ?></div>
                        <div><i class="fa-solid fa-phone" style="color: var(--text-muted); width: 20px;"></i> <?php echo htmlspecialchars($tech['phone'] ?: 'No phone provided'); ?></div>
                    </div>

                    <!-- Workload -->
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-top: 1px dashed var(--border-color); border-bottom: 1px dashed var(--border-color);">
                        <div style="text-align: center; flex-grow: 1; border-right: 1px solid var(--border-color);">
                            <div style="font-weight: 800; font-size: 1.4rem; color: <?php echo $tech['active_jobs'] > 3 ? 'var(--danger-color)' : 'var(--text-main)'; ?>;">
                                <?php echo $tech['active_jobs']; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Active Jobs</div>
                        </div>
                        <div style="text-align: center; flex-grow: 1;">
                            <div style="font-weight: 800; font-size: 1.4rem; color: var(--text-main);"><?php echo $tech['completed_jobs']; ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Completed</div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: var(--text-muted);">
                        <span><i class="fa-solid fa-star" style="color: #F59E0B; margin-right: 6px;"></i><?php echo number_format((float)($tech['rating'] ?? 0), 2); ?></span>
                        <span><?php echo (int)($tech['total_reviews'] ?? 0); ?> reviews</span>
                        <span><?php echo (int)($tech['total_jobs_completed'] ?? 0); ?> total jobs</span>
                    </div>

                    <!-- Management Actions -->
                    <div style="display: flex; gap: 8px; justify-content: space-between; align-items: center;">
                        
                        <form method="POST" style="margin: 0; display: flex; gap: 8px; flex-grow: 1;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="tech_id" value="<?php echo $tech['id']; ?>">
                            <select name="new_status" class="form-control" style="margin: 0; padding: 6px 10px; font-size: 0.85rem; height: auto;" onchange="this.form.submit()">
                                <option value="available" <?php if($tech['status'] === 'available') echo 'selected'; ?>>Available</option>
                                <option value="busy" <?php if($tech['status'] === 'busy') echo 'selected'; ?>>Busy</option>
                                <option value="offline" <?php if($tech['status'] === 'offline') echo 'selected'; ?>>Offline</option>
                            </select>
                        </form>

                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Remove technician? Their history will be kept but they will be unassigned from future jobs.');">
                            <input type="hidden" name="action" value="delete_tech">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="tech_id" value="<?php echo $tech['id']; ?>">
                            <button type="submit" class="icon-btn" style="color: var(--danger-color); border: none; background: rgba(239, 68, 68, 0.1); width: 34px; height: 34px; border-radius: 8px;">
                                <i class="fa-solid fa-trash" style="font-size: 0.9rem;"></i>
                            </button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
