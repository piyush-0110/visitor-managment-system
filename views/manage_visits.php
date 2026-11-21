<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin();

$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['visit_id'], $_POST['action'])) {
    $visitId = (int)$_POST['visit_id'];
    $action  = $_POST['action'];
    $staffId = (int)$_SESSION['user_id'];

    if ($action === 'approve') {
        $vStmt = $pdo->prepare("SELECT vis.name FROM visits v JOIN visitors vis ON v.visitor_id = vis.visitor_id WHERE v.visit_id = ?");
        $vStmt->execute([$visitId]);
        $visitorName = $vStmt->fetchColumn() ?: 'Visitor';

        // Call Python venv to generate QR pass
        $pythonExe = PHP_OS_FAMILY === 'Windows'
                ? __DIR__ . '/../venv/Scripts/python.exe'
                : '/var/www/html/venv/bin/python';
            $script    = realpath(__DIR__ . '/../python_services/make_qr.py');
        $cmd       = "\"$pythonExe\" \"$script\" " . escapeshellarg((string)$visitId) . " " . escapeshellarg($visitorName) . " 2>&1";
        $qrOutput  = trim((string)shell_exec($cmd));

        $qrPath = (strpos($qrOutput, 'assets/qr_codes/') === 0) ? $qrOutput : null;

        $upd = $pdo->prepare("UPDATE visits SET status = 'approved', approved_by = ?, qr_code_path = ? WHERE visit_id = ?");
        $upd->execute([$staffId, $qrPath, $visitId]);

        logAudit($pdo, $staffId, "Approved Visit #$visitId and generated QR pass");
        $feedback = "Visit #$visitId approved! QR pass generated via Python.";
    } elseif ($action === 'reject') {
        $upd = $pdo->prepare("UPDATE visits SET status = 'rejected', approved_by = ? WHERE visit_id = ?");
        $upd->execute([$staffId, $visitId]);
        logAudit($pdo, $staffId, "Rejected Visit #$visitId");
        $feedback = "Visit #$visitId rejected.";
    } elseif ($action === 'check_in') {
        $upd = $pdo->prepare("UPDATE visits SET status = 'checked_in', check_in_time = NOW() WHERE visit_id = ?");
        $upd->execute([$visitId]);
        logAudit($pdo, $staffId, "Checked in Visit #$visitId");
        $feedback = "Visitor for Visit #$visitId checked in.";
    } elseif ($action === 'check_out') {
        $upd = $pdo->prepare("UPDATE visits SET status = 'checked_out', check_out_time = NOW() WHERE visit_id = ?");
        $upd->execute([$visitId]);
        logAudit($pdo, $staffId, "Checked out Visit #$visitId");
        $feedback = "Visitor for Visit #$visitId checked out.";
    }
}

// Search & Filter (supports both registered and custom 'Other' hosts)
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "
    SELECT v.*, vis.name AS visitor_name, vis.phone, vis.organization,
           COALESCE(u.name, v.custom_host_name) AS host_name
    FROM visits v
    JOIN visitors vis ON v.visitor_id = vis.visitor_id
    LEFT JOIN users u ON v.host_id = u.user_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (vis.name LIKE ? OR vis.phone LIKE ? OR v.purpose LIKE ? OR COALESCE(u.name, v.custom_host_name) LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($statusFilter !== '') {
    $sql .= " AND v.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY v.visit_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

renderPageStart('Gate & Approvals');
?>

<?php if ($feedback): ?>
    <div class="alert-success"><?= htmlspecialchars($feedback) ?></div>
<?php endif; ?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h2 style="font-size: 1.1rem;">Visitor Gate Control &amp; Approvals</h2>
        <form method="GET" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <input type="text" name="search" placeholder="Search visitor, host, phone..." value="<?= htmlspecialchars($search) ?>" style="width: 240px;">
            <select name="status" style="width: 165px;">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="checked_in" <?= $statusFilter === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                <option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <button type="submit" class="btn btn-sm">Filter</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Visitor</th>
                    <th>Host</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Timestamps</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visits)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--muted);">No matching visitor records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($visits as $v): ?>
                        <tr>
                            <td><strong>#<?= $v['visit_id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($v['visitor_name']) ?></strong><br>
                                <small style="color:var(--muted)"><?= htmlspecialchars($v['phone']) ?> | <?= htmlspecialchars($v['organization'] ?: 'Individual') ?></small>
                            </td>
                            <td><?= htmlspecialchars($v['host_name']) ?></td>
                            <td><?= htmlspecialchars($v['purpose']) ?></td>
                            <td><span class="status status-<?= $v['status'] ?>"><?= str_replace('_', ' ', $v['status']) ?></span></td>
                            <td style="font-size: 0.82rem; color: var(--muted);">
                                In: <?= $v['check_in_time'] ?: '—' ?><br>
                                Out: <?= $v['check_out_time'] ?: '—' ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline-flex; gap: 0.35rem; flex-wrap: wrap;">
                                    <input type="hidden" name="visit_id" value="<?= $v['visit_id'] ?>">
                                    <?php if ($v['status'] === 'pending'): ?>
                                        <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                        <button name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                                    <?php elseif ($v['status'] === 'approved'): ?>
                                        <button name="action" value="check_in" class="btn btn-sm">Check In</button>
                                        <a href="/views/visitor_pass.php?id=<?= $v['visit_id'] ?>" target="_blank" class="btn btn-sm btn-dark">QR Pass</a>
                                    <?php elseif ($v['status'] === 'checked_in'): ?>
                                        <button name="action" value="check_out" class="btn btn-sm btn-danger">Check Out</button>
                                        <a href="/views/visitor_pass.php?id=<?= $v['visit_id'] ?>" target="_blank" class="btn btn-sm btn-dark">QR Pass</a>
                                    <?php else: ?>
                                        <span style="color: var(--muted); font-size: 0.82rem;">Closed</span>
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