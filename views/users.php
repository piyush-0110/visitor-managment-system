<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin(['admin']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    $validRoles = ['admin', 'security', 'host'];

    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 6 && in_array($role, $validRoles, true)) {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = "An account with email '$email' already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $ins->execute([$name, $email, $hash, $role]);

            logAudit($pdo, (int)$_SESSION['user_id'], "Created new $role account: $name ($email)");
            $message = "Account created for $name ($role).";
        }
    } else {
        $error = "Provide a valid name, email, role, and password (minimum 6 characters).";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $targetId = (int)$_POST['delete_user_id'];
    if ($targetId === (int)$_SESSION['user_id']) {
        $error = "You cannot delete your own currently logged-in admin account.";
    } else {
        try {
            $del = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $del->execute([$targetId]);
            logAudit($pdo, (int)$_SESSION['user_id'], "Deleted user account #$targetId");
            $message = "User #$targetId deleted.";
        } catch (PDOException $e) {
            $error = "Cannot delete user #$targetId because they are linked to existing visit or appointment records.";
        }
    }
}

$users = $pdo->query("SELECT user_id, name, email, role FROM users ORDER BY role ASC, name ASC")->fetchAll();

renderPageStart('Staff & Users');
?>

<?php if ($error): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($message): ?>
    <div class="alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 1.75rem;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Add Staff / Faculty Account</h2>
    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.25rem;">Create logins for administrators, gate security personnel, or host faculty members.</p>

    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.9rem; align-items: end;">
        <input type="hidden" name="create_user" value="1">
        <div>
            <label>Full Name *</label>
            <input type="text" name="name" placeholder="e.g., Prof. Neha Gupta" required>
        </div>
        <div>
            <label>Email Address *</label>
            <input type="email" name="email" placeholder="neha@college.edu" required>
        </div>
        <div>
            <label>Password (min 6 chars) *</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <div>
            <label>Role *</label>
            <select name="role" required>
                <option value="host">Host (Faculty / Employee)</option>
                <option value="security">Security Staff</option>
                <option value="admin">Administrator</option>
            </select>
        </div>
        <button type="submit" class="btn">Create Account</button>
    </form>
</div>

<div class="card">
    <h3 style="font-size: 1.05rem;">Registered System Users</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong>#<?= $u['user_id'] ?></strong></td>
                        <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge-role" style="background: var(--primary-soft); color: var(--primary);"><?= htmlspecialchars($u['role']) ?></span></td>
                        <td>
                            <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="POST" onsubmit="return confirm('Delete this user account?');">
                                    <input type="hidden" name="delete_user_id" value="<?= $u['user_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--muted); font-size: 0.82rem;">Current Session</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderPageEnd(); ?>