<?php
// admin/services.php - Admin interface to Create, Read, Update, and Delete (CRUD) Services
require_once '../config/db.php';

// Authentication is handled in header.php
include 'includes/header.php';

$success = '';
$error = '';
$csrf_ok = true;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_validate($_POST['_csrf'] ?? null)) {
    $csrf_ok = false;
    $error = "Security check failed. Please refresh and try again.";
}

// Ensure uploads directory exists
$upload_dir = '../uploads/services/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ==========================================
// 1. ADD NEW SERVICE (Form Submission)
// ==========================================
if ($csrf_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_service') {
    
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $price = (float)$_POST['price'];
    $description = trim($_POST['description']);
    $rating = 5.0; // Default rating for newly created services
    
    // Default fallback image if none uploaded
    $image_url = 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&q=80&w=400'; 
    
    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['image']['name']));
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_url = 'uploads/services/' . $filename; // Store relative path for frontend
        } else {
            $error = "Failed to move uploaded file.";
        }
    }
    
    if (empty($title) || empty($category) || empty($price) || empty($description)) {
        $error = "All text fields are required.";
    } elseif (!$error) {
        try {
            $stmt = $pdo->prepare("INSERT INTO services (title, category, price, description, image_url, rating, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            if ($stmt->execute([$title, $category, $price, $description, $image_url, $rating])) {
                $success = "New service '$title' successfully added!";
            }
        } catch(PDOException $e) {
            $error = "Failed to add service: " . $e->getMessage();
        }
    }
}

// ==========================================
// 2. TOGGLE SERVICE ACTIVE STATUS
// ==========================================
if ($csrf_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
    $service_id = (int)$_POST['service_id'];
    $new_state = (int)$_POST['new_state'];
    
    try {
        $stmt = $pdo->prepare("UPDATE services SET is_active = ? WHERE id = ?");
        if ($stmt->execute([$new_state, $service_id])) {
            $success = $new_state ? "Service enabled." : "Service disabled and hidden from public.";
        }
    } catch(PDOException $e) {
        $error = "Failed to update service status.";
    }
}

// ==========================================
// 3. DELETE SERVICE
// ==========================================
if ($csrf_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_service') {
    $service_id = (int)$_POST['service_id'];
    try {
        $stmt = $pdo->prepare("UPDATE services SET is_active = 0, deleted_at = NOW() WHERE id = ?");
        if ($stmt->execute([$service_id])) {
            $success = "Service removed (soft deleted).";
        }
    } catch(PDOException $e) {
        $error = "Failed to delete service: " . $e->getMessage();
    }
}

// ==========================================
// 4. FETCH ALL SERVICES
// ==========================================
$services = [];
try {
    $stmt = $pdo->query("SELECT * FROM services WHERE deleted_at IS NULL ORDER BY category ASC, title ASC");
    $services = $stmt->fetchAll();
} catch(PDOException $e) {
    if(!$error) $error = "Failed to load services.";
}

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; font-weight: 800;">Manage Services</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Create, edit, and toggle service availability.</p>
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

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 32px; align-items: start;">
    
    <!-- LEFT SIDE: ADD SERVICE FORM -->
    <div class="chart-box" style="position: sticky; top: 100px;">
        <h3 style="margin: 0 0 24px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; font-weight: 700;">Add New Service</h3>
        
        <!-- enctype="multipart/form-data" is REQUIRED for file uploads in forms -->
        <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" name="action" value="add_service">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Service Image</label>
                <input type="file" name="image" accept="image/*" class="form-control" style="margin: 0; padding: 8px;">
            </div>

            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Service Title</label>
                <input type="text" name="title" required class="form-control" placeholder="e.g. Broken Pipe Fix" style="margin: 0;">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Category</label>
                <input type="text" name="category" required class="form-control" placeholder="e.g. Plumbing" style="margin: 0;">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Base Price ($)</label>
                <input type="number" name="price" required min="0" step="0.01" class="form-control" placeholder="50.00" style="margin: 0;">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Description</label>
                <textarea name="description" required rows="4" class="form-control" style="resize: vertical; margin: 0;"></textarea>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">Create Service</button>
        </form>
    </div>

    <!-- RIGHT SIDE: SERVICES LIST -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php if(empty($services)): ?>
            <div class="chart-box" style="text-align: center; padding: 60px; color: var(--text-muted);">
                <p>No services found. Add one on the left!</p>
            </div>
        <?php else: ?>
            <?php foreach($services as $service): ?>
                
                <div class="chart-box" style="display: flex; gap: 24px; padding: 20px; align-items: start; <?php if(isset($service['is_active']) && !$service['is_active']) echo 'opacity: 0.6;'; ?>">
                    
                    <!-- Thumbnail -->
                    <div style="width: 120px; height: 120px; border-radius: 12px; overflow: hidden; flex-shrink: 0; background-color: var(--border-color);">
                        <?php 
                            // QuickFix logic: if absolute URL (http), print direct, else prepend ../ to walk up admin dir
                            $img_src = (strpos($service['image_url'], 'http') === 0) ? $service['image_url'] : '../' . $service['image_url']; 
                        ?>
                        <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Service" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    
                    <!-- Content -->
                    <div style="flex-grow: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span style="background: var(--bg-color); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; color: var(--text-main); border: 1px solid var(--border-color); text-transform: uppercase;">
                                    <?php echo htmlspecialchars($service['category']); ?>
                                </span>
                                <h3 style="margin: 10px 0 4px 0; font-size: 1.25rem; font-weight: 700;"><?php echo htmlspecialchars($service['title']); ?></h3>
                                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0 0 12px 0; line-height: 1.4;"><?php echo htmlspecialchars($service['description']); ?></p>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 800; color: var(--accent-color); font-size: 1.4rem;">$<?php echo number_format($service['price'], 2); ?></div>
                                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;"><i class="fa-solid fa-star" style="color: #F59E0B;"></i> <?php echo number_format($service['rating'], 1); ?></div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div style="display: flex; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 8px;">
                            
                            <a href="edit-service.php?id=<?php echo $service['id']; ?>" class="btn-outline" style="border-color: var(--border-color); color: var(--text-main); padding: 6px 16px; text-decoration: none;">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </a>
                            
                            <!-- Toggle Active Status Form -->
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                <?php if(isset($service['is_active']) && $service['is_active']): ?>
                                    <input type="hidden" name="new_state" value="0">
                                    <button type="submit" class="btn-outline" style="border-color: #FCD34D; color: #D97706; padding: 6px 16px;">
                                        <i class="fa-solid fa-eye-slash"></i> Disable
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="new_state" value="1">
                                    <button type="submit" class="btn-outline" style="border-color: #A7F3D0; color: #10B981; padding: 6px 16px;">
                                        <i class="fa-solid fa-eye"></i> Enable
                                    </button>
                                <?php endif; ?>
                            </form>

                            <div style="flex-grow: 1;"></div>
                            
                            <!-- Delete Form with Javascript Confirmation -->
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('WARNING: Deleting this service will DELETE ALL customer bookings related to it! Proceed?');">
                                <input type="hidden" name="action" value="delete_service">
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                <button type="submit" class="icon-btn" style="color: var(--danger-color); border: none; background: rgba(239, 68, 68, 0.1);">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
</div>

<?php include 'includes/footer.php'; ?>
