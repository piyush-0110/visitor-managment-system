<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$rawCode = trim($input['qr_data'] ?? '');

if ($rawCode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty QR payload received.']);
    exit;
}

// Extract Visit ID from "VMS-PASS | ID: #12 | Name: John" or plain number "12"
$visitId = 0;
if (preg_match('/ID:\s*#?(\d+)/i', $rawCode, $matches)) {
    $visitId = (int)$matches[1];
} elseif (ctype_digit($rawCode)) {
    $visitId = (int)$rawCode;
}

if ($visitId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unrecognized QR format: ' . htmlspecialchars($rawCode)]);
    exit;
}

// Fetch visit and visitor details
$stmt = $pdo->prepare("
    SELECT v.visit_id, v.status, vis.name AS visitor_name, vis.organization,
    COALESCE(u.name, v.custom_host_name) AS host_name
    FROM visits v
    JOIN visitors vis ON v.visitor_id = vis.visitor_id
    LEFT JOIN users u ON v.host_id = u.user_id
    WHERE v.visit_id = ?
");
$stmt->execute([$visitId]);
$visit = $stmt->fetch();

if (!$visit) {
    echo json_encode(['status' => 'error', 'message' => "Visit #$visitId not found in database."]);
    exit;
}

$staffId = (int)$_SESSION['user_id'];
$currentStatus = $visit['status'];

if ($currentStatus === 'approved') {
    $pdo->prepare("UPDATE visits SET status = 'checked_in', check_in_time = NOW() WHERE visit_id = ?")
        ->execute([$visitId]);
    logAudit($pdo, $staffId, "QR Scan Check-In for Visit #$visitId ({$visit['visitor_name']})");

    echo json_encode([
        'status'  => 'success',
        'action'  => 'CHECKED IN',
        'visit'   => $visit,
        'message' => "{$visit['visitor_name']} checked IN at " . date('H:i:s')
    ]);
} elseif ($currentStatus === 'checked_in') {
    $pdo->prepare("UPDATE visits SET status = 'checked_out', check_out_time = NOW() WHERE visit_id = ?")
        ->execute([$visitId]);
    logAudit($pdo, $staffId, "QR Scan Check-Out for Visit #$visitId ({$visit['visitor_name']})");

    echo json_encode([
        'status'  => 'success',
        'action'  => 'CHECKED OUT',
        'visit'   => $visit,
        'message' => "{$visit['visitor_name']} checked OUT at " . date('H:i:s')
    ]);
} elseif ($currentStatus === 'pending') {
    echo json_encode([
        'status'  => 'warning',
        'message' => "Visit #$visitId ({$visit['visitor_name']}) is still PENDING approval."
    ]);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => "Pass #$visitId is closed (Status: " . strtoupper(str_replace('_', ' ', $currentStatus)) . ")."
    ]);
}
?>