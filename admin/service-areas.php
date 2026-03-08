<?php
require_once '../config/db.php';
include 'includes/header.php';

$success = '';
$error = '';

// Load services/states upfront
$services = [];
$states = [];
try {
    $services = $pdo->query("SELECT id, title FROM services WHERE deleted_at IS NULL ORDER BY title ASC")->fetchAll();
} catch(PDOException $e) { $services = []; }

try {
    $states = $pdo->query("SELECT id, state_name FROM states ORDER BY state_name ASC")->fetchAll();
} catch(PDOException $e) { $states = []; }

$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : (int)($_POST['service_id'] ?? 0);

// Save mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_service_areas') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
        $service_id = (int)$_POST['service_id'];
        $city_ids = $_POST['city_ids'] ?? [];
        if ($service_id <= 0) {
            $error = "Please select a service.";
        } else {
            $city_ids = array_values(array_filter(array_map('intval', is_array($city_ids) ? $city_ids : [])));
            try {
                $pdo->beginTransaction();

                $pdo->prepare("DELETE FROM service_available_cities WHERE service_id = ?")->execute([$service_id]);

                if (!empty($city_ids)) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO service_available_cities (service_id, city_id) VALUES (?, ?)");
                    foreach ($city_ids as $cid) {
                        if ($cid > 0) $ins->execute([$service_id, $cid]);
                    }
                }

                $pdo->commit();
                $success = "Supported cities updated successfully.";
            } catch(PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Failed to update supported cities.";
            }
        }
    }
}

// Load currently selected cities for chosen service
$selected = [];
if ($service_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT city_id FROM service_available_cities WHERE service_id = ?");
        $stmt->execute([$service_id]);
        $selected = array_map('intval', array_column($stmt->fetchAll(), 'city_id'));
    } catch(PDOException $e) {
        $selected = [];
    }
}

// Fetch all cities grouped by state
$citiesByState = [];
try {
    $rows = $pdo->query("SELECT c.id, c.city_name, c.state_id FROM cities c ORDER BY c.city_name ASC")->fetchAll();
    foreach ($rows as $r) {
        $sid = (int)$r['state_id'];
        if (!isset($citiesByState[$sid])) $citiesByState[$sid] = [];
        $citiesByState[$sid][] = $r;
    }
} catch(PDOException $e) {
    $citiesByState = [];
}
?>

<div style="display:flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="margin: 0; font-weight: 800;">Service Areas</h1>
        <p style="color: var(--text-muted); margin: 4px 0 0 0;">Control which cities each service is available in.</p>
    </div>
</div>

<?php if($success): ?>
    <div style="background-color: rgba(16, 185, 129, 0.1); color: #10B981; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.3);">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>
<?php if($error): ?>
    <div style="background-color: rgba(239, 68, 68, 0.1); color: var(--danger-color); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.3);">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="chart-box" style="padding: 20px;">
    <form method="POST">
        <input type="hidden" name="action" value="save_service_areas">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

        <div class="form-group" style="margin-bottom: 16px;">
            <label for="service_id">Service</label>
            <select id="service_id" name="service_id" class="form-control" onchange="this.form.submit()">
                <option value="0">Select a service</option>
                <?php foreach($services as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php if($service_id === (int)$s['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($s['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 6px;">
                After selecting a service, check the cities it supports, then click “Save”.
            </div>
        </div>

        <?php if($service_id > 0): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-top: 10px;">
                <?php foreach($states as $st): ?>
                    <div style="border: 1px solid var(--border-color); border-radius: 12px; padding: 14px; background: var(--bg-color);">
                        <div style="font-weight: 800; margin-bottom: 10px;"><?php echo htmlspecialchars($st['state_name']); ?></div>
                        <?php $list = $citiesByState[(int)$st['id']] ?? []; ?>
                        <?php if(empty($list)): ?>
                            <div style="color: var(--text-muted); font-size: 0.9rem;">No cities in this state.</div>
                        <?php else: ?>
                            <div style="display:flex; flex-direction: column; gap: 8px;">
                                <?php foreach($list as $c): ?>
                                    <label style="display:flex; gap: 10px; align-items: center; font-weight: 600; color: var(--text-main);">
                                        <input type="checkbox" name="city_ids[]" value="<?php echo (int)$c['id']; ?>" <?php if(in_array((int)$c['id'], $selected, true)) echo 'checked'; ?>>
                                        <?php echo htmlspecialchars($c['city_name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex; justify-content: flex-end; margin-top: 18px;">
                <button type="submit" class="btn-primary">Save Supported Cities</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

