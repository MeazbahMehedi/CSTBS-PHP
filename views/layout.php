<?php $viewer = current_user(); ?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#11233a">
        <meta name="description" content="Discover campus events, reserve your stadium seats, and manage your admission passes.">
        <title><?= e($title ?? 'CSTBS') ?> | Campus Stadium</title>
        <link rel="stylesheet" href="<?= e(url('/public/assets/css/styles.css')) ?>">
        <link rel="stylesheet" href="<?= e(url('/public/assets/css/php.css')) ?>">
        <script src="<?= e(url('/public/assets/js/main.js')) ?>" defer></script>
        <script src="<?= e(url('/public/assets/js/seats.js')) ?>" defer></script>
    </head>
    <body>
        <a class="skip-link" href="#main-content">Skip to content</a>
        <header class="site-header">
            <nav class="container nav" aria-label="Primary navigation">
                <a class="brand" href="<?= e(url('/')) ?>" aria-label="CSTBS home"><span class="brand-mark" aria-hidden="true">C</span><span class="brand-name">CSTBS<small>Campus Stadium</small></span></a>
                <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-links">Menu</button>
                <div class="nav-links" id="nav-links">
                    <a href="<?= e(url('/')) ?>">Home</a>
                    <a href="<?= e(url('/events')) ?>">Events</a>
                    <?php if ($viewer): ?>
                        <?php if ($viewer['role'] === 'admin'): ?>
                            <a href="<?= e(url('/admin')) ?>">Stadium control</a>
                        <?php elseif ($viewer['role'] === 'student'): ?>
                            <a href="<?= e(url('/dashboard')) ?>">Student dashboard</a>
                    <a href="<?= e(url('/bookings')) ?>">My bookings</a>
                        <?php endif ?>
                        <?php if (in_array($viewer['role'], ['admin', 'staff'], true)): ?>
                            <a href="<?= e(url('/entry/scan')) ?>">QR scanner</a>
                            <a href="<?= e(url('/entry/check-ins')) ?>">Attendance</a>
                        <?php endif ?>
                        <a href="<?= e(url('/account')) ?>">My account</a>
                        <form class="logout-form" method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?><button class="logout-button" type="submit">Sign out</button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e(url('/login')) ?>">Login</a>
                    <a class="nav-register" href="<?= e(url('/register')) ?>">Student register</a>
                    <a href="<?= e(url('/admin/login')) ?>">Admin login</a>
                    <?php endif ?>
                </div>
                <button class="theme-toggle" type="button" aria-label="Switch between day and night theme" aria-pressed="false">☾</button>
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
        <footer class="site-footer">
            <div class="container footer-inner">
                <div>
                    <strong>CSTBS</strong>
                    <p>Campus Stadium Ticket Booking System</p>
                </div>
                <span>University Stadium · Campus access and event reservations</span>
            </div>
        </footer>
    </body>
</html>
