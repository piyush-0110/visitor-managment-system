<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];

// Fetch KPI metrics
$totalVisitors = $pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
$activeInside  = $pdo->query("SELECT COUNT(*) FROM visits WHERE status = 'checked_in'")->fetchColumn();
$pendingCount  = $pdo->query("SELECT COUNT(*) FROM visits WHERE status = 'pending'")->fetchColumn();
$todayVisits   = $pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(check_in_time) = CURDATE()")->fetchColumn();

// Fetch unread notifications for the logged-in user
$notifStmt = $pdo->prepare("
    SELECT notification_id, message, created_at
    FROM notifications
    WHERE user_id = ? AND is_read = 0
    ORDER BY notification_id DESC
");
$notifStmt->execute([$currentUserId]);
$unreadNotifs = $notifStmt->fetchAll();

// Fetch recent visits (supports both registered and custom 'Other' hosts)
$stmt = $pdo->query("
    SELECT v.visit_id, vis.name AS visitor_name, vis.phone, vis.organization,
           COALESCE(u.name, v.custom_host_name) AS host_name,
           v.purpose, v.status, v.check_in_time, v.check_out_time
    FROM visits v
    JOIN visitors vis ON v.visitor_id = vis.visitor_id
    LEFT JOIN users u ON v.host_id = u.user_id
    ORDER BY v.visit_id DESC
    LIMIT 10
");
$recentVisits = $stmt->fetchAll();

renderPageStart('Dashboard');
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
    <h2 style="font-size: 1.15rem; font-weight: 700;">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?></h2>
    <span id="notifBadge" style="background: var(--danger-soft); color: #991b1b; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.82rem; font-weight: 700;">
        <?= count($unreadNotifs) ?> Unread Alerts
    </span>
</div>

<!-- Host Notification Panel -->
<div id="notifPanel" class="card" style="margin-bottom: 1.75rem; border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
        <h3 style="font-size: 1rem;">Host Arrival Notifications</h3>
        <?php if (!empty($unreadNotifs)): ?>
            <button id="markReadBtn" class="btn btn-sm btn-dark">Mark All Read</button>
        <?php endif; ?>
    </div>

    <div id="notifList">
        <?php if (empty($unreadNotifs)): ?>
            <p style="color: var(--muted); font-size: 0.9rem;">No new visitor arrival notifications.</p>
        <?php else: ?>
            <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem;">
                <?php foreach ($unreadNotifs as $n): ?>
                    <li style="background: #f8fafc; padding: 0.7rem 0.95rem; border-radius: 8px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.9rem;"><?= htmlspecialchars($n['message']) ?></span>
                        <small style="color: var(--muted); white-space: nowrap; margin-left: 1rem;"><?= htmlspecialchars($n['created_at']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- 4 Colored KPI Cards -->
<div class="stats-grid">
    <div class="card stat-card success">
        <div class="stat-label">Currently Inside</div>
        <div class="stat-value"><?= $activeInside ?></div>
    </div>
    <div class="card stat-card warning">
        <div class="stat-label">Pending Approvals</div>
        <div class="stat-value"><?= $pendingCount ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Today's Check-Ins</div>
        <div class="stat-value"><?= $todayVisits ?></div>
    </div>
    <div class="card stat-card slate">
        <div class="stat-label">Total Registered Visitors</div>
        <div class="stat-value"><?= $totalVisitors ?></div>
    </div>
</div>

<!-- Recent Visitor Activity Table -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.05rem;">Recent Visitor Activity</h3>
        <a href="//views/register_visitor.php" class="btn btn-sm">+ New Visitor Entry</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Visitor</th>
                    <th>Organization</th>
                    <th>Host</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Check-In</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentVisits)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--muted);">No visitor records yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentVisits as $row): ?>
                        <tr>
                            <td><strong>#<?= $row['visit_id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($row['visitor_name']) ?></strong><br><small style="color:var(--muted)"><?= htmlspecialchars($row['phone']) ?></small></td>
                            <td><?= htmlspecialchars($row['organization'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($row['host_name']) ?></td>
                            <td><?= htmlspecialchars($row['purpose']) ?></td>
                            <td><span class="status status-<?= $row['status'] ?>"><?= str_replace('_', ' ', $row['status']) ?></span></td>
                            <td><?= $row['check_in_time'] ?: '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const markBtn = document.getElementById("markReadBtn");
    if (markBtn) {
        markBtn.addEventListener("click", async () => {
            const res = await fetch("//api/mark_notifications.php", { method: "POST" });
            const data = await res.json();
            if (data.status === "ok") {
                document.getElementById("notifList").innerHTML =
                    '<p style="color: var(--muted); font-size: 0.9rem;">All notifications marked as read.</p>';
                document.getElementById("notifBadge").textContent = "0 Unread Alerts";
                markBtn.remove();
            }
        });
    }
</script>

<?php renderPageEnd(); ?>