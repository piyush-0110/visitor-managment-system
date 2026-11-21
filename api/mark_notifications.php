<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);

    echo json_encode(['status' => 'ok', 'updated' => $stmt->rowCount()]);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
?>