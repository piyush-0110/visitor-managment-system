<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin(['admin']);

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

$feedback = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visitor_id'])) {
    $visitorId = (int)$_POST['toggle_visitor_id'];
    $newStatus = (int)$_POST['new_status'];

    $stmt = $pdo->prepare("UPDATE visitors SET is_blacklisted = ? WHERE visitor_id = ?");
    $stmt->execute([$newStatus, $visitorId]);

    $actionText = $newStatus === 1 ? "Blacklisted visitor #$visitorId" : "Removed visitor #$visitorId from blacklist";
    logAudit($pdo, (int)$_SESSION['user_id'], $actionText);
    $feedback = $actionText . ".";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_blacklist'])) {
    $name           = trim($_POST['name'] ?? '');
    $countryCode    = $_POST['country_code'] ?? '+91';
    $rawPhone       = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $org            = trim($_POST['organization'] ?? '');
    $expectedDigits = $countryRules[$countryCode]['digits'] ?? 10;

    if (!isset($countryRules[$countryCode]) || strlen($rawPhone) !== $expectedDigits) {
        $error = "Invalid phone number: $countryCode requires strictly $expectedDigits numeric digits.";
    } elseif ($name) {
        $phone = $countryCode . ' ' . $rawPhone;

        // Match both formatted ("+91 9876543210") and legacy unformatted ("9876543210")
        $check = $pdo->prepare("SELECT visitor_id FROM visitors WHERE phone = ? OR phone = ?");
        $check->execute([$phone, $rawPhone]);
        $existingId = $check->fetchColumn();

        if ($existingId) {
            $pdo->prepare("UPDATE visitors SET is_blacklisted = 1, phone = ? WHERE visitor_id = ?")
                ->execute([$phone, $existingId]);
        } else {
            $pdo->prepare("INSERT INTO visitors (name, phone, organization, is_blacklisted) VALUES (?, ?, ?, 1)")
                ->execute([$name, $phone, $org]);
        }

        logAudit($pdo, (int)$_SESSION['user_id'], "Added $name ($phone) to security blacklist");
        $feedback = "$name ($phone) has been added to the security blacklist.";
    } else {
        $error = "Please enter the visitor's full name.";
    }
}

$visitors = $pdo->query("SELECT * FROM visitors ORDER BY is_blacklisted DESC, visitor_id DESC")->fetchAll();

renderPageStart('Security Blacklist');
?>

<?php if ($error): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($feedback): ?>
    <div class="alert-success"><?= htmlspecialchars($feedback) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 1.75rem;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Add Person to Security Blacklist</h2>
    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.25rem;">Flagged phone numbers are automatically blocked at reception and appointment booking.</p>

    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.9rem; align-items: end;">
        <input type="hidden" name="add_blacklist" value="1">
        <div>
            <label>Full Name *</label>
            <input type="text" name="name" placeholder="Full Name" required>
        </div>
        <div>
            <label>Phone Number *</label>
            <div style="display: flex; gap: 0.45rem;">
                <select id="bl_country_code" name="country_code" style="width: 145px; flex-shrink: 0;">
                    <?php foreach ($countryRules as $code => $meta): ?>
                        <option value="<?= htmlspecialchars($code) ?>" data-digits="<?= $meta['digits'] ?>" <?= $code === '+91' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($meta['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="tel" id="bl_phone" name="phone" inputmode="numeric"
                       maxlength="10" minlength="10" pattern="[0-9]{10}"
                       placeholder="10-digit number" required
                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, this.maxLength);"
                       style="flex: 1;">
            </div>
        </div>
        <div>
            <label>Organization / Notes</label>
            <input type="text" name="organization" placeholder="Reason or Organization">
        </div>
        <button type="submit" class="btn btn-danger">Blacklist Entry</button>
    </form>
    <small id="bl_phone_hint" style="display: block; margin-top: 0.45rem; color: var(--muted); font-size: 0.78rem;">Requires strictly 10 digits for +91</small>
</div>

<div class="card">
    <h3 style="font-size: 1.05rem;">Visitor Directory &amp; Access Status</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Visitor ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Organization</th>
                    <th>Security Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visitors)): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--muted);">No visitors in directory yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($visitors as $vis): ?>
                        <tr>
                            <td><strong>#<?= $vis['visitor_id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($vis['name']) ?></strong></td>
                            <td><?= htmlspecialchars($vis['phone']) ?></td>
                            <td><?= htmlspecialchars($vis['organization'] ?: '—') ?></td>
                            <td>
                                <?php if ((int)$vis['is_blacklisted'] === 1): ?>
                                    <span class="status status-rejected">Blacklisted</span>
                                <?php else: ?>
                                    <span class="status status-approved">Allowed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="toggle_visitor_id" value="<?= $vis['visitor_id'] ?>">
                                    <?php if ((int)$vis['is_blacklisted'] === 1): ?>
                                        <input type="hidden" name="new_status" value="0">
                                        <button type="submit" class="btn btn-sm btn-success">Unblock Visitor</button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_status" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger">Flag Blacklist</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const blCountrySelect = document.getElementById("bl_country_code");
    const blPhoneInput    = document.getElementById("bl_phone");
    const blPhoneHint     = document.getElementById("bl_phone_hint");

    blCountrySelect.addEventListener("change", () => {
        const opt = blCountrySelect.options[blCountrySelect.selectedIndex];
        const digits = parseInt(opt.getAttribute("data-digits") || "10", 10);
        blPhoneInput.maxLength = digits;
        blPhoneInput.minLength = digits;
        blPhoneInput.pattern = `[0-9]{${digits}}`;
        blPhoneInput.placeholder = `${digits}-digit number`;
        blPhoneInput.value = blPhoneInput.value.replace(/[^0-9]/g, "").slice(0, digits);
        blPhoneHint.textContent = `Requires strictly ${digits} digits for ${blCountrySelect.value}`;
    });
</script>

<?php renderPageEnd(); ?>