<?php
$viewer = current_user();
$role = $viewer['role'] ?? 'guest';
$currentPath = $GLOBALS['request_path'] ?? '/';
$roleHome = match ($role) {
    'admin' => '/admin',
    'staff' => '/entry/scan',
    'student' => '/dashboard',
    default => '/',
};
$navigation = match ($role) {
    'admin' => [
        '/admin' => 'Overview',
        '/admin/events' => 'Events',
        '/admin/bookings' => 'Bookings',
        '/admin/users' => 'Users',
        '/admin/reports' => 'Reports',
        '/entry/scan' => 'Scanner',
        '/admin/check-ins' => 'Attendance',
    ],
    'staff' => ['/entry/scan' => 'Scanner', '/entry/check-ins' => 'Attendance'],
    'student' => ['/events' => 'Events', '/dashboard' => 'My tickets', '/bookings' => 'My bookings'],
    default => ['/events' => 'Events', '/login' => 'Student login', '/admin/login' => 'Admin login'],
};
$workspace = match ($role) {
    'admin' => 'Administration',
    'staff' => 'Gate operations',
    'student' => 'Student portal',
    default => 'Campus Stadium',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#142b40">
    <meta name="description" content="Campus events, seat reservations, and stadium admission.">
    <title><?= e($title ?? 'CSTBS') ?> | Campus Stadium</title>
    <link rel="stylesheet" href="<?= e(url('/public/assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/public/assets/css/php.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/public/assets/css/premium.css')) ?>">
    <script src="<?= e(url('/public/assets/js/main.js')) ?>" defer></script>
    <script src="<?= e(url('/public/assets/js/motion.js')) ?>" defer></script>
    <script src="<?= e(url('/public/assets/js/seats.js')) ?>" defer></script>
</head>
<body data-role="<?= e($role) ?>" data-page="<?= $currentPath === '/' ? 'home' : 'workspace' ?>">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <nav class="container nav" aria-label="Primary navigation">
            <a class="brand" href="<?= e(url($roleHome)) ?>" aria-label="<?= e($workspace . ' home') ?>">
                <span class="brand-mark" aria-hidden="true">C</span>
                <span class="brand-name">CSTBS<small><?= e($workspace) ?></small></span>
            </a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-links">Menu</button>
            <div class="nav-links" id="nav-links">
                <?php foreach ($navigation as $path => $label): ?>
                    <?php $isCurrent = $currentPath === $path || ($path !== '/admin' && str_starts_with($currentPath, $path . '/')); ?>
                    <a href="<?= e(url($path)) ?>" <?= $isCurrent ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach ?>
                <?php if ($viewer): ?>
                    <a href="<?= e(url('/account')) ?>" <?= $currentPath === '/account' ? 'aria-current="page"' : '' ?>>My account</a>
                    <form class="logout-form" method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="logout-button" type="submit">Sign out</button>
                    </form>
                <?php else: ?>
                    <a class="nav-register" href="<?= e(url('/register')) ?>">Register</a>
                <?php endif ?>
            </div>
            <button class="theme-toggle" type="button" aria-label="Switch theme" aria-pressed="false">☾</button>
        </nav>
    </header>
    <main id="main-content">
        <?php foreach (flash() as $notice): ?>
            <div class="container flash-wrap">
                <p class="flash flash-<?= e($notice['category']) ?>" role="status"><?= e($notice['message']) ?></p>
            </div>
        <?php endforeach ?>
        <?php require $viewFile; ?>
    </main>
</body>
</html>
