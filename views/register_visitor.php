<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin();

$countryRules = [
    '+91'  => ['label' => '+91 (India)',          'digits' => 10],
    '+1'   => ['label' => '+1 (USA / Canada)',    'digits' => 10],
    '+44'  => ['label' => '+44 (United Kingdom)', 'digits' => 10],
    '+971' => ['label' => '+971 (UAE)',           'digits' => 9],
    '+966' => ['label' => '+966 (Saudi Arabia)',  'digits' => 9],
    '+974' => ['label' => '+974 (Qatar)',         'digits' => 8],
    '+965' => ['label' => '+965 (Kuwait)',        'digits' => 8],
    '+968' => ['label' => '+968 (Oman)',          'digits' => 8],
    '+973' => ['label' => '+973 (Bahrain)',       'digits' => 8],
    '+61'  => ['label' => '+61 (Australia)',      'digits' => 9],
    '+64'  => ['label' => '+64 (New Zealand)',    'digits' => 9],
    '+65'  => ['label' => '+65 (Singapore)',      'digits' => 8],
    '+60'  => ['label' => '+60 (Malaysia)',       'digits' => 9],
    '+81'  => ['label' => '+81 (Japan)',          'digits' => 10],
    '+82'  => ['label' => '+82 (South Korea)',    'digits' => 10],
    '+86'  => ['label' => '+86 (China)',          'digits' => 11],
    '+49'  => ['label' => '+49 (Germany)',        'digits' => 10],
    '+33'  => ['label' => '+33 (France)',         'digits' => 9],
    '+39'  => ['label' => '+39 (Italy)',          'digits' => 10],
    '+34'  => ['label' => '+34 (Spain)',          'digits' => 9],
    '+31'  => ['label' => '+31 (Netherlands)',    'digits' => 9],
    '+41'  => ['label' => '+41 (Switzerland)',    'digits' => 9],
    '+46'  => ['label' => '+46 (Sweden)',         'digits' => 9],
    '+7'   => ['label' => '+7 (Russia)',          'digits' => 10],
    '+55'  => ['label' => '+55 (Brazil)',         'digits' => 11],
    '+52'  => ['label' => '+52 (Mexico)',         'digits' => 10],
    '+27'  => ['label' => '+27 (South Africa)',   'digits' => 9],
    '+234' => ['label' => '+234 (Nigeria)',       'digits' => 10],
    '+254' => ['label' => '+254 (Kenya)',         'digits' => 9],
    '+20'  => ['label' => '+20 (Egypt)',          'digits' => 10],
    '+92'  => ['label' => '+92 (Pakistan)',       'digits' => 10],
    '+880' => ['label' => '+880 (Bangladesh)',    'digits' => 10],
    '+94'  => ['label' => '+94 (Sri Lanka)',      'digits' => 9],
    '+977' => ['label' => '+977 (Nepal)',         'digits' => 10],
    '+62'  => ['label' => '+62 (Indonesia)',      'digits' => 10],
    '+63'  => ['label' => '+63 (Philippines)',    'digits' => 10],
    '+66'  => ['label' => '+66 (Thailand)',       'digits' => 9],
    '+84'  => ['label' => '+84 (Vietnam)',        'digits' => 9],
    '+90'  => ['label' => '+90 (Turkey)',         'digits' => 10],
];

$message = '';
$error = '';

$hosts = $pdo->query("SELECT user_id, name, role FROM users ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $countryCode    = $_POST['country_code'] ?? '+91';
    $rawPhone       = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $organization   = trim($_POST['organization'] ?? '');
    $hostSelection  = $_POST['host_id'] ?? '';
    $customHostName = trim($_POST['custom_host_name'] ?? '');
    $purpose        = trim($_POST['purpose'] ?? '');

    $isOtherHost    = ($hostSelection === 'other');
    $hostId         = $isOtherHost ? null : (int)$hostSelection;
    $validHost      = $isOtherHost ? ($customHostName !== '') : ($hostId > 0);
    $expectedDigits = $countryRules[$countryCode]['digits'] ?? 10;

    if (!isset($countryRules[$countryCode]) || strlen($rawPhone) !== $expectedDigits) {
        $error = "Invalid phone number: $countryCode requires strictly $expectedDigits numeric digits.";
    } elseif ($name && $validHost && $purpose) {
        $phone = $countryCode . ' ' . $rawPhone;

        // Check blacklist against both formatted ("+91 9876543210") and legacy raw ("9876543210")
        $stmt = $pdo->prepare("SELECT visitor_id, is_blacklisted FROM visitors WHERE phone = ? OR phone = ?");
        $stmt->execute([$phone, $rawPhone]);
        $existing = $stmt->fetch();

        if ($existing && (int)$existing['is_blacklisted'] === 1) {
            $error = "SECURITY ALERT: This visitor ($phone) is blacklisted and cannot be registered.";
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

        <div class="form-group">
            <label for="phone">Phone Number *</label>
            <div style="display: flex; gap: 0.5rem;">
                <select id="reg_country_code" name="country_code" style="width: 155px; flex-shrink: 0;">
                    <?php foreach ($countryRules as $code => $meta): ?>
                        <option value="<?= htmlspecialchars($code) ?>" data-digits="<?= $meta['digits'] ?>" <?= $code === '+91' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($meta['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="tel" id="phone" name="phone" inputmode="numeric"
                       maxlength="10" minlength="10" pattern="[0-9]{10}"
                       placeholder="10-digit number" required
                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, this.maxLength);"
                       style="flex: 1;">
            </div>
            <small id="reg_phone_hint" style="color: var(--muted); font-size: 0.78rem;">Requires strictly 10 digits for +91</small>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="visitor@example.com">
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

    const regCountrySelect = document.getElementById("reg_country_code");
    const regPhoneInput    = document.getElementById("phone");
    const regPhoneHint     = document.getElementById("reg_phone_hint");

    regCountrySelect.addEventListener("change", () => {
        const opt = regCountrySelect.options[regCountrySelect.selectedIndex];
        const digits = parseInt(opt.getAttribute("data-digits") || "10", 10);
        regPhoneInput.maxLength = digits;
        regPhoneInput.minLength = digits;
        regPhoneInput.pattern = `[0-9]{${digits}}`;
        regPhoneInput.placeholder = `${digits}-digit number`;
        regPhoneInput.value = regPhoneInput.value.replace(/[^0-9]/g, "").slice(0, digits);
        regPhoneHint.textContent = `Requires strictly ${digits} digits for ${regCountrySelect.value}`;
    });
</script>

<?php renderPageEnd(); ?>