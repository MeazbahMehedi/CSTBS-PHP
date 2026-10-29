<?php
$mode = $mode ?? 'login';
$values = $values ?? [];
$register = $mode === 'register';
$activate = $mode === 'activate';
$admin_login = $mode === 'admin-login';
$heading = $register ? 'Join your campus community.' : ($activate ? 'Activate your admin account.' : ($admin_login ? 'Stadium administrator login' : 'Sign in to CSTBS'));
$action = $register ? '/register' : ($activate ? '/admin/activate' : ($admin_login ? '/admin/login' : '/login'));
?>
<section class="auth-section">
    <div class="auth-card animated-enter">
        <span class="section-kicker"><?= $register ? 'STUDENT REGISTRATION' : ($activate ? 'SECURE ACTIVATION' : 'WELCOME BACK') ?></span>
        <h1><?= e($heading) ?></h1>
        <p class="auth-subtitle"><?= $activate ? 'Use the single-use activation key supplied by your administrator.' : 'Access university events, reservations, and your personal admission passes.' ?></p>
        <?php foreach (($errors ?? []) as $error): ?>
            <p class="field-error" role="alert"><?= e($error) ?></p>
        <?php endforeach ?>
        <form method="post" action="<?= e(url($action)) ?>">
            <?= csrf_field() ?>
            <?php if ($register): ?>
                <div class="form-field">
                    <label for="full_name">Full name</label>
                    <input class="form-control" id="full_name" name="full_name" maxlength="120" autocomplete="name" value="<?= e($values['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-field">
                    <label for="student_id">Student ID</label>
                    <input class="form-control" id="student_id" name="student_id" pattern="[A-Za-z0-9]{6,20}" minlength="6" maxlength="20" value="<?= e($values['student_id'] ?? '') ?>" required>
                    <small>6–20 letters or numbers.</small>
                </div>
            <?php endif ?>
            <div class="form-field">
                <label for="email">Email address</label>
                <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= e($values['email'] ?? '') ?>" required>
            </div>
            <?php if ($activate): ?>
                <div class="form-field">
                    <label for="admin_key">Activation key</label>
                    <input class="form-control" id="admin_key" name="admin_key" maxlength="128" autocomplete="off" required>
                </div>
            <?php endif ?>
            <div class="form-field">
                <label for="password"><?= $activate ? 'New password' : 'Password' ?></label>
                <input class="form-control" id="password" name="password" type="password" maxlength="128" <?= ($register || $activate) ? 'minlength="12" autocomplete="new-password"' : 'autocomplete="current-password"' ?> required>
            </div>
            <?php if ($register || $activate): ?>
                <div class="form-field">
                    <label for="confirm_password">Confirm password</label>
                    <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required>
                </div>
            <?php endif ?>
            <button class="button button-primary button-full" id="submit" type="submit"><?= $register ? 'Create student account' : ($activate ? 'Activate account' : 'Sign in') ?></button>
        </form>
        <?php if ($register): ?>
            <p class="auth-switch">Already registered? <a href="<?= e(url('/login')) ?>">Sign in</a></p>
        <?php elseif ($admin_login): ?>
            <p class="auth-switch">First login? <a href="<?= e(url('/admin/activate')) ?>">Activate your administrator account</a></p>
        <?php else: ?>
            <p class="auth-switch">Student? <a href="<?= e(url('/register')) ?>">Register with Student ID</a></p>
        <?php endif ?>
    </div>
</section>
