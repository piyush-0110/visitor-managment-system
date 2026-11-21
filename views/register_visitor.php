<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin();

$message = '';
$error = '';

$hosts = $pdo->query("SELECT user_id, name, role FROM users ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $organization   = trim($_POST['organization'] ?? '');
    $hostSelection  = $_POST['host_id'] ?? '';
    $customHostName = trim($_POST['custom_host_name'] ?? '');
    $purpose        = trim($_POST['purpose'] ?? '');

    $isOtherHost = ($hostSelection === 'other');
    $hostId      = $isOtherHost ? null : (int)$hostSelection;
    $validHost   = $isOtherHost ? ($customHostName !== '') : ($hostId > 0);

    if ($name && $phone && $validHost && $purpose) {
        $stmt = $pdo->prepare("SELECT visitor_id, is_blacklisted FROM visitors WHERE phone = ?");
        $stmt->execute([$phone]);
        $existing = $stmt->fetch();

        if ($existing && (int)$existing['is_blacklisted'] === 1) {
            $error = "SECURITY ALERT: This visitor is blacklisted and cannot be registered.";
            logAudit($pdo, (int)$_SESSION['user_id'], "Blocked blacklisted visitor attempt: $name ($phone)");
        } else {
            if ($existing) {
                $visitorId = (int)$existing['visitor_id'];
            } else {
                $insVis = $pdo->prepare("INSERT INTO visitors (name, phone, email, organization) VALUES (?, ?, ?, ?)");
                $insVis->execute([$name, $phone, $email, $organization]);
                $visitorId = (int)$pdo->lastInsertId();
            }

            $insVisit = $pdo->prepare("
                INSERT INTO visits (visitor_id, host_id, custom_host_name, purpose, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $insVisit->execute([
                $visitorId,
                $hostId,
                $isOtherHost ? $customHostName : null,
                $purpose
            ]);
            $visitId = (int)$pdo->lastInsertId();

            if (!$isOtherHost && $hostId) {
                $notifMsg = "New visitor $name ($organization) is waiting at reception. Purpose: $purpose";
                $insNotif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $insNotif->execute([$hostId, $notifMsg]);
            }

            $hostLogLabel = $isOtherHost ? "Custom Host: $customHostName" : "Host ID #$hostId";
            logAudit($pdo, (int)$_SESSION['user_id'], "Registered visitor #$visitorId ($name) for Visit #$visitId ($hostLogLabel)");
            $message = "Visitor registered successfully! Visit ID #$visitId is awaiting gate approval.";
        }
    } else {
        $error = "Please fill in all required fields, including the host name if 'Other' is selected.";
    }
}

renderPageStart('Register Visitor');
?>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">New Visitor Registration</h2>
    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Enter visitor details to generate an entry request.</p>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert-success">
            <?= htmlspecialchars($message) ?> — <a href="/views/manage_visits.php" style="color: inherit; text-decoration: underline;"><strong>Go to Gate Approvals</strong></a>
        </div>
    <?php endif; ?>

    <form id="visitorForm" method="POST" action="">
        <div class="form-group">
            <label for="name">Visitor Full Name *</label>
            <input type="text" id="name" name="name" placeholder="e.g., Rajesh Verma" required>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label for="phone">Phone Number *</label>
                <input type="text" id="phone" name="phone" placeholder="e.g., 9876543210" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="visitor@example.com">
            </div>
        </div>
        <div class="form-group">
            <label for="organization">Organization / College / Company</label>
            <input type="text" id="organization" name="organization" placeholder="e.g., Parent / TechCorp Pvt Ltd">
        </div>

        <div class="form-group">
            <label for="host_id">Person to Meet (Host) *</label>
            <select id="host_id" name="host_id" required>
                <option value="">-- Select Host --</option>
                <?php foreach ($hosts as $h): ?>
                    <option value="<?= $h['user_id'] ?>">
                        <?= htmlspecialchars($h['name']) ?> (<?= ucfirst($h['role']) ?>)
                    </option>
                <?php endforeach; ?>
                <option value="other">Other (Enter Name Manually)</option>
            </select>
        </div>

        <div class="form-group" id="customHostGroup" style="display: none;">
            <label for="custom_host_name">Name of Person to Meet *</label>
            <input type="text" id="custom_host_name" name="custom_host_name" placeholder="Enter full name of the person they are meeting">
        </div>

        <div class="form-group">
            <label for="purpose">Purpose of Visit *</label>
            <input type="text" id="purpose" name="purpose" placeholder="e.g., Admission Inquiry / Project Meeting" required>
        </div>
        <button type="submit" class="btn">Submit Registration</button>
    </form>
</div>

<script src="/assets/js/validation.js"></script>
<script>
    const hostSelect = document.getElementById("host_id");
    const customHostGroup = document.getElementById("customHostGroup");
    const customHostInput = document.getElementById("custom_host_name");

    hostSelect.addEventListener("change", () => {
        if (hostSelect.value === "other") {
            customHostGroup.style.display = "block";
            customHostInput.required = true;
            customHostInput.focus();
        } else {
            customHostGroup.style.display = "none";
            customHostInput.required = false;
            customHostInput.value = "";
        }
    });
</script>

<?php renderPageEnd(); ?>