<?php
// booking.php collects booking requirements (no cart flow).
require_once 'config/db.php';
require_once 'includes/csrf.php';

// 1. Session check: Ensure the user is actually logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If they are NOT logged in, we cannot book a service for them. 
// Redirect them to the login page first.
if (!isset($_SESSION['user_id'])) {
    // Add a message to the URL so we could potentially show it on the login page (optional feature)
    header('Location: login.php?msg=login_required');
    exit();
}

// Security Admin block removed to allow user-side flow testing

// 2. Variables setup
$error = '';
$success = '';
$service_id = null;
$service_details = null;
$tech_details = null;
$tech_id = null;

// Load states for manual dropdown-based address system
$states = [];
try {
    $states = $pdo->query("SELECT id, state_name FROM states ORDER BY state_name ASC")->fetchAll();
} catch (PDOException $e) {
    $states = [];
}

// 3. Fetch the specific service details they clicked on
if (isset($_GET['service_id'])) {
    $service_id = (int)$_GET['service_id'];
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service_details = $stmt->fetch();
    if (!$service_details) {
        $error = "The selected service does not exist.";
    }
} else {
    header('Location: services.php');
    exit();
}

// 3b. Fetch technician if tech_id provided
if (isset($_GET['tech_id'])) {
    $tech_id = (int)$_GET['tech_id'];
    $t_stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = ? AND service_id = ? AND deleted_at IS NULL LIMIT 1");
    $t_stmt->execute([$tech_id, $service_id]);
    $tech_details = $t_stmt->fetch();
    if (!$tech_details) {
        // Invalid tech_id — send back to technician selection
        header('Location: technicians.php?service_id=' . $service_id);
        exit();
    }
} else {
    // No tech selected — redirect to technician selection (required step)
    header('Location: technicians.php?service_id=' . $service_id);
    exit();
}

function generateFullAddress(string $flatNo, string $area, string $city, string $state, string $pincode): string {
    $parts = array_filter([
        trim($flatNo),
        trim($area),
        trim($city),
        trim($state)
    ]);
    $base = implode(', ', $parts);
    $pin = trim($pincode);
    return $base . ($pin !== '' ? (' - ' . $pin) : '');
}

function serviceSupportsCity(PDO $pdo, int $serviceId, int $cityId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_available_cities WHERE service_id = ? AND city_id = ?");
    $stmt->execute([$serviceId, $cityId]);
    return (int)$stmt->fetchColumn() > 0;
}

// 4. Handle the form submission when they click "Continue"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $service_details) {
    
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
        // Gather all form data
        $service_date = trim($_POST['service_date'] ?? '');
        $service_time = trim($_POST['service_time'] ?? '');
        $technician_count = (int)($_POST['technician_count'] ?? 1);
        
        // tech_id comes from a hidden input in the form
        if (isset($_POST['tech_id'])) {
            $tech_id = (int)$_POST['tech_id'];
        }

        $problem_desc = trim($_POST['problem_description'] ?? '');
        $emergency_ui = $_POST['emergency_level'] ?? 'Normal';
        $emergency_level = $emergency_ui === 'High' ? 'High' : 'Medium'; // Map UI "Normal" -> DB "Medium"

        $state_id = (int)($_POST['state_id'] ?? 0);
        $city_id = (int)($_POST['city_id'] ?? 0);
        $area = trim($_POST['area'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $flat_no = trim($_POST['flat_no'] ?? '');
        $landmark = trim($_POST['landmark'] ?? '');

        $contact = trim($_POST['contact'] ?? '');
        $user_id = (int)$_SESSION['user_id'];
    
        // Validation
        if ($technician_count < 1 || $technician_count > 3) {
            $error = "Please select technician quantity between 1 and 3.";
        } elseif ($service_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $service_date)) {
            $error = "Please select a valid date.";
        } elseif ($service_time === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $service_time)) {
            $error = "Please select a valid time slot.";
        } elseif ($state_id <= 0) {
            $error = "Please select a state.";
        } elseif ($city_id <= 0) {
            $error = "Please select a city.";
        } elseif ($area === '' || $flat_no === '') {
            $error = "Please fill out area/society and flat/house number.";
        } elseif (!preg_match('/^\d{6}$/', $pincode)) {
            $error = "Pincode must be 6 digits.";
        } elseif ($contact === '') {
            $error = "Please fill out contact number.";
        } else {
            try {
                // Validate state->city relationship and fetch names
                $loc = $pdo->prepare(
                    "SELECT st.state_name, c.city_name
                     FROM cities c
                     JOIN states st ON st.id = c.state_id
                     WHERE c.id = ? AND st.id = ?
                     LIMIT 1"
                );
                $loc->execute([$city_id, $state_id]);
                $locRow = $loc->fetch();
                if (!$locRow) {
                    $error = "Invalid location selection. Please select state and city again.";
                } else {
                    $state_name = (string)$locRow['state_name'];
                    $city_name = (string)$locRow['city_name'];
                    $full_address = generateFullAddress($flat_no, $area, $city_name, $state_name, $pincode);

                    // Service area restriction (service_available_cities)
                    if (!serviceSupportsCity($pdo, (int)$service_id, $city_id)) {
                        $error = "Service not available in your selected city.";
                    } else {
                        // Check if technician is already booked for this slot
                        $conflictStmt = $pdo->prepare(
                            "SELECT COUNT(*) FROM bookings
                             WHERE technician_id = ? AND service_date = ? AND service_time = ?
                               AND status NOT IN ('Cancelled')"
                        );
                        $conflictStmt->execute([$tech_id, $service_date, $service_time]);
                        if ((int)$conflictStmt->fetchColumn() > 0) {
                            $error = "This time slot is already booked for the selected provider. Please choose another slot.";
                        } else {
                        $_SESSION['pending_booking'] = [
                            'user_id'            => $user_id,
                            'service_id'         => (int)$service_id,
                            'technician_id'      => (int)$tech_id,
                            'technician_count'   => $technician_count,
                            'service_date'       => $service_date,
                            'service_time'       => $service_time,
                            'emergency_level'    => $emergency_level,
                            'problem_description'=> $problem_desc,
                            'contact'            => $contact,

                            'state'       => $state_name,
                            'city'        => $city_name,
                            'area'        => $area,
                            'pincode'     => $pincode,
                            'flat_no'     => $flat_no,
                            'landmark'    => $landmark,
                            'full_address'=> $full_address,
                        ];
                        unset($_SESSION['applied_coupon']);
                        header('Location: payment.php');
                        exit();
                        } // End else tech conflict
                    }
                }
            } catch (PDOException $e) {
                $error = "Location/slots are not configured yet. Please try again later.";
            }
        }
    }
}

// Load UI
include 'includes/header.php';

// NEW: Map session location to state/city for pre-selection
$session_location = $_SESSION['location'] ?? '';
$preselected_state_id = 0;
$preselected_city_id = 0;

if ($session_location) {
    try {
        $loc_stmt = $pdo->prepare("
            SELECT st.id as state_id, c.id as city_id 
            FROM cities c 
            JOIN states st ON st.id = c.state_id 
            WHERE c.city_name = ? LIMIT 1
        ");
        $loc_stmt->execute([$session_location]);
        $loc_row = $loc_stmt->fetch();
        if ($loc_row) {
            $preselected_state_id = (int)$loc_row['state_id'];
            $preselected_city_id = (int)$loc_row['city_id'];
        }
    } catch (Exception $e) {}
}
?>

<!-- Booking Form UI -->
<section class="container" style="padding: 60px 20px;">
    
    <div class="form-container" style="max-width: 700px;">
        
        <!-- Technician Mini-Profile Banner -->
        <?php if ($tech_details): ?>
        <div style="display: flex; align-items: center; gap: 16px; background: linear-gradient(135deg, #f0f4ff, #e8f0fe); border-radius: 16px; padding: 16px 20px; margin-bottom: 24px; border: 1px solid #c7d7fc;">
            <?php $hasPhoto = !empty($tech_details['photo']) && file_exists(__DIR__ . '/' . $tech_details['photo']); ?>
            <?php if ($hasPhoto): ?>
                <img src="<?= htmlspecialchars($tech_details['photo']) ?>" alt="<?= htmlspecialchars($tech_details['name']) ?>"
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.15); flex-shrink: 0;">
            <?php else: ?>
                <!-- Random portrait placeholder -->
                <img src="https://randomuser.me/api/portraits/men/<?php echo ($tech_details['id'] % 90) + 1; ?>.jpg" alt="<?= htmlspecialchars($tech_details['name']) ?>"
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.15); flex-shrink: 0;">
            <?php endif; ?>
            <div style="flex: 1;">
                <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main);"><?= htmlspecialchars($tech_details['name']) ?></div>
                <div style="font-size: 0.8rem; color: var(--accent-color); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin: 2px 0;">
                    <?= htmlspecialchars($tech_details['specialty'] ?: 'Professional') ?>
                </div>
                <div style="font-size: 0.85rem; color: #F59E0B;">
                    <?php $r = round((float)($tech_details['rating'] ?? 4.5)); for ($i=1;$i<=5;$i++) echo $i<=$r?'★':'☆'; ?>
                    <span style="color: var(--text-muted); margin-left: 4px;"><?= number_format((float)($tech_details['rating']??4.5),1) ?> · <?= (int)($tech_details['total_reviews']??0) ?> reviews</span>
                </div>
            </div>
            <a href="technicians.php?service_id=<?= (int)$service_id ?>" style="font-size: 0.82rem; color: var(--primary-color); font-weight: 600; text-decoration: none; white-space: nowrap;">
                ← Change
            </a>
        </div>
        <?php endif; ?>

        <!-- Header area showing what they are booking -->
        <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 24px; margin-bottom: 32px; text-align: center;">
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 8px;">Complete Booking</h2>
            
            <?php if($service_details): ?>
                <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 8px;">
                    Service: <strong><?php echo htmlspecialchars($service_details['title']); ?></strong>
                </p>
                <div style="display: inline-block; padding: 6px 16px; background: #FAFAFA; border: 1px solid var(--border-color); border-radius: 50px; font-weight: 700; color: var(--text-main);">
                    Estimated: ₹<?php echo number_format($service_details['price'], 2); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Display error box if validation fails -->
        <?php if(!empty($error)): ?>
            <div style="background-color: #FEF2F2; color: var(--danger-color); padding: 16px; border-radius: var(--border-radius-sm); margin-bottom: 24px; font-size: 0.95rem; border: 1px solid #FECACA;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Only show the form if we successfully found the service details -->
        <?php if($service_details): ?>
            
            <form action="" method="POST">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <?php if ($tech_id): ?>
                    <input type="hidden" name="tech_id" value="<?php echo (int)$tech_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="service_date">Select Date</label>
                    <input type="date" id="service_date" name="service_date" required class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>"
                           style="font-size: 1rem; padding: 12px 14px; border-radius: 12px; border: 1.5px solid var(--border-color); width: 100%; background: white; cursor: pointer;">
                </div>

                <!-- Hidden input that stores the chosen slot value -->
                <input type="hidden" id="service_time" name="service_time" required>

                <!-- Slot picker: shows after a date is selected -->
                <div id="slot-section" class="form-group" style="display: none;">
                    <label style="display:block; margin-bottom: 12px; font-weight: 600;">Select Time Slot</label>
                    <div id="slot-grid" style="display: flex; flex-wrap: wrap; gap: 10px;"></div>
                    <p id="slot-message" style="color: var(--text-muted); font-size: 0.9rem; margin-top: 8px; display: none;"></p>
                </div>

                <!-- Loading indicator -->
                <div id="slot-loading" style="display: none; padding: 12px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                    <i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i> Loading available slots...
                </div>

                <script>
                (function() {
                    const dateEl  = document.getElementById('service_date');
                    const timeEl  = document.getElementById('service_time');
                    const section = document.getElementById('slot-section');
                    const grid    = document.getElementById('slot-grid');
                    const loading = document.getElementById('slot-loading');
                    const msg     = document.getElementById('slot-message');
                    const serviceId = <?php echo (int)$service_id; ?>;
                    const techId    = <?php echo (int)$tech_id; ?>;

                    dateEl.addEventListener('change', async function () {
                        const date = dateEl.value;
                        if (!date) return;

                        // Reset
                        timeEl.value = '';
                        grid.innerHTML = '';
                        msg.style.display = 'none';
                        section.style.display = 'none';
                        loading.style.display = 'block';

                        try {
                            const res = await fetch(`api/available_slots.php?service_id=${serviceId}&tech_id=${techId}&date=${date}`);
                            const data = await res.json();

                            loading.style.display = 'none';
                            section.style.display = 'block';

                            if (!data.ok || !data.slots || data.slots.length === 0) {
                                msg.textContent = 'No slots available for this date.';
                                msg.style.display = 'block';
                                return;
                            }

                            data.slots.forEach(slot => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.textContent = slot.display;
                                btn.dataset.time = slot.time;

                                if (!slot.available) {
                                    // Booked slot — grayed out, not clickable
                                    btn.disabled = true;
                                    btn.style.cssText = `
                                        padding: 10px 18px; border-radius: 10px;
                                        border: 1.5px solid #e5e7eb; background: #f3f4f6;
                                        color: #9ca3af; font-size: 0.9rem; font-weight: 500;
                                        cursor: not-allowed; position: relative;`;
                                    btn.title = 'Already booked';

                                    // Strike-through text
                                    btn.innerHTML = `<span style="text-decoration: line-through;">${slot.display}</span> <small style="display:block; font-size:0.7rem; color:#d1d5db;">Booked</small>`;
                                } else {
                                    // Available slot
                                    btn.style.cssText = `
                                        padding: 10px 18px; border-radius: 10px;
                                        border: 1.5px solid var(--primary-color); background: white;
                                        color: var(--primary-color); font-size: 0.9rem; font-weight: 600;
                                        cursor: pointer; transition: all 0.2s;`;

                                    btn.onmouseover = () => {
                                        if (!btn.classList.contains('selected')) {
                                            btn.style.background = '#EBF0FB';
                                        }
                                    };
                                    btn.onmouseout = () => {
                                        if (!btn.classList.contains('selected')) {
                                            btn.style.background = 'white';
                                        }
                                    };

                                    btn.addEventListener('click', function () {
                                        // Deselect all
                                        grid.querySelectorAll('button.selected').forEach(b => {
                                            b.classList.remove('selected');
                                            b.style.background = 'white';
                                            b.style.color = 'var(--primary-color)';
                                            b.style.borderColor = 'var(--primary-color)';
                                        });
                                        // Select this one
                                        btn.classList.add('selected');
                                        btn.style.background = 'var(--primary-color)';
                                        btn.style.color = 'white';
                                        btn.style.borderColor = 'var(--primary-color)';
                                        timeEl.value = slot.time;
                                    });
                                }
                                grid.appendChild(btn);
                            });

                        } catch (e) {
                            loading.style.display = 'none';
                            section.style.display = 'block';
                            msg.textContent = 'Failed to load slots. Please try again.';
                            msg.style.display = 'block';
                        }
                    });
                })();
                </script>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 20px; margin-top: 24px;">
                    <div>
                        <label for="technician_count" style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">Technician Quantity</label>
                        <select id="technician_count" name="technician_count" required class="form-control">
                            <option value="1" selected>1 Technician</option>
                            <option value="2">2 Technicians</option>
                            <option value="3">3 Technicians</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="problem_description">Problem Description</label>
                    <textarea id="problem_description" name="problem_description" rows="3" required class="form-control" style="resize: vertical;" placeholder="Describe the issue..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 20px;">
                    <div>
                        <label for="emergency_level" style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">Emergency Level</label>
                        <select id="emergency_level" name="emergency_level" required class="form-control">
                            <option value="Normal" selected>Normal</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div>
                        <label for="contact" style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">Contact Number</label>
                        <input type="tel" id="contact" name="contact" required placeholder="e.g. 9876543210" class="form-control">
                    </div>
                </div>

                <div style="margin-top: 10px; margin-bottom: 6px; font-weight: 800;">Service Address</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="state_id">State <span style="color: var(--danger-color);">*</span></label>
                        <select id="state_id" name="state_id" required class="form-control">
                            <option value="">Select state</option>
                            <?php foreach($states as $st): ?>
                                <option value="<?php echo (int)$st['id']; ?>" <?php echo ($preselected_state_id == $st['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($st['state_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="city_id">City <span style="color: var(--danger-color);">*</span></label>
                        <select id="city_id" name="city_id" required class="form-control" disabled>
                            <option value="">Select city</option>
                        </select>
                        <div id="cityHelp" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 6px;"></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 18px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="area">Area / Society Name <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" id="area" name="area" required class="form-control" placeholder="e.g. Green Valley Society">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="pincode">Pincode <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" id="pincode" name="pincode" required class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit pincode">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 18px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="flat_no">Flat / House Number <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" id="flat_no" name="flat_no" required class="form-control" placeholder="e.g. Flat 302 / H-12">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="landmark">Landmark (optional)</label>
                        <input type="text" id="landmark" name="landmark" class="form-control" placeholder="e.g. Near City Mall">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 16px; width: 100%; font-size: 1.05rem; padding: 16px;">
                    Continue
                </button>
                
            </form>

            <script>
                (function() {
                    const stateEl = document.getElementById('state_id');
                    const cityEl = document.getElementById('city_id');
                    const helpEl = document.getElementById('cityHelp');

                    function setHelp(msg) {
                        helpEl.textContent = msg || '';
                    }

                    async function loadCities(stateId) {
                        setHelp('Loading cities…');
                        cityEl.innerHTML = '<option value="">Select city</option>';
                        cityEl.disabled = true;
                        const res = await fetch('api/cities.php?state_id=' + encodeURIComponent(stateId), {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (!data.ok) throw new Error(data.error || 'Unable to load cities');
                        for (const c of data.cities) {
                            const opt = document.createElement('option');
                            opt.value = c.id;
                            opt.textContent = c.city_name;
                            cityEl.appendChild(opt);
                        }
                        cityEl.disabled = false;
                        setHelp(data.cities.length ? '' : 'No cities found for selected state.');
                    }

                    stateEl.addEventListener('change', async function() {
                        const stateId = stateEl.value;
                        cityEl.value = '';
                        if (!stateId) {
                            cityEl.disabled = true;
                            cityEl.innerHTML = '<option value="">Select city</option>';
                            setHelp('');
                            return;
                        }
                        try {
                            await loadCities(stateId);
                        } catch (e) {
                            setHelp(e.message || 'Unable to load cities.');
                        }
                    });

                    // Auto-load cities if state is pre-selected
                    if (stateEl.value) {
                        loadCities(stateEl.value).then(() => {
                            const preCityId = "<?php echo $preselected_city_id; ?>";
                            if (preCityId > 0) {
                                cityEl.value = preCityId;
                            }
                        });
                    }
                })();
            </script>
            
        <?php endif; ?>
        
    </div>
</section>

<?php include 'includes/footer.php'; ?>
