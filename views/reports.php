<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin(['admin']);

$generatedFile = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['format'])) {
    $format = $_POST['format'] === 'pdf' ? 'pdf' : 'csv';
    $reportsDir = __DIR__ . '/../assets/reports';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0777, true);
    }

    $relativePath = 'assets/reports/vms_visits_' . date('Ymd_His') . '.' . $format;
    $fullOutputPath = __DIR__ . '/../' . $relativePath;
    $tempJsonPath = sys_get_temp_dir() . '/vms_export_' . uniqid() . '.json';

    // Fetch visit rows via PHP's active $pdo connection (works on both TiDB Cloud & Localhost)
    $visitRows = $pdo->query("
        SELECT v.visit_id,
               vis.name AS visitor_name,
               vis.phone AS visitor_phone,
               vis.organization,
               v.purpose,
               COALESCE(u.name, v.custom_host_name, 'Unassigned') AS host_name,
               v.status,
               v.check_in_time,
               v.check_out_time,
               v.created_at
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.visitor_id
        LEFT JOIN users u ON v.host_id = u.user_id
        ORDER BY v.visit_id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    file_put_contents($tempJsonPath, json_encode($visitRows, JSON_UNESCAPED_UNICODE));

    $pythonExe = PHP_OS_FAMILY === 'Windows'
        ? __DIR__ . '/../venv/Scripts/python.exe'
        : '/var/www/html/venv/bin/python';
    $script = __DIR__ . '/../python_services/generate_report.py';

    $cmd = "\"$pythonExe\" \"$script\" "
         . escapeshellarg($format) . " "
         . escapeshellarg($fullOutputPath) . " "
         . escapeshellarg($tempJsonPath) . " 2>&1";
    $output = trim((string)shell_exec($cmd));
    @unlink($tempJsonPath);

    if (str_contains($output, 'SUCCESS') && file_exists($fullOutputPath)) {
        $generatedFile = $relativePath;
        logAudit($pdo, (int)$_SESSION['user_id'], "Generated Python $format visitor report");
    } else {
        $error = "Python Report Error: " . $output;
    }
}

$logs = $pdo->query("
    SELECT a.log_id, u.name, u.role, a.action, a.timestamp
    FROM audit_logs a
    JOIN users u ON a.user_id = u.user_id
    ORDER BY a.log_id DESC
    LIMIT 15
")->fetchAll();

renderPageStart('Python Reports');
?>

<div class="card" style="margin-bottom: 1.75rem;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Python Visitor Analytics &amp; Export Engine</h2>
    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
        Uses Python (<code>csv</code> &amp; <code>reportlab</code>) to compile TiDB/MySQL visit records into downloadable reports.
    </p>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" style="display: flex; gap: 0.9rem; align-items: center; flex-wrap: wrap;">
        <button type="submit" name="format" value="csv" class="btn">Generate CSV Report</button>
        <button type="submit" name="format" value="pdf" class="btn btn-success">Generate PDF Report</button>

        <?php if ($generatedFile): ?>
            <a href="/<?= htmlspecialchars($generatedFile) ?>" download class="btn btn-dark">
                Download Ready File (<?= strtoupper(pathinfo($generatedFile, PATHINFO_EXTENSION)) ?>)
            </a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h3 style="font-size: 1.05rem;">System Audit Logs</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Staff Member</th>
                    <th>Role</th>
                    <th>Action Recorded</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--muted);">No audit logs recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><strong>#<?= $log['log_id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($log['name']) ?></strong></td>
                            <td><span class="badge-role" style="background: var(--primary-soft); color: var(--primary);"><?= htmlspecialchars($log['role']) ?></span></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td style="color: var(--muted); font-size: 0.85rem;"><?= $log['timestamp'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderPageEnd(); ?>