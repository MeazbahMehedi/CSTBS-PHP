<?php
declare(strict_types=1);

function issue_admin_key(int $userId, bool $reset = false): string
{
    return transaction(function () use ($userId, $reset) {
        $user = one("SELECT * FROM users WHERE id=? AND role='admin' FOR UPDATE", [$userId]);
        if (!$user) { throw new HttpError(404, 'Administrator not found.'); }
        if ($user['password_ready'] && !$reset) { throw new HttpError(409, 'Use the explicit admin-reset command for an activated account.'); }
        if ($reset) {
            query('UPDATE users SET password_ready=0,auth_version=auth_version+1,password_hash=? WHERE id=?',
                [hash_password(bin2hex(random_bytes(32))), $userId]);
        }
        query('UPDATE admin_keys SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL', [$userId]);
        $key = bin2hex(random_bytes(24));
        query('INSERT INTO admin_keys(user_id,token_hash,expires_at) VALUES(?,?,?)',
            [$userId, hash('sha256', $key), gmdate('Y-m-d H:i:s', time() + 1200)]);
        return $key;
    });
}

route('GET', '/register', function () {
    if ($user = current_user()) { redirect(role_home($user)); }
    render('auth', ['title' => 'Student registration', 'mode' => 'register']);
});
route('POST', '/register', function () {
    if (current_user()) { throw new HttpError(403, 'Sign out before creating another account.'); }
    rate_limit('register', 5, 60);
    $name = text_input('full_name', 2, 120);
    $email = mb_strtolower(text_input('email', 3, 254));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new HttpError(400, 'Enter a valid email address.'); }
    $sid = student_id(text_input('student_id', 6, 20));
    $password = password_input();
    if ($password !== ($_POST['confirm_password'] ?? null)) { throw new HttpError(400, 'Passwords must match.'); }
    query("INSERT INTO users(full_name,email,student_id,password_hash,role) VALUES(?,?,?,?,'student')", [$name, $email, $sid, hash_password($password)]);
    sign_in(one('SELECT * FROM users WHERE id=?', [db()->lastInsertId()]));
    flash('Welcome to your student dashboard.', 'success');
    redirect('/dashboard');
});
foreach (['/login' => 'login', '/admin/login' => 'admin-login', '/admin/activate' => 'activate'] as $path => $mode) {
    route('GET', $path, function () use ($mode) {
        if ($user = current_user()) { redirect(role_home($user)); }
        render('auth', ['title' => 'Sign in', 'mode' => $mode]);
    });
}
foreach (['/login', '/admin/login'] as $path) {
    route('POST', $path, function () use ($path) {
        rate_limit('login', 5, 60);
        $email = mb_strtolower(text_input('email', 3, 254));
        $password = $_POST['password'] ?? '';
        if (!is_string($password) || strlen($password) > 128) { throw new HttpError(400, 'Invalid credentials.'); }
        $user = one('SELECT * FROM users WHERE email=?', [$email]);
        $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = verify_password($password, $hash);
        $adminLogin = $path === '/admin/login';
        if (!$user || !$valid || !$user['password_ready'] || (($user['role'] === 'admin') !== $adminLogin)) {
            http_response_code(400);
            render('auth', ['mode' => $adminLogin ? 'admin-login' : 'login', 'errors' => ['Invalid credentials or account not activated.']]);
            return;
        }
        sign_in($user);
        $destination = match ($user['role']) { 'admin' => '/admin', 'staff' => '/entry/scan', default => '/dashboard' };
        redirect($destination);
    });
}
route('POST', '/admin/activate', function () {
    rate_limit('activate', 5, 60);
    $email = mb_strtolower(text_input('email', 3, 254));
    $key = text_input('admin_key', 1, 128);
    $password = password_input();
    if ($password !== ($_POST['confirm_password'] ?? null)) { throw new HttpError(400, 'Passwords must match.'); }
    $user = transaction(function () use ($email, $key, $password) {
        $user = one("SELECT * FROM users WHERE email=? AND role='admin' FOR UPDATE", [$email]);
        $invitation = $user ? one('SELECT * FROM admin_keys WHERE user_id=? AND token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE', [$user['id'], hash('sha256', $key)]) : null;
        if (!$user || $user['password_ready'] || !$invitation) { throw new HttpError(400, 'Key invalid, expired or already used.'); }
        query('UPDATE admin_keys SET used_at=UTC_TIMESTAMP() WHERE id=?', [$invitation['id']]);
        query('UPDATE users SET password_hash=?,password_ready=1,auth_version=auth_version+1 WHERE id=?', [hash_password($password), $user['id']]);
        return one('SELECT * FROM users WHERE id=?', [$user['id']]);
    });
    sign_in($user);
    redirect('/admin');
});
route('POST', '/logout', function () {
    require_user();
    session_regenerate_id(true);
    $_SESSION = [];
    redirect('/');
});
route('GET', '/account', function () { render('account', ['title' => 'My account', 'user' => require_user()]); });
route('POST', '/account/student-id', function () {
    $user = require_user(['student']);
    query('UPDATE users SET student_id=? WHERE id=?', [student_id(text_input('student_id', 6, 20)), $user['id']]);
    flash('Student ID updated.', 'success');
    redirect('/account');
});
route('GET', '/dashboard', function () {
    $user = require_user(['student']);
    render('dashboard', ['title' => 'Student dashboard', 'user' => $user,
        'tickets' => all(TICKET_SELECT . " WHERE t.attendee_user_id=? AND t.status IN ('active','checked_in') ORDER BY t.id DESC", [$user['id']]),
        'events' => all('SELECT * FROM events WHERE starts_at>? ORDER BY starts_at LIMIT 3', [date('Y-m-d H:i:s')])]);
});
