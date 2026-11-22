<?php
session_start();
require_once __DIR__ . '/config/db.php';

$step = $_SESSION['reset_step'] ?? 1;
$resetEmail = $_SESSION['reset_email'] ?? '';
$error = '';
$success = '';

// Step 1: Send OTP to Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "No account found with that email address.";
    } else {
        $otp = (string)random_int(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        $pdo->prepare("INSERT INTO password_resets (email, otp_code, expires_at) VALUES (?, ?, ?)")
            ->execute([$email, $otp, $expires]);

        $pythonExe = (PHP_OS_FAMILY === 'Windows')
            ? __DIR__ . '/venv/Scripts/python.exe'
            : '/var/www/html/venv/bin/python';
        $script = realpath(__DIR__ . '/python_services/send_otp.py');
        $cmd = "\"$pythonExe\" \"$script\" " . escapeshellarg($email) . " " . escapeshellarg($otp) . " 2>&1";
        $output = trim((string)shell_exec($cmd));

        if (str_contains($output, 'SUCCESS')) {
            $_SESSION['reset_step'] = 2;
            $_SESSION['reset_email'] = $email;
            $step = 2;
            $resetEmail = $email;
            $success = "A 6-digit OTP has been sent to " . htmlspecialchars($email) . ".";
        } else {
            $error = "Failed to send OTP email. Check SMTP settings. (" . htmlspecialchars($output) . ")";
        }
    }
}

// Step 2: Verify OTP & Update Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otpInput = trim($_POST['otp'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($newPass !== $confirmPass) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND otp_code = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([$resetEmail, $otpInput]);
        $record = $stmt->fetch();

        if (!$record) {
            $error = "Invalid or expired OTP code.";
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $resetEmail]);
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$resetEmail]);
            unset($_SESSION['reset_step'], $_SESSION['reset_email']);
            header("Location: /index.php?reset=1");
            exit;
        }
    }
}

// Reset flow if user clicks "Use different email"
if (isset($_GET['restart'])) {
    unset($_SESSION['reset_step'], $_SESSION['reset_email']);
    header("Location: /forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — VMS Portal</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body style="background: #111827; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: 'Segoe UI', sans-serif;">
    <div style="background: #ffffff; width: 100%; max-width: 420px; padding: 36px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);">
        <h2 style="margin: 0 0 8px; color: #111827; font-size: 22px;">Reset Password</h2>
        <p style="margin: 0 0 24px; color: #64748b; font-size: 14px;">
            <?= $step === 1 ? 'Enter your registered email to receive a 6-digit OTP.' : 'Enter the OTP sent to <strong>' . htmlspecialchars($resetEmail) . '</strong>.' ?>
        </p>

        <?php if ($error): ?>
            <div style="background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid #fecaca;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: #ecfdf5; color: #059669; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid #a7f3d0;">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Registered Email</label>
                <input type="email" name="email" required placeholder="yourpersonalmail@gmail.com"
                       style="width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 18px; box-sizing: border-box;">
                <button type="submit" name="send_otp"
                        style="width: 100%; padding: 12px; background: #0f766e; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Send OTP Code
                </button>
            </form>
        <?php else: ?>
            <form method="POST">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">6-Digit OTP</label>
                <input type="text" name="otp" maxlength="6" required placeholder="123456"
                       style="width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 14px; letter-spacing: 4px; font-weight: 700; box-sizing: border-box;">

                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">New Password</label>
                <input type="password" name="new_password" required placeholder="Minimum 6 characters"
                       style="width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 14px; box-sizing: border-box;">

                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="Re-enter password"
                       style="width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 18px; box-sizing: border-box;">

                <button type="submit" name="verify_otp"
                        style="width: 100%; padding: 12px; background: #059669; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Verify OTP & Update Password
                </button>
            </form>
            <div style="text-align: center; margin-top: 12px;">
                <a href="/forgot_password.php?restart=1" style="color: #64748b; font-size: 13px; text-decoration: none;">Use a different email</a>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <a href="/index.php" style="color: #0f766e; font-size: 13px; font-weight: 600; text-decoration: none;">← Back to Sign In</a>
        </div>
    </div>
</body>
</html>