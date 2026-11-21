<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(array $allowedRoles = []): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: //index.php");
        exit;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        die("Access Denied: You do not have permission to view this page.");
    }
}

function logAudit(PDO $pdo, int $userId, string $action): void {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$userId, $action]);
}
?>