<?php
declare(strict_types=1);

// Every run owns a new database and removes only that exact database afterward.
$databaseName = 'cstbs_test_' . bin2hex(random_bytes(5));
putenv('CSTBS_DB_NAME=' . $databaseName);
putenv('CSTBS_BASE_PATH=');
putenv('CSTBS_SECRET=' . bin2hex(random_bytes(32)));
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/ticketing.php';
function route(string $method, string $pattern, callable $handler): void {}
require dirname(__DIR__) . '/src/accounts.php';
$_SESSION = [];
$adminConnection = new PDO('mysql:host=' . config('db_host') . ';port=' . config('db_port'), config('db_user'), config('db_password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
$server = null;

function check(bool $condition, string $label): void
{
    if (!$condition) { throw new RuntimeException('FAILED ' . $label); }
    $GLOBALS['checks']++;
    echo 'PASS ' . $label . PHP_EOL;
}
final class Browser
{
    private CurlHandle $curl;
    public function __construct(private string $base)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 30]);
    }
    public function request(string $path, ?array $data = null): array
    {
        curl_setopt_array($this->curl, [CURLOPT_URL => $this->base . $path, CURLOPT_POST => $data !== null]);
        if ($data !== null) {
            $multipart = count(array_filter($data, fn($value) => $value instanceof CURLFile)) > 0;
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));
        }
        $raw = curl_exec($this->curl);
        if ($raw === false) { throw new RuntimeException(curl_error($this->curl)); }
        $size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        return ['status' => curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE), 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
    }
    public function csrf(string $path): string
    {
        $response = $this->request($path);
        if ($response['status'] !== 200 || !preg_match('/name="csrf_token" value="([^"]+)"/', $response['body'], $match)) {
            throw new RuntimeException('Missing form token at ' . $path . ' status ' . $response['status']);
        }
        return $match[1];
    }
    public function post(string $path, array $data = [], string $form = '/account'): array
    {
        return $this->request($path, ['csrf_token' => $this->csrf($form), ...$data]);
    }
}

try {
    $adminConnection->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    db()->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    db()->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    check((int) scalar('SELECT COUNT(*) FROM schema_versions') === 1, 'schema installation is idempotent');
    $port = random_int(18000, 25000);
    $log = dirname(__DIR__) . '/storage/test-server.log';
    $server = proc_open([PHP_BINARY, '-d', 'extension=gd', '-S', '127.0.0.1:' . $port, 'router.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname(__DIR__));
    if (!is_resource($server)) { throw new RuntimeException('Could not start test server.'); }
    fclose($pipes[0]);
    $base = 'http://127.0.0.1:' . $port;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1', $port);
        if ($socket) { fclose($socket); break; }
        usleep(100000);
    }
    $public = new Browser($base);
    $alice = new Browser($base);
    $bob = new Browser($base);
    $admin = new Browser($base);
    $staff = new Browser($base);
    check($public->request('/health')['status'] === 200, 'database health endpoint');
    check($public->request('/')['status'] === 200, 'public homepage');
    check($public->request('/config.local.php')['status'] === 404, 'private configuration is inaccessible');
    check($public->request('/database/schema.sql')['status'] === 404, 'schema source is inaccessible');
    check($public->request('/admin')['status'] === 303, 'anonymous administration requires login');
    check($public->request('/register', [])['status'] === 400, 'missing CSRF rejected');
    $password = 'IntegrationPassword123!';
    foreach ([[$alice, 'Alice Student', 'alice@campus.example', 'ALICE001'], [$bob, 'Bob Student', 'bob@campus.example', 'BOB00001']] as [$browser, $name, $email, $sid]) {
        $response = $browser->post('/register', ['full_name' => $name, 'email' => $email, 'student_id' => $sid, 'password' => $password, 'confirm_password' => $password, 'role' => 'admin'], '/register');
        check($response['status'] === 303, 'register ' . $sid);
    }
    $aliceUser = one('SELECT * FROM users WHERE student_id=?', ['ALICE001']);
    $bobUser = one('SELECT * FROM users WHERE student_id=?', ['BOB00001']);
    check($aliceUser['role'] === 'student', 'forged registration role cannot escalate');
    check(password_verify($password, $aliceUser['password_hash']), 'password is hashed and verifiable');
    check($alice->request('/admin')['status'] === 403, 'student cannot access administration');
    check($public->request('/account')['status'] === 303, 'separate browser remains anonymous');
    query("INSERT INTO users(full_name,email,password_hash,role,password_ready) VALUES('Administrator','admin@campus.example',?,'admin',0)", [password_hash($password, PASSWORD_DEFAULT)]);
    $adminId = (int) db()->lastInsertId();
    $key = issue_admin_key($adminId);
    $response = $admin->post('/admin/activate', ['email' => 'admin@campus.example', 'admin_key' => $key, 'password' => $password, 'confirm_password' => $password], '/admin/activate');
    check($response['status'] === 303, 'one-time administrator activation');
    check($public->post('/admin/activate', ['email' => 'admin@campus.example', 'admin_key' => $key, 'password' => $password, 'confirm_password' => $password], '/admin/activate')['status'] === 400, 'activation key replay rejected');
    check($admin->request('/admin')['status'] === 200, 'activated administrator dashboard');
    $csrf = $admin->csrf('/admin/events/new');
    check($admin->request('/admin/events/new', ['csrf_token' => $csrf . 'tampered'])['status'] === 400, 'tampered CSRF rejected');
    $eventData = ['title' => 'Integration Cup', 'category' => 'Sports', 'description' => 'A test match at the university stadium.', 'venue' => 'Campus Stadium', 'starts_at' => date('Y-m-d\TH:i', time() + 86400 * 5), 'price' => '-999'];
    check($admin->post('/admin/events/new', $eventData, '/admin/events/new')['status'] === 303, 'administrator creates event');
    $event = one('SELECT * FROM events WHERE title=?', ['Integration Cup']);
    $eventId = (int) $event['id'];
    $gate = one('SELECT * FROM gates WHERE event_id=?', [$eventId]);
    $gateId = (int) $gate['id'];
    $seats = all('SELECT * FROM seats WHERE event_id=? ORDER BY id', [$eventId]);
    check(count($seats) === 60, 'new event has 60 seats');
    check((float) $event['price'] === 0.0, 'forged price is ignored');
    check(!$event['checkin_enabled'] && !$gate['is_open'], 'new event entry and gate start closed');
    $selection = $seats[0]['id'] . ',' . $seats[1]['id'];
    check($alice->post('/events/' . $eventId . '/booking/review', ['seat_ids' => $selection])['status'] === 200, 'review selected seats');
    $confirmation = ['attendee_student_id' => ['ALICE001', 'BOB00001'], 'attendee_name' => ['Alice Student', 'Wrong Name']];
    check($alice->post('/events/' . $eventId . '/booking/confirm', $confirmation)['status'] === 400, 'registered attendee name must match');
    check((int) scalar('SELECT COUNT(*) FROM bookings') === 0, 'failed group booking rolls back every seat');
    check((int) scalar('SELECT COUNT(*) FROM mail_outbox') === 0, 'failed booking sends no notification');
    $alice->post('/events/' . $eventId . '/booking/review', ['seat_ids' => $selection]);
    $confirmation['attendee_name'][1] = 'Bob Student';
    check($alice->post('/events/' . $eventId . '/booking/confirm', $confirmation)['status'] === 303, 'confirm atomic classmate booking');
    check((int) scalar('SELECT COUNT(*) FROM bookings') === 2, 'two seats reserved together');
    check((int) scalar('SELECT COUNT(*) FROM mail_outbox') === 2, 'notifications queued after successful booking');
    check($alice->post('/events/' . $eventId . '/booking/confirm', $confirmation)['status'] === 303, 'confirmation replay redirects safely');
    check((int) scalar('SELECT COUNT(*) FROM bookings') === 2, 'confirmation replay creates no duplicate');
    check($bob->request('/events/' . $eventId . '/seats')['status'] === 303, 'classmate cannot obtain second ticket');
    $ticket = one('SELECT * FROM tickets WHERE attendee_user_id=?', [$aliceUser['id']]);
    $token = ticket_token($ticket);
    check(resolve_ticket($token)['id'] === $ticket['id'], 'signed token resolves to exact ticket');
    check(resolve_ticket($token . 'x') === null, 'QR tampering rejected');
    check($public->request('/tickets/' . $ticket['id'])['status'] === 303, 'ticket requires authentication');
    check($alice->request('/tickets/' . $ticket['id'] . '/qr.svg')['status'] === 200, 'private SVG QR generation');
    $pdf = $alice->request('/tickets/' . $ticket['id'] . '/pdf');
    check($pdf['status'] === 200 && str_starts_with($pdf['body'], '%PDF'), 'ticket PDF download');
    check($alice->request('/entry/verify/' . $token)['status'] === 403, 'student cannot scan admissions');
    query("INSERT INTO users(full_name,email,password_hash,role) VALUES('Gate Officer','staff@campus.example',?,'staff')", [password_hash($password, PASSWORD_DEFAULT)]);
    check($staff->post('/login', ['email' => 'staff@campus.example', 'password' => $password], '/login')['status'] === 303, 'staff password login');
    check(str_contains($staff->request('/entry/verify/' . $token)['body'], 'ENTRY NOT OPEN'), 'closed entry verdict');
    check($staff->post('/admin/events/' . $eventId . '/entry/open')['status'] === 403, 'staff cannot open entry');
    check($admin->post('/admin/events/' . $eventId . '/entry/open')['status'] === 303, 'administrator opens gates');
    query("INSERT INTO gates(event_id,name,is_open) VALUES(?,'South Gate',1)", [$eventId]);
    $wrongGate = (int) db()->lastInsertId();
    check(str_contains($staff->request('/entry/verify/' . $token . '?gate_id=' . $wrongGate)['body'], 'WRONG GATE'), 'wrong gate verdict');
    check($staff->post('/entry/check-in/' . $token, ['gate_id' => $wrongGate], '/entry/scan')['status'] === 409, 'wrong gate cannot admit');
    check($staff->post('/entry/check-in/' . $token, ['gate_id' => $gateId], '/entry/scan')['status'] === 303, 'first authorized check-in succeeds');
    check($staff->post('/entry/check-in/' . $token, ['gate_id' => $gateId], '/entry/scan')['status'] === 409, 'duplicate check-in rejected');
    check($alice->post('/bookings/' . $ticket['booking_id'] . '/cancel')['status'] === 409, 'checked-in ticket cannot be cancelled');
    check($bob->post('/bookings/' . $ticket['booking_id'] . '/cancel')['status'] === 404, 'booking ownership enforced');
    $bobTicket = one('SELECT * FROM tickets WHERE attendee_user_id=?', [$bobUser['id']]);
    check($alice->post('/bookings/' . $bobTicket['booking_id'] . '/cancel')['status'] === 303, 'booker can cancel own classmate reservation');
    check(one('SELECT * FROM tickets WHERE id=?', [$bobTicket['id']])['status'] === 'cancelled', 'cancellation invalidates signed pass');
    check($admin->post('/admin/events/' . $eventId . '/delete')['status'] === 409, 'booking history blocks event deletion');
    foreach (['/admin/events', '/admin/events/' . $eventId . '/seats', '/admin/events/' . $eventId . '/gates', '/admin/users', '/admin/bookings', '/admin/reports', '/admin/check-ins'] as $path) {
        check($admin->request($path)['status'] === 200, 'administrator screen ' . $path);
    }
    $csv = $admin->request('/admin/reports/export?format=csv');
    check($csv['status'] === 200 && str_contains($csv['body'], 'Integration Cup'), 'private CSV report');
    $report = $admin->request('/admin/reports/export?format=pdf');
    check($report['status'] === 200 && str_starts_with($report['body'], '%PDF'), 'private PDF report');
    check($bob->request('/admin/reports/export?format=csv')['status'] === 403, 'report authorization');
    check($admin->post('/admin/users/' . $adminId . '/role', ['role' => 'student'])['status'] === 409, 'self-demotion and last admin protection');
    $newAdmin = new Browser($base);
    $oldCsrf = $newAdmin->csrf('/admin/login');
    check($newAdmin->request('/admin/login', ['csrf_token' => $oldCsrf, 'email' => 'admin@campus.example', 'password' => $password])['status'] === 303, 'normal admin password login after activation');
    check($newAdmin->request('/admin/events/new', ['csrf_token' => $oldCsrf])['status'] === 400, 'login rotates stale CSRF token');
    issue_admin_key($adminId, true);
    check($newAdmin->request('/admin')['status'] === 303, 'admin reset revokes existing sessions');
    echo "\n{$checks} integration checks passed.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (preg_match('/^cstbs_test_[a-f0-9]{10}$/', $databaseName)) { $adminConnection->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`'); }
}
