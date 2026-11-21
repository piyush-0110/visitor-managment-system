<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$visitId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT v.*, vis.name AS visitor_name, vis.phone, vis.organization,
           COALESCE(u.name, v.custom_host_name) AS host_name
    FROM visits v
    JOIN visitors vis ON v.visitor_id = vis.visitor_id
    LEFT JOIN users u ON v.host_id = u.user_id
    WHERE v.visit_id = ?
");
$stmt->execute([$visitId]);
$pass = $stmt->fetch();

if (!$pass) {
    die("Visitor pass not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visitor Pass #<?= $pass['visit_id'] ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { background: #e2e8f0; display: grid; place-items: center; min-height: 100vh; padding: 1.5rem; }
        .pass-card {
            width: 100%; max-width: 370px; background: #fff; border-radius: 18px;
            overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid #cbd5e1; text-align: center;
        }
        .pass-header {
            background: linear-gradient(135deg, #0f172a, #312e81); color: #fff; padding: 1.25rem;
        }
        .lanyard-slot {
            width: 56px; height: 8px; background: rgba(255,255,255,0.25); border-radius: 999px; margin: 0 auto 0.85rem;
        }
        .pass-body { padding: 1.5rem; }
        .qr-frame {
            display: inline-block; padding: 0.65rem; border: 2px dashed #cbd5e1; border-radius: 12px; margin: 0.75rem 0; background: #f8fafc;
        }
        .pass-meta {
            text-align: left; background: #f8fafc; border-radius: 10px; padding: 0.9rem 1rem;
            font-size: 0.86rem; display: grid; gap: 0.45rem; margin-top: 0.75rem; border: 1px solid var(--border);
        }
        .pass-meta div { display: flex; justify-content: space-between; gap: 0.5rem; }
        .pass-meta span { color: var(--muted); }
        @media print { .no-print { display: none !important; } body { background: #fff; } .pass-card { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="pass-card">
        <div class="pass-header">
            <div class="lanyard-slot"></div>
            <div style="font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase; color: #a5b4fc; font-weight: 700;">
                Authorized Entry Badge
            </div>
            <h2 style="font-size: 1.35rem; margin-top: 0.25rem;"><?= htmlspecialchars($pass['visitor_name']) ?></h2>
            <p style="font-size: 0.85rem; color: #cbd5e1;"><?= htmlspecialchars($pass['organization'] ?: 'Guest Visitor') ?></p>
        </div>

        <div class="pass-body">
            <?php if ($pass['qr_code_path']): ?>
                <div class="qr-frame">
                    <img src="//<?= htmlspecialchars($pass['qr_code_path']) ?>" alt="QR Code" style="width: 165px; height: 165px; display: block;">
                </div>
            <?php endif; ?>

            <div class="pass-meta">
                <div><span>Pass Number</span><strong>#VMS-<?= str_pad((string)$pass['visit_id'], 4, '0', STR_PAD_LEFT) ?></strong></div>
                <div><span>Meeting Host</span><strong><?= htmlspecialchars($pass['host_name']) ?></strong></div>
                <div><span>Purpose</span><strong><?= htmlspecialchars($pass['purpose']) ?></strong></div>
                <div><span>Date Issued</span><strong><?= date('d M Y') ?></strong></div>
            </div>

            <button onclick="window.print()" class="btn no-print" style="margin-top: 1.25rem; width: 100%;">
                Print Visitor Pass
            </button>
        </div>
    </div>
</body>
</html>