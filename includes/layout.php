<?php
function renderPageStart(string $pageTitle): void {
    $currentPage = basename($_SERVER['PHP_SELF']);
    $role = $_SESSION['role'] ?? 'guest';
    $userName = $_SESSION['name'] ?? 'Staff';

    $navItems = [
        ['file' => 'dashboard.php',        'label' => 'Dashboard',         'icon' => 'layout-dashboard', 'roles' => ['admin', 'security', 'host']],
        ['file' => 'register_visitor.php', 'label' => 'Register Visitor',  'icon' => 'user-plus',        'roles' => ['admin', 'security', 'host']],
        ['file' => 'appointments.php',     'label' => 'Appointments',      'icon' => 'calendar-clock',   'roles' => ['admin', 'security', 'host']],
        ['file' => 'manage_visits.php',    'label' => 'Gate & Approvals',  'icon' => 'shield-check',     'roles' => ['admin', 'security', 'host']],
        ['file' => 'qr_scanner.php',       'label' => 'Live QR Scanner',   'icon' => 'scan-line',        'roles' => ['admin', 'security', 'host']],
        ['file' => 'blacklist.php',        'label' => 'Security Blacklist','icon' => 'user-x',           'roles' => ['admin']],
        ['file' => 'users.php',            'label' => 'Staff & Users',     'icon' => 'users',            'roles' => ['admin']],
        ['file' => 'reports.php',          'label' => 'Python Reports',    'icon' => 'file-spreadsheet', 'roles' => ['admin']],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($pageTitle) ?> — VMS Enterprise</title>
        <link rel="stylesheet" href="/assets/css/style.css">
        <script src="https://unpkg.com/lucide@latest"></script>
    </head>
    <body>
        <div class="app-shell">
            <aside class="sidebar">
                <a href="/views/dashboard.php" class="sidebar-brand">
                    <div class="brand-icon">V</div>
                    <div class="brand-text">
                        <span>VMS Portal</span>
                        <small>Smart Gate &amp; Security</small>
                    </div>
                </a>

                <nav class="sidebar-nav">
                    <div class="nav-section-label">Operations</div>
                    <?php foreach ($navItems as $item): ?>
                        <?php if (in_array($role, $item['roles'], true)): ?>
                            <?php if ($item['file'] === 'blacklist.php'): ?>
                                <div class="nav-section-label">Administration</div>
                            <?php endif; ?>
                            <a href="/views/<?= $item['file'] ?>" class="nav-item <?= $currentPage === $item['file'] ? 'active' : '' ?>">
                                <i data-lucide="<?= $item['icon'] ?>"></i>
                                <span><?= htmlspecialchars($item['label']) ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <div class="sidebar-footer">
                    <div class="user-pill">
                        <div class="user-meta">
                            <strong><?= htmlspecialchars($userName) ?></strong>
                            <span class="badge-role"><?= htmlspecialchars($role) ?></span>
                        </div>
                        <a href="//logout.php" class="logout-link">Logout</a>
                    </div>
                </div>
            </aside>

            <div class="main-content">
                <header class="topbar">
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                    <div style="font-size: 0.84rem; color: var(--muted); font-weight: 500;">
                        <?= date('l, d M Y') ?>
                    </div>
                </header>
                <main class="container">
    <?php
}

function renderPageEnd(): void {
    ?>
                </main>
            </div>
        </div>
        <script>
            if (window.lucide) { lucide.createIcons(); }
        </script>
    </body>
    </html>
    <?php
}
?>