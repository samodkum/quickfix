<?php
// admin/edit-service.php
require_once '../config/db.php';
include 'includes/header.php';

if (!isset($_GET['id'])) {
    header('Location: services.php');
    exit();
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: services.php');
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_service') {
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $price = (float)$_POST['price']; 
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $image_url = $service['image_url']; // keep existing by default
    
    // Handle Image Upload if a new one was provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/services/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['image']['name']));
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_url = 'uploads/services/' . $filename;
        }
    }

    try {
        $update = $pdo->prepare("UPDATE services SET title=?, category=?, price=?, description=?, image_url=?, is_active=? WHERE id=?");
        if ($update->execute([$title, $category, $price, $description, $image_url, $is_active, $id])) {
            $success = "Service updated successfully.";
            
            // Refresh Data so the form below shows correctly
            $stmt->execute([$id]);
            $service = $stmt->fetch();
        }
    } catch(PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <a href="services.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;"><i class="fa-solid fa-arrow-left"></i> Back to Services</a>
        <h1 style="margin: 8px 0 0 0; font-weight: 800;">Edit Service</h1>
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

<div class="chart-box" style="max-width: 600px;">
    <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 20px;">
        <input type="hidden" name="action" value="edit_service">
        
        <!-- Image Preview & Upload -->
        <div>
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">Service Image</label>
            <div style="display: flex; gap: 16px; align-items: center;">
                <div style="width: 80px; height: 80px; border-radius: 8px; overflow: hidden; background: var(--border-color);">
                    <?php $img_src = (strpos($service['image_url'], 'http') === 0) ? $service['image_url'] : '../' . $service['image_url']; ?>
                    <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <input type="file" name="image" accept="image/*" class="form-control" style="margin: 0; padding: 8px; flex-grow: 1;">
            </div>
            <p style="color: var(--text-muted); margin: 4px 0 0 0; font-size: 0.8rem;">Leave blank to keep existing image.</p>
        </div>

        <div>
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Service Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($service['title']); ?>" required class="form-control" style="margin: 0;">
        </div>
        
        <div>
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Category</label>
            <input type="text" name="category" value="<?php echo htmlspecialchars($service['category']); ?>" required class="form-control" style="margin: 0;">
        </div>
        
        <div>
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Base Price ($)</label>
            <input type="number" name="price" value="<?php echo htmlspecialchars($service['price']); ?>" required min="0" step="0.01" class="form-control" style="margin: 0;">
        </div>
        
        <div>
            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Description</label>
            <textarea name="description" required rows="5" class="form-control" style="resize: vertical; margin: 0;"><?php echo htmlspecialchars($service['description']); ?></textarea>
        </div>
        
        <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
            <input type="checkbox" name="is_active" id="is_active" value="1" <?php if(isset($service['is_active']) && $service['is_active']) echo 'checked'; ?> style="width: 20px; height: 20px;">
            <label for="is_active" style="font-weight: 500;">Service is strictly active and visible to public</label>
        </div>
        
        <button type="submit" class="btn-primary" style="padding: 14px; margin-top: 16px; font-size: 1.05rem;">Save Changes</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
