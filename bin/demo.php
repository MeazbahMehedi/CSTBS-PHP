<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
if (config('production')) { throw new RuntimeException('Demo accounts are available only in development.'); }
$password = getenv('CSTBS_DEMO_PASSWORD') ?: '';
if (strlen($password) < 12 || strlen($password) > 128) { throw new RuntimeException('Set CSTBS_DEMO_PASSWORD privately to a 12–128 character password.'); }
transaction(function () use ($password) {
    foreach ([['Alex Morgan', 'demo.student@campus.example', 'DEMO1001', 'student'], ['Sam Taylor', 'demo.classmate@campus.example', 'DEMO1002', 'student'], ['Gate Officer', 'demo.staff@campus.example', null, 'staff'], ['Stadium Administrator', 'demo.admin@campus.example', null, 'admin']] as [$name, $email, $sid, $role]) {
        $existing = one('SELECT * FROM users WHERE email=?', [$email]);
        if ($existing) {
            if ($existing['role'] !== $role || !verify_password($password, $existing['password_hash'])) { throw new RuntimeException('A demo email is already in use with different credentials.'); }
            continue;
        }
        query('INSERT INTO users(full_name,email,student_id,password_hash,role) VALUES(?,?,?,?,?)', [$name, $email, $sid, hash_password($password), $role]);
    }
});
echo "Created or verified four fictional development accounts.\n";
