<?php
declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public function __construct(public int $status, string $message) { parent::__construct($message); }
}

$config = require dirname(__DIR__) . '/config.example.php';
$localConfig = dirname(__DIR__) . '/config.local.php';
if (is_file($localConfig)) {
    $config = array_replace($config, require $localConfig);
}
foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'base_path', 'secret'] as $key) {
    $value = getenv('CSTBS_' . strtoupper($key));
    if ($value !== false) { $config[$key] = $value; }
}
$config['base_path'] = rtrim($config['base_path'], '/');
date_default_timezone_set($config['timezone']);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new HttpError(503, 'Application dependencies are missing. Complete the Composer installation in the CSTBS-PHP folder, then reload this page.');
}
require $autoload;

function config(string $key): mixed { return $GLOBALS['config'][$key] ?? null; }

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) config('db_name'))) {
            throw new RuntimeException('Invalid database name.');
        }
        $pdo = new PDO('mysql:host=' . config('db_host') . ';port=' . config('db_port') .
            ';dbname=' . config('db_name') . ';charset=utf8mb4', config('db_user'), config('db_password'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function one(string $sql, array $params = []): ?array { return query($sql, $params)->fetch() ?: null; }
function all(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(); }
function scalar(string $sql, array $params = []): mixed { return query($sql, $params)->fetchColumn(); }
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = '/'): string { return config('base_path') . '/' . ltrim($path, '/'); }

function csrf_token(): string
{
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void
{
    $candidate = $_POST['csrf_token'] ?? '';
    if (!is_string($candidate) || !hash_equals(csrf_token(), $candidate)) {
        throw new HttpError(400, 'Refresh the form and try again.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) { return null; }
    $user = one('SELECT * FROM users WHERE id=?', [$_SESSION['user_id']]);
    if (!$user || !$user['password_ready'] || (int) $user['auth_version'] !== ($_SESSION['auth_version'] ?? 0)) {
        unset($_SESSION['user_id'], $_SESSION['auth_version'], $_SESSION['pending_booking']);
        return null;
    }
    return $user;
}

function require_user(array $roles = []): array
{
    $user = current_user();
    if (!$user) { redirect($roles === ['admin'] ? '/admin/login' : '/login'); }
    if ($roles && !in_array($user['role'], $roles, true)) { throw new HttpError(403, 'Access restricted.'); }
    return $user;
}

function role_home(array $user): string
{
    return match ($user['role']) {
        'admin' => '/admin',
        'staff' => '/entry/scan',
        default => '/dashboard',
    };
}

function sign_in(array $user): void
{
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $user['id'], 'auth_version' => (int) $user['auth_version']];
}
function redirect(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function flash(?string $message = null, string $category = 'info'): array
{
    if ($message !== null) { $_SESSION['flashes'][] = compact('message', 'category'); return []; }
    $messages = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $messages;
}
function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = dirname(__DIR__) . '/views/' . $view . '.php';
    require dirname(__DIR__) . '/views/layout.php';
}
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}

function transaction(callable $operation): mixed
{
    db()->beginTransaction();
    try { $result = $operation(); db()->commit(); return $result; }
    catch (Throwable $error) { if (db()->inTransaction()) { db()->rollBack(); } throw $error; }
}
function event_row(int $id, bool $lock = false): array
{
    $event = one('SELECT * FROM events WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
    if (!$event) { throw new HttpError(404, 'Event not found.'); }
    return $event;
}
function rate_limit(string $action, int $limit, int $seconds): void
{
    $bucket = hash('sha256', $action . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $now = time();
    query('INSERT INTO rate_limits(bucket,hits,resets_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE '
        . 'hits=IF(resets_at<=?,1,hits+1),resets_at=IF(resets_at<=?,?,resets_at)',
        [$bucket, $now + $seconds, $now, $now, $now + $seconds]);
    if ((int) scalar('SELECT hits FROM rate_limits WHERE bucket=?', [$bucket]) > $limit) {
        throw new HttpError(429, 'Too many attempts. Please try again later.');
    }
}
function text_input(string $key, int $min, int $max): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) { throw new HttpError(400, 'Invalid form value.'); }
    $value = trim($value);
    if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
        throw new HttpError(400, 'Check the length of ' . str_replace('_', ' ', $key) . '.');
    }
    return $value;
}
function student_id(string $value): string
{
    $value = strtoupper(trim($value));
    if (!preg_match('/^[A-Z0-9]{6,20}$/', $value)) { throw new HttpError(400, 'Student ID must contain 6–20 letters or numbers.'); }
    return $value;
}
function password_input(): string
{
    $password = $_POST['password'] ?? '';
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 128) {
        throw new HttpError(400, 'Use a password containing 12–128 characters.');
    }
    if (!hash_equals($password, (string) ($_POST['confirm_password'] ?? ''))) {
        throw new HttpError(400, 'Passwords must match.');
    }
    return $password;
}

function hash_password(string $password): string
{
    if (in_array('argon2id', password_algos(), true)) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    // Prehash keeps the full 128-byte input on PHP builds with bcrypt only.
    return 'sha384:' . password_hash(base64_encode(hash('sha384', $password, true)), PASSWORD_BCRYPT);
}

function verify_password(string $password, string $hash): bool
{
    if (str_starts_with($hash, 'sha384:')) {
        return password_verify(base64_encode(hash('sha384', $password, true)), substr($hash, 7));
    }
    return password_verify($password, $hash);
}

if (PHP_SAPI !== 'cli') {
    if (strlen((string) config('secret')) < 32) { throw new HttpError(503, 'Installation is incomplete. Run the CSTBS command-line installer to configure the private signing secret and database.'); }
    if (config('production') && config('mail_transport') !== 'smtp') {
        throw new RuntimeException('Configure SMTP before production startup.');
    }
    ini_set('session.use_strict_mode', '1');
    session_name('CSTBSPHP');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => (bool) config('production'), 'path' => url('/')]);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('Cache-Control: no-store, private');
    if (config('production')) { header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); }
}
