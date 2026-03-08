<?php
// admin/settings.php - Advanced Global Settings and Admin Credentials
require_once '../config/db.php';
include 'includes/header.php';

$success = '';
$error = '';

// ==========================================
// 1. HANDLE SETTINGS UPDATE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_settings') {
        // Dynamic loop to update all settings submitted in the form
        try {
            $update_stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            foreach ($_POST['settings'] as $key => $value) {
                // Only allow updating existing keys to prevent junk injection
                $update_stmt->execute([trim($value), $key]);
            }
            $success = "Global settings updated successfully.";
        } catch(PDOException $e) {
            $error = "Failed to update settings.";
        }
    }
    
    elseif ($_POST['action'] === 'update_password') {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        
        if ($new_pass !== $confirm_pass) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_pass) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            // Verify old password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $hash = $stmt->fetchColumn();
            
            if (password_verify($old_pass, $hash)) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $_SESSION['user_id']]);
                $success = "Admin security password has been changed securely.";
            } else {
                $error = "Incorrect current password.";
            }
        }
    }
}

// ==========================================
// 2. FETCH CURRENT SETTINGS
// ==========================================
$settingsData = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    // Re-pack into associative array for easy lookups
    foreach($rows as $r) {
        $settingsData[$r['setting_key']] = $r['setting_value'];
    }
} catch(PDOException $e) {
    if(!$error) $error = "Could not load settings configuration.";
}

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="margin: 0; font-weight: 800;">Platform Settings</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Manage global site configuration and your security credentials.</p>
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

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 32px; align-items: start;">

    <!-- GLOBAL SITE SETTINGS -->
    <div class="chart-box" style="padding: 32px;">
        <h3 style="margin: 0 0 24px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; font-weight: 700;">
            <i class="fa-solid fa-globe" style="color: var(--accent-color); margin-right: 8px;"></i> Global Configuration
        </h3>
        
        <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action" value="update_settings">
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;">Website Name</label>
                <input type="text" name="settings[site_name]" value="<?php echo htmlspecialchars($settingsData['site_name'] ?? ''); ?>" class="form-control" style="margin: 0;">
                <p style="font-size: 0.75rem; color: var(--text-muted); margin: 4px 0 0 0;">Used in headers, footers, and email templates.</p>
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;">Support Email Address</label>
                <input type="email" name="settings[contact_email]" value="<?php echo htmlspecialchars($settingsData['contact_email'] ?? ''); ?>" class="form-control" style="margin: 0;">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;">Public Support Phone</label>
                <input type="text" name="settings[contact_phone]" value="<?php echo htmlspecialchars($settingsData['contact_phone'] ?? ''); ?>" class="form-control" style="margin: 0;">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;">System Currency Code</label>
                <select name="settings[currency]" class="form-control" style="margin: 0;">
                    <option value="USD" <?php if(($settingsData['currency']??'') === 'USD') echo 'selected'; ?>>USD ($)</option>
                    <option value="EUR" <?php if(($settingsData['currency']??'') === 'EUR') echo 'selected'; ?>>EUR (€)</option>
                    <option value="GBP" <?php if(($settingsData['currency']??'') === 'GBP') echo 'selected'; ?>>GBP (£)</option>
                </select>
            </div>
            
            <button type="submit" class="btn-primary" style="padding: 12px; margin-top: 8px;">Save Configuration</button>
        </form>
    </div>

    <!-- SECURITY: CHANGE PASSWORD -->
    <div class="chart-box" style="padding: 32px; border-top: 4px solid var(--danger-color);">
        <h3 style="margin: 0 0 24px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; font-weight: 700;">
            <i class="fa-solid fa-shield-halved" style="color: var(--danger-color); margin-right: 8px;"></i> Security Credentials
        </h3>
        
        <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action" value="update_password">
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;">Current Password</label>
                <input type="password" name="old_password" required class="form-control" style="margin: 0;">
            </div>
            
            <hr style="border: 0; border-top: 1px dashed var(--border-color); margin: 0;">
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;">New Admin Password</label>
                <input type="password" name="new_password" required class="form-control" style="margin: 0;">
            </div>
            
            <div>
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;">Confirm New Password</label>
                <input type="password" name="confirm_password" required class="form-control" style="margin: 0;">
            </div>
            
            <button type="submit" class="btn-outline" style="border-color: var(--danger-color); color: var(--danger-color); padding: 12px; background: rgba(239, 68, 68, 0.05);">Update Secure Password</button>
        </form>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
