<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: //views/dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT user_id, name, password_hash, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        logAudit($pdo, (int)$user['user_id'], "Logged into VMS");
        header("Location: //views/dashboard.php");
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Visitor Management System</title>
    <link rel="stylesheet" href="//assets/css/style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="card">
            <h2 style="margin-bottom: 0.25rem;">Visitor Management System</h2>
            <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Sign in with your staff credentials</p>

            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="admin@vms.local" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Sign In</button>
            </form>

            <div style="margin-top: 1.25rem; font-size: 0.8rem; color: var(--muted); border-top: 1px solid var(--border); padding-top: 0.8rem;">
                <strong>Demo Logins (Password: <code>password</code>):</strong><br>
                Admin: <code>admin@vms.local</code><br>
                Security: <code>security@vms.local</code>
            </div>
        </div>
    </div>
</body>
</html>