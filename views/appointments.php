<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin();

// Ensure 'cancelled' and 'completed' exist in appointments.status ENUM on TiDB/MySQL
try {
    $pdo->exec("ALTER TABLE appointments MODIFY COLUMN status ENUM('scheduled', 'arrived', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled'");
} catch (Throwable $e) {
    // Ignore if already configured or restricted
}

$countryRules = [
    '+91'  => ['label' => '+91 (India)',     'digits' => 10],
    '+1'   => ['label' => '+1 (USA/Canada)', 'digits' => 10],
    '+44'  => ['label' => '+44 (UK)',        'digits' => 10],
    '+971' => ['label' => '+971 (UAE)',      'digits' => 9],
    '+61'  => ['label' => '+61 (Australia)', 'digits' => 9],
];

$message = '';
$error = '';

$hosts = $pdo->query("SELECT user_id, name, role FROM users ORDER BY name ASC")->fetchAll();

// 1. Handle New Appointment Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_appointment'])) {
    $name           = trim($_POST['name'] ?? '');
    $countryCode    = $_POST['country_code'] ?? '+91';
    $rawPhone       = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $organization   = trim($_POST['organization'] ?? '');
    $hostSelection  = $_POST['host_id'] ?? '';
    $customHostName = trim($_POST['custom_host_name'] ?? '');
    $visitDate      = $_POST['visit_date'] ?? '';
    $purpose        = trim($_POST['purpose'] ?? '');

    $isOtherHost = ($hostSelection === 'other');
    $hostId      = $isOtherHost ? null : (int)$hostSelection;
    $validHost   = $isOtherHost ? ($customHostName !== '') : ($hostId > 0);

    $expectedDigits = $countryRules[$countryCode]['digits'] ?? 10;

    if (!isset($countryRules[$countryCode]) || strlen($rawPhone) !== $expectedDigits) {
        $error = "Invalid phone number: $countryCode requires strictly $expectedDigits numeric digits.";
    } elseif ($name && $validHost && $visitDate && $purpose) {
        $phone = $countryCode . ' ' . $rawPhone;

        // Check blacklist against both formatted ("+91 9876543210") and raw ("9876543210")
        $stmt = $pdo->prepare("SELECT visitor_id, is_blacklisted FROM visitors WHERE phone = ? OR phone = ?");
        $stmt->execute([$phone, $rawPhone]);
        $existing = $stmt->fetch();

        if ($existing && (int)$existing['is_blacklisted'] === 1) {
            $error = "BLOCKED: This visitor ($phone) is blacklisted and cannot schedule an appointment.";
            logAudit($pdo, (int)$_SESSION['user_id'], "Blocked appointment attempt for blacklisted visitor: $name ($phone)");
        } else {
            if ($existing) {
                $visitorId = (int)$existing['visitor_id'];
            } else {
                $insVis = $pdo->prepare("INSERT INTO visitors (name, phone, email, organization) VALUES (?, ?, ?, ?)");
                $insVis->execute([$name, $phone, $email, $organization]);
                $visitorId = (int)$pdo->lastInsertId();
            }

            $insApp = $pdo->prepare("
                INSERT INTO appointments (visitor_id, host_id, custom_host_name, visit_date, purpose, status)
                VALUES (?, ?, ?, ?, ?, 'scheduled')
            ");
            $insApp->execute([
                $visitorId,
                $hostId,
                $isOtherHost ? $customHostName : null,
                $visitDate,
                $purpose
            ]);
            $appId = (int)$pdo->lastInsertId();

            if (!$isOtherHost && $hostId) {
                $notifMsg = "Scheduled Appointment #$appId: $name ($organization) on $visitDate. Purpose: $purpose";
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$hostId, $notifMsg]);
            }

            logAudit($pdo, (int)$_SESSION['user_id'], "Scheduled Appointment #$appId for $name");
            $message = "Appointment #$appId scheduled successfully for $visitDate!";
        }
    } else {
        $error = "Please complete all required appointment fields, including the host name if 'Other' is selected.";
    }
}

// 2. Handle Appointment Actions (Convert to Active Visit / Cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['app_action'])) {
    $appId   = (int)$_POST['appointment_id'];
    $action  = trim($_POST['app_action']);
    $staffId = (int)$_SESSION['user_id'];

    if ($action === 'arrived') {
        $aStmt = $pdo->prepare("
            SELECT a.*, vis.name AS visitor_name
            FROM appointments a
            JOIN visitors vis ON a.visitor_id = vis.visitor_id
            WHERE a.appointment_id = ? AND a.status = 'scheduled'
        ");
        $aStmt->execute([$appId]);
        $app = $aStmt->fetch();

        if ($app) {
            $insVisit = $pdo->prepare("
                INSERT INTO visits (visitor_id, host_id, custom_host_name, purpose, status, approved_by)
                VALUES (?, ?, ?, ?, 'approved', ?)
            ");
            $insVisit->execute([
                $app['visitor_id'],
                $app['host_id'],
                $app['custom_host_name'],
                $app['purpose'],
                $staffId
            ]);
            $visitId = (int)$pdo->lastInsertId();

            $pythonExe = PHP_OS_FAMILY === 'Windows'
                ? __DIR__ . '/../venv/Scripts/python.exe'
                : '/var/www/html/venv/bin/python';
            $script    = __DIR__ . '/../python_services/make_qr.py';
            $cmd       = "\"$pythonExe\" \"$script\" " . escapeshellarg((string)$visitId) . " " . escapeshellarg($app['visitor_name']) . " 2>&1";
            $qrOutput  = trim((string)shell_exec($cmd));
            $qrPath    = (strpos($qrOutput, 'assets/qr_codes/') === 0) ? $qrOutput : null;

            if ($qrPath) {
                $pdo->prepare("UPDATE visits SET qr_code_path = ? WHERE visit_id = ?")->execute([$qrPath, $visitId]);
            }

            $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ?")->execute([$appId]);
            logAudit($pdo, $staffId, "Converted Appointment #$appId into Visit #$visitId with QR Pass");
            $message = "Visitor arrived! Appointment #$appId converted to Approved Visit #$visitId with QR Pass.";
        }
    } elseif ($action === 'cancel') {
        $upd = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND status = 'scheduled'");
        $upd->execute([$appId]);
        if ($upd->rowCount() > 0) {
            logAudit($pdo, $staffId, "Cancelled Appointment #$appId");
            $message = "Appointment #$appId has been cancelled.";
        } else {
            $error = "Unable to cancel Appointment #$appId (it may already be completed or cancelled).";
        }
    }
}

$appointments = $pdo->query("
    SELECT a.*, vis.name AS visitor_name, vis.phone, vis.organization,
           COALESCE(u.name, a.custom_host_name) AS host_name
    FROM appointments a
    JOIN visitors vis ON a.visitor_id = vis.visitor_id
    LEFT JOIN users u ON a.host_id = u.user_id
    ORDER BY a.visit_date DESC
")->fetchAll();

renderPageStart('Appointments');
?>

<?php if ($error): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($message): ?>
    <div class="alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 1.75rem;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Pre-Schedule a Visit</h2>
    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.25rem;">Book an upcoming visit so gate security can fast-track entry on arrival.</p>

    <form method="POST" action="">
        <input type="hidden" name="create_appointment" value="1">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label>Visitor Full Name *</label>
                <input type="text" name="name" placeholder="e.g., Ananya Singh" required>
            </div>
            <div class="form-group">
                <label>Phone Number *</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select id="app_country_code" name="country_code" style="width: 145px; flex-shrink: 0;">
                        <?php foreach ($countryRules as $code => $meta): ?>
                            <option value="<?= htmlspecialchars($code) ?>" data-digits="<?= $meta['digits'] ?>" <?= $code === '+91' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($meta['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="tel" id="app_phone" name="phone" inputmode="numeric"
                           maxlength="10" minlength="10" pattern="[0-9]{10}"
                           placeholder="10-digit number" required
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, this.maxLength);"
                           style="flex: 1;">
                </div>
                <small id="app_phone_hint" style="color: var(--muted); font-size: 0.78rem;">Requires strictly 10 digits for +91</small>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="guest@company.com">
            </div>
            <div class="form-group">
                <label>Organization</label>
                <input type="text" name="organization" placeholder="e.g., TCS / Parent">
            </div>
            <div class="form-group">
                <label for="app_host_id">Host to Meet *</label>
                <select id="app_host_id" name="host_id" required>
                    <option value="">-- Select Host --</option>
                    <?php foreach ($hosts as $h): ?>
                        <option value="<?= $h['user_id'] ?>"><?= htmlspecialchars($h['name']) ?> (<?= ucfirst($h['role']) ?>)</option>
                    <?php endforeach; ?>
                    <option value="other">Other (Enter Name Manually)</option>
                </select>
            </div>
            <div class="form-group" id="appCustomHostGroup" style="display: none;">
                <label for="app_custom_host_name">Name of Person to Meet *</label>
                <input type="text" id="app_custom_host_name" name="custom_host_name" placeholder="Enter host's full name">
            </div>
            <div class="form-group">
                <label>Scheduled Date &amp; Time *</label>
                <input type="datetime-local" name="visit_date" required>
            </div>
        </div>
        <div class="form-group">
            <label>Purpose of Visit *</label>
            <input type="text" name="purpose" placeholder="e.g., Campus Placement Drive / Guest Lecture" required>
        </div>
        <button type="submit" class="btn">Book Appointment</button>
    </form>
</div>

<div class="card">
    <h3 style="font-size: 1.05rem;">Scheduled &amp; Past Appointments</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Visitor</th>
                    <th>Host</th>
                    <th>Scheduled Time</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--muted);">No appointments scheduled yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td><strong>#<?= $a['appointment_id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($a['visitor_name']) ?></strong><br><small style="color:var(--muted)"><?= htmlspecialchars($a['phone']) ?></small></td>
                            <td><?= htmlspecialchars($a['host_name']) ?></td>
                            <td><?= htmlspecialchars($a['visit_date']) ?></td>
                            <td><?= htmlspecialchars($a['purpose']) ?></td>
                            <td>
                                <span class="status status-<?= $a['status'] === 'scheduled' ? 'pending' : ($a['status'] === 'completed' ? 'approved' : 'rejected') ?>">
                                    <?= htmlspecialchars($a['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($a['status'] === 'scheduled'): ?>
                                    <form method="POST" style="display: inline-flex; gap: 0.35rem; flex-wrap: wrap;">
                                        <input type="hidden" name="appointment_id" value="<?= (int)$a['appointment_id'] ?>">
                                        <button type="submit" name="app_action" value="arrived" class="btn btn-sm btn-success">Mark Arrived &amp; Issue QR</button>
                                        <button type="submit" name="app_action" value="cancel" class="btn btn-sm btn-danger" onclick="return confirm('Cancel Appointment #<?= (int)$a['appointment_id'] ?>?');">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--muted); font-size: 0.82rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const appHostSelect = document.getElementById("app_host_id");
    const appCustomGroup = document.getElementById("appCustomHostGroup");
    const appCustomInput = document.getElementById("app_custom_host_name");

    appHostSelect.addEventListener("change", () => {
        if (appHostSelect.value === "other") {
            appCustomGroup.style.display = "block";
            appCustomInput.required = true;
            appCustomInput.focus();
        } else {
            appCustomGroup.style.display = "none";
            appCustomInput.required = false;
            appCustomInput.value = "";
        }
    });

    const appCountrySelect = document.getElementById("app_country_code");
    const appPhoneInput = document.getElementById("app_phone");
    const appPhoneHint = document.getElementById("app_phone_hint");

    appCountrySelect.addEventListener("change", () => {
        const opt = appCountrySelect.options[appCountrySelect.selectedIndex];
        const digits = parseInt(opt.getAttribute("data-digits") || "10", 10);
        appPhoneInput.maxLength = digits;
        appPhoneInput.minLength = digits;
        appPhoneInput.pattern = `[0-9]{${digits}}`;
        appPhoneInput.placeholder = `${digits}-digit number`;
        appPhoneInput.value = appPhoneInput.value.replace(/[^0-9]/g, "").slice(0, digits);
        appPhoneHint.textContent = `Requires strictly ${digits} digits for ${appCountrySelect.value}`;
    });
</script>

<?php renderPageEnd(); ?>