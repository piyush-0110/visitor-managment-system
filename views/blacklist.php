<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin(['admin']);

$feedback = '';

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
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $org   = trim($_POST['organization'] ?? '');

    if ($name && $phone) {
        $check = $pdo->prepare("SELECT visitor_id FROM visitors WHERE phone = ?");
        $check->execute([$phone]);
        $existingId = $check->fetchColumn();

        if ($existingId) {
            $pdo->prepare("UPDATE visitors SET is_blacklisted = 1 WHERE visitor_id = ?")->execute([$existingId]);
        } else {
            $pdo->prepare("INSERT INTO visitors (name, phone, organization, is_blacklisted) VALUES (?, ?, ?, 1)")
                ->execute([$name, $phone, $org]);
        }

        logAudit($pdo, (int)$_SESSION['user_id'], "Added $name ($phone) to security blacklist");
        $feedback = "$name ($phone) has been added to the security blacklist.";
    }
}

$visitors = $pdo->query("SELECT * FROM visitors ORDER BY is_blacklisted DESC, visitor_id DESC")->fetchAll();

renderPageStart('Security Blacklist');
?>

<?php if ($feedback): ?>
    <div class="alert-success"><?= htmlspecialchars($feedback) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 1.75rem;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Add Person to Security Blacklist</h2>
    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.25rem;">Flagged phone numbers are automatically blocked at reception and appointment booking.</p>

    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.9rem; align-items: end;">
        <input type="hidden" name="add_blacklist" value="1">
        <div>
            <label>Full Name *</label>
            <input type="text" name="name" placeholder="Full Name" required>
        </div>
        <div>
            <label>Phone Number *</label>
            <input type="text" name="phone" placeholder="Phone Number" required>
        </div>
        <div>
            <label>Organization / Notes</label>
            <input type="text" name="organization" placeholder="Reason or Organization">
        </div>
        <button type="submit" class="btn btn-danger">Blacklist Entry</button>
    </form>
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

<?php renderPageEnd(); ?>