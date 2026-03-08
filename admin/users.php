<?php
// admin/users.php - Admin interface to manage registered users and admins
require_once '../config/db.php';
include 'includes/header.php';

$success = '';
$error = '';

// ==========================================
// 1. MANAGE USERS (Form Submissions)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
    
    $target_user_id = (int)$_POST['user_id'];
    
    // SAFETY CHECK: Prevent the currently logged-in admin from touching themselves!
    if ($target_user_id === $_SESSION['user_id']) {
        $error = "You cannot modify your own active admin session account!";
    } else {
        try {
            if ($_POST['action'] === 'delete_user') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'blocked', deleted_at = NOW() WHERE id = ?");
                if ($stmt->execute([$target_user_id])) {
                    $success = "User account removed (soft deleted).";
                }
            }
            elseif ($_POST['action'] === 'change_role') {
                $new_role = $_POST['new_role'] === 'admin' ? 'admin' : 'user';
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                if ($stmt->execute([$new_role, $target_user_id])) {
                    $success = "User role successfully updated to " . strtoupper($new_role) . ".";
                }
            }
            elseif ($_POST['action'] === 'toggle_block') {
                $new_status = $_POST['new_status'] === 'blocked' ? 'blocked' : 'active';
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $target_user_id])) {
                    $success = "User account status updated to " . strtoupper($new_status) . ".";
                }
            }
        } catch(PDOException $e) {
            $error = "Database action failed: " . $e->getMessage();
        }
    }
    }
}

// ==========================================
// 2. FETCH ALL USERS
// ==========================================
$users = [];
$search = $_GET['search'] ?? '';
$filter_role = $_GET['filter_role'] ?? 'all';

$query = "SELECT id, name, email, role, status, created_at, last_login FROM users WHERE deleted_at IS NULL";
$params = [];

if ($search !== '') {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_role !== 'all') {
    $query .= " AND role = ?";
    $params[] = $filter_role;
}

$query .= " ORDER BY role ASC, created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    if(!$error) $error = "Failed to fetch users: " . $e->getMessage();
}
?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
    <div>
        <h1 style="margin: 0; font-weight: 800;">Manage Users</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">View, search, block, and manage roles.</p>
    </div>
</div>

<!-- Simple Filter Bar -->
<div class="chart-box" style="margin-bottom: 30px; padding: 20px;">
    <form method="GET" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
        <div style="flex-grow: 1; min-width: 250px;">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search by name or email..." style="margin: 0;">
        </div>
        <div>
            <select name="filter_role" class="form-control" style="margin: 0; min-width: 150px;">
                <option value="all" <?php if($filter_role==='all') echo 'selected'; ?>>All Roles</option>
                <option value="admin" <?php if($filter_role==='admin') echo 'selected'; ?>>Admins Only</option>
                <option value="user" <?php if($filter_role==='user') echo 'selected'; ?>>Customers Only</option>
            </select>
        </div>
        <button type="submit" class="btn-primary" style="padding: 10px 24px;">Search</button>
        <?php if($search !== '' || $filter_role !== 'all'): ?>
            <a href="users.php" class="btn-outline" style="border: none; color: var(--danger-color);">Clear</a>
        <?php endif; ?>
    </form>
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

<div style="overflow-x: auto; background: var(--card-bg); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Profile</th>
                <th>Contact</th>
                <th>Role & Status</th>
                <th>Activity</th>
                <th>Manage</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($users)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 40px;">No users found.</td></tr>
            <?php else: ?>
                <?php foreach($users as $user): ?>
                    <tr style="<?php if(isset($user['status']) && $user['status'] === 'blocked') echo 'opacity: 0.6; background-color: rgba(0,0,0,0.02);'; ?>">
                        
                        <!-- Profile -->
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; font-size: 1.05rem;"><?php echo htmlspecialchars($user['name']); ?></div>
                                    <div style="color: var(--text-muted); font-size: 0.8rem;">ID: #<?php echo $user['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Contact -->
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: var(--accent-color); font-weight: 500; text-decoration: none;">
                                <i class="fa-solid fa-envelope" style="margin-right: 6px;"></i><?php echo htmlspecialchars($user['email']); ?>
                            </a>
                        </td>
                        
                        <!-- Role & Status -->
                        <td>
                            <div style="display: flex; gap: 8px; flex-direction: column; align-items: flex-start;">
                                <span style="padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid var(--border-color); text-transform: uppercase;
                                    <?php echo ($user['role'] === 'admin') 
                                        ? 'background: rgba(59, 130, 246, 0.1); color: #3B82F6; border-color: rgba(59, 130, 246, 0.3);' 
                                        : 'background: var(--bg-color); color: var(--text-muted);'; 
                                    ?>">
                                    <i class="fa-solid <?php echo ($user['role'] === 'admin') ? 'fa-shield-halved' : 'fa-user'; ?>" style="margin-right: 4px;"></i>
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                                
                                <?php if(isset($user['status']) && $user['status'] === 'blocked'): ?>
                                    <span style="color: var(--danger-color); font-size: 0.75rem; font-weight: 700; background: rgba(239, 68, 68, 0.1); padding: 2px 6px; border-radius: 4px;">BLOCKED</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <!-- Activity -->
                        <td>
                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                <div style="margin-bottom: 4px;"><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                <div><strong>Last Login:</strong> <?php echo current($user['last_login'] ? [date('M d, g:i a', strtotime($user['last_login']))] : ['Never']); ?></div>
                            </div>
                            <div style="margin-top: 8px;">
                                <!-- Simple link mapping to bookings page pre-filtered for this exact user -->
                                <a href="bookings.php?search=<?php echo urlencode($user['name']); ?>" style="font-size: 0.8rem; color: var(--accent-color); text-decoration: none; font-weight: 600;">
                                    View History <i class="fa-solid fa-arrow-right" style="font-size: 0.7rem;"></i>
                                </a>
                            </div>
                        </td>
                        
                        <!-- Manage Actions -->
                        <td>
                            <?php if($user['id'] !== $_SESSION['user_id']): ?>
                                <div style="display: flex; gap: 4px; flex-wrap: wrap; max-width: 250px;">
                                    
                                    <!-- Role Toggle -->
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if($user['role'] === 'admin'): ?>
                                            <input type="hidden" name="new_role" value="user">
                                            <button class="btn-outline" style="padding: 4px 8px; font-size: 0.75rem; border-color: var(--border-color); color: var(--text-muted);" title="Demote to User">Make User</button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_role" value="admin">
                                            <button class="btn-outline" style="padding: 4px 8px; font-size: 0.75rem; border-color: rgba(59, 130, 246, 0.5); color: #3B82F6;" onclick="return confirm('Grant full Administrative Privileges to this user?');">Make Admin</button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Block Toggle -->
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="toggle_block">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if(isset($user['status']) && $user['status'] === 'blocked'): ?>
                                            <input type="hidden" name="new_status" value="active">
                                            <button class="btn-outline" style="padding: 4px 8px; font-size: 0.75rem; border-color: #10B981; color: #10B981;">Unblock</button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="blocked">
                                            <button class="btn-outline" style="padding: 4px 8px; font-size: 0.75rem; border-color: #F59E0B; color: #F59E0B;" onclick="return confirm('Block this user? They will not be able to log in.');">Block</button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Delete -->
                                    <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete this user? Their account and entire booking history will be erased entirely.');" style="margin: 0;">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="icon-btn" style="color: var(--danger-color); border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.1); width: 28px; height: 28px; border-radius: 4px;" title="Delete User">
                                            <i class="fa-solid fa-trash" style="font-size: 0.8rem;"></i>
                                        </button>
                                    </form>
                                    
                                </div>
                            <?php else: ?>
                                <span style="color: #10B981; font-size: 0.8rem; font-weight: 700; background: rgba(16, 185, 129, 0.1); padding: 6px 12px; border-radius: 50px; border: 1px solid rgba(16, 185, 129, 0.3);">
                                    <i class="fa-solid fa-circle-check"></i> Current Session
                                </span>
                            <?php endif; ?>
                        </td>
                        
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
