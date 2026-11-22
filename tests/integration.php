<?php
declare(strict_types=1);

// Every run owns a new database and removes only that exact database afterward.
$externalUrl = getenv('CSTBS_TEST_URL') ?: '';
$databaseName = $externalUrl ? (getenv('CSTBS_TEST_DATABASE') ?: '') : 'cstbs_test_' . bin2hex(random_bytes(5));
if (!preg_match('/^cstbs_test_[a-z0-9_]+$/', $databaseName)) { throw new RuntimeException('A dedicated cstbs_test_ database is required.'); }
putenv('CSTBS_DB_NAME=' . $databaseName);
putenv('CSTBS_BASE_PATH=');
if (!$externalUrl) { putenv('CSTBS_SECRET=' . bin2hex(random_bytes(32))); }
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/ticketing.php';
function route(string $method, string $pattern, callable $handler): void {}
require dirname(__DIR__) . '/src/accounts.php';
$_SESSION = [];
$adminConnection = new PDO('mysql:host=' . config('db_host') . ';port=' . config('db_port'), config('db_user'), config('db_password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
$server = null;
$ownsDatabase = false;

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
    $ownsDatabase = true;
    db()->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    db()->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    check((int) scalar('SELECT COUNT(*) FROM schema_versions') === 1, 'schema installation is idempotent');
    query('ALTER TABLE events DROP COLUMN deleted_at');
    require dirname(__DIR__) . '/src/migrations.php';
    upgrade_database();
    upgrade_database();
    check((int) scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='deleted_at'") === 1, 'existing database gains event deletion field safely');
    check((int) scalar('SELECT COUNT(*) FROM schema_versions') === 2, 'event migration is idempotent');
    if ($externalUrl) {
        $base = rtrim($externalUrl, '/');
    } else {
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
    }
    $public = new Browser($base);
    $alice = new Browser($base);
    $bob = new Browser($base);
    $admin = new Browser($base);
    $staff = new Browser($base);
    $setupRoot = dirname(__DIR__) . '/storage/setup-test-' . bin2hex(random_bytes(5));
    mkdir($setupRoot . '/src', 0700, true);
    mkdir($setupRoot . '/views', 0700, true);
    $setupFiles = ['index.php', 'config.example.php', 'src/bootstrap.php', 'views/layout.php', 'views/error.php'];
    foreach ($setupFiles as $file) { copy(dirname(__DIR__) . '/' . $file, $setupRoot . '/' . $file); }
    try {
        $script = '$_SERVER["REQUEST_URI"]="/CSTBS-PHP/"; $_SERVER["REQUEST_METHOD"]="GET"; require ' . var_export($setupRoot . '/index.php', true) . '; fwrite(STDERR,"STATUS=".http_response_code());';
        $setupProcess = proc_open([PHP_BINARY, '-r', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $setupPipes, $setupRoot, array_replace(getenv(), ['CSTBS_BASE_PATH' => '/CSTBS-PHP']));
        fclose($setupPipes[0]);
        $setupHtml = stream_get_contents($setupPipes[1]);
        $setupStatus = stream_get_contents($setupPipes[2]);
        fclose($setupPipes[1]); fclose($setupPipes[2]); proc_close($setupProcess);
        check(str_contains($setupStatus, 'STATUS=503'), 'missing dependencies report incomplete installation');
        check(str_contains($setupHtml, 'Composer installation'), 'missing dependency page explains setup action');
        check(str_contains($setupHtml, '/CSTBS-PHP/public/assets/css/styles.css'), 'setup error preserves subfolder stylesheet URLs');
    } finally {
        foreach ($setupFiles as $file) { unlink($setupRoot . '/' . $file); }
        rmdir($setupRoot . '/src'); rmdir($setupRoot . '/views'); rmdir($setupRoot);
    }
    check($public->request('/health')['status'] === 200, 'database health endpoint');
    check($public->request('/')['status'] === 200, 'public homepage');
    check(in_array($public->request('/config.local.php')['status'], [403, 404], true), 'private configuration is inaccessible');
    check(in_array($public->request('/database/schema.sql')['status'], [403, 404], true), 'schema source is inaccessible');
    foreach (['/Setup-CSTBS.cmd', '/bin/setup.ps1', '/bin/setup.php'] as $setupPath) {
        check(in_array($public->request($setupPath)['status'], [403, 404], true), 'local installer is inaccessible over HTTP ' . $setupPath);
    }
    check($public->request('/admin')['status'] === 303, 'anonymous administration requires login');
    check($public->request('/register', [])['status'] === 400, 'missing CSRF rejected');
    $password = 'IntegrationPassword123!';
    $longPassword = ' ' . str_repeat('Ab9!', 25) . ' ';
    $longHash = hash_password($longPassword);
    check(verify_password($longPassword, $longHash), 'full-length passwords preserve whitespace');
    check(!verify_password($longPassword . 'x', $longHash), 'long password suffix remains significant');
    foreach ([[$alice, 'Alice Student', 'alice@campus.example', 'ALICE001'], [$bob, 'Bob Student', 'bob@campus.example', 'BOB00001']] as [$browser, $name, $email, $sid]) {
        $response = $browser->post('/register', ['full_name' => $name, 'email' => $email, 'student_id' => $sid, 'password' => $password, 'confirm_password' => $password, 'role' => 'admin'], '/register');
        check($response['status'] === 303, 'register ' . $sid);
    }
    $aliceUser = one('SELECT * FROM users WHERE student_id=?', ['ALICE001']);
    $bobUser = one('SELECT * FROM users WHERE student_id=?', ['BOB00001']);
    check($aliceUser['role'] === 'student', 'forged registration role cannot escalate');
    check(verify_password($password, $aliceUser['password_hash']), 'password is hashed and verifiable');
    check($alice->request('/admin')['status'] === 403, 'student cannot access administration');
    check($public->request('/account')['status'] === 303, 'separate browser remains anonymous');
    query("INSERT INTO users(full_name,email,password_hash,role,password_ready) VALUES('Administrator','admin@campus.example',?,'admin',0)", [password_hash($password, PASSWORD_DEFAULT)]);
    $adminId = (int) db()->lastInsertId();
    $key = issue_admin_key($adminId);
    $response = $admin->post('/admin/activate', ['email' => 'admin@campus.example', 'admin_key' => $key, 'password' => $password, 'confirm_password' => $password], '/admin/activate');
    check($response['status'] === 303, 'one-time administrator activation');
    check($public->post('/admin/activate', ['email' => 'admin@campus.example', 'admin_key' => $key, 'password' => $password, 'confirm_password' => $password], '/admin/activate')['status'] === 400, 'activation key replay rejected');
    check($admin->request('/admin')['status'] === 200, 'activated administrator dashboard');
    check($admin->request('/dashboard')['status'] === 403, 'administrator cannot open student dashboard');
    check($admin->request('/bookings')['status'] === 403, 'administrator cannot open student booking panel');
    check($alice->request('/admin/login')['status'] === 303, 'signed-in student returns to own panel instead of admin login');
    check($alice->request('/entry/scan')['status'] === 403, 'student cannot open scanner panel');
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
    check($staff->request('/entry/check-ins?gate_id=' . $gateId)['status'] === 200, 'staff can view gate attendance');
    check($alice->request('/entry/check-ins')['status'] === 403, 'attendance is restricted to staff and administrators');
    check($staff->post('/entry/check-in/' . $token, ['gate_id' => $gateId], '/entry/scan')['status'] === 409, 'duplicate check-in rejected');
    $usedPage = $staff->request('/entry/verify/' . $token . '?gate_id=' . $gateId)['body'];
    $record = ticket_row((int) $ticket['id']);
    check(str_contains($usedPage, 'Ticket already used'), 'used ticket shows an explicit used result');
    check(str_contains($usedPage, e(admission_time($record['checked_in_at']))), 'used ticket shows local admission time');
    check(str_contains($usedPage, 'Gate Officer') && str_contains($usedPage, 'Gate 1'), 'used ticket shows admitting person and gate');
    check(!str_contains($usedPage, '>Confirm entry<'), 'used ticket has no second entry action');
    check(str_contains($alice->request('/tickets/' . $ticket['id'])['body'], 'Ticket already used'), 'student ticket displays recorded usage');

    check($alice->post('/bookings/' . $ticket['booking_id'] . '/cancel')['status'] === 409, 'checked-in ticket cannot be cancelled');
    check($bob->post('/bookings/' . $ticket['booking_id'] . '/cancel')['status'] === 404, 'booking ownership enforced');
    $bobTicket = one('SELECT * FROM tickets WHERE attendee_user_id=?', [$bobUser['id']]);
    check($alice->post('/bookings/' . $bobTicket['booking_id'] . '/cancel')['status'] === 303, 'booker can cancel own classmate reservation');
    check(one('SELECT * FROM tickets WHERE id=?', [$bobTicket['id']])['status'] === 'cancelled', 'cancellation invalidates signed pass');
    check($alice->post('/admin/events/' . $eventId . '/delete')['status'] === 403, 'students cannot delete events');
    foreach (['/admin/events', '/admin/events/' . $eventId . '/seats', '/admin/events/' . $eventId . '/gates', '/admin/users', '/admin/bookings', '/admin/reports', '/admin/check-ins'] as $path) {
        check($admin->request($path)['status'] === 200, 'administrator screen ' . $path);
    }
    $csv = $admin->request('/admin/reports/export?format=csv');
    check($csv['status'] === 200 && str_contains($csv['body'], 'Integration Cup'), 'private CSV report');
    $report = $admin->request('/admin/reports/export?format=pdf');
    check($report['status'] === 200 && str_starts_with($report['body'], '%PDF'), 'private PDF report');
    check($bob->request('/admin/reports/export?format=csv')['status'] === 403, 'report authorization');
    check($admin->post('/admin/users/' . $adminId . '/role', ['role' => 'student'])['status'] === 409, 'self-demotion and last admin protection');
    $admin->post('/admin/events/' . $eventId . '/sections/create', ['section' => 'East', 'rows' => 2, 'seat_count' => 3, 'gate_id' => $gateId]);
    check((int) scalar("SELECT COUNT(*) FROM seats WHERE event_id=? AND section='East'", [$eventId]) === 6, 'create bounded seat section');
    $admin->post('/admin/events/' . $eventId . '/sections/East/rename', ['new_name' => 'Premium']);
    check((int) scalar("SELECT COUNT(*) FROM seats WHERE event_id=? AND section='Premium'", [$eventId]) === 6, 'rename seat section');
    $admin->post('/admin/events/' . $eventId . '/sections/Premium/delete');
    check((int) scalar("SELECT COUNT(*) FROM seats WHERE event_id=? AND section='Premium'", [$eventId]) === 0, 'delete unbooked section');
    check($admin->post('/admin/events/' . $eventId . '/sections/Main/delete')['status'] === 409, 'historical section cannot be deleted');
    $admin->post('/admin/seats/' . $seats[4]['id'] . '/toggle-block');
    check((bool) scalar('SELECT is_blocked FROM seats WHERE id=?', [$seats[4]['id']]), 'block unreserved seat');
    check($bob->post('/events/' . $eventId . '/booking/review', ['seat_ids' => (string) $seats[4]['id']])['status'] === 409, 'blocked seat rejected by server');
    check($admin->post('/admin/seats/' . $seats[0]['id'] . '/toggle-block')['status'] === 409, 'occupied seat cannot be blocked');
    check($admin->post('/admin/events/' . $eventId . '/sections/Main/gate', ['gate_id' => $wrongGate])['status'] === 409, 'admitted section cannot move gates');
    $eventData['title'] = 'Disposable Event';
    $admin->post('/admin/events/new', $eventData, '/admin/events/new');
    $disposable = (int) scalar("SELECT id FROM events WHERE title='Disposable Event'");
    $otherGate = (int) scalar('SELECT id FROM gates WHERE event_id=?', [$disposable]);
    check($admin->post('/admin/events/' . $eventId . '/sections/Main/gate', ['gate_id' => $otherGate])['status'] === 400, 'cross-event gate assignment rejected');
    check($admin->post('/admin/events/' . $disposable . '/delete')['status'] === 303, 'unbooked event can be deleted');
    check((int) scalar('SELECT COUNT(*) FROM gates WHERE event_id=?', [$disposable]) === 1, 'deleted event retains inventory for history');
    check((bool) scalar('SELECT deleted_at FROM events WHERE id=?', [$disposable]), 'deleted event is marked and hidden');
    try {
        query('INSERT INTO bookings(user_id,seat_id) VALUES(?,?)', [$bobUser['id'], $seats[0]['id']]);
        throw new RuntimeException('Duplicate active seat was accepted');
    } catch (PDOException $error) { check($error->getCode() === '23000', 'database unique index rejects active seat collision'); }
    $charlie = new Browser($base);
    check($charlie->post('/register', ['full_name' => 'Charlie Student', 'email' => 'charlie@campus.example', 'student_id' => 'CHARLIE1', 'password' => $password, 'confirm_password' => $password], '/register')['status'] === 303, 'register third attendee for race test');
    $seatId = (string) $seats[5]['id'];
    check($bob->post('/events/' . $eventId . '/booking/review', ['seat_ids' => $seatId])['status'] === 200, 'first browser reviews available seat');
    check($charlie->post('/events/' . $eventId . '/booking/review', ['seat_ids' => $seatId])['status'] === 200, 'second browser reviews same available seat');
    check($bob->post('/events/' . $eventId . '/booking/confirm')['status'] === 303, 'first reservation wins reviewed seat');
    check($charlie->post('/events/' . $eventId . '/booking/confirm')['status'] === 409, 'stale second confirmation cannot double-book');
    $options = new \chillerlan\QRCode\QROptions(['outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG, 'outputBase64' => false, 'scale' => 8]);
    $qrPath = dirname(__DIR__) . '/storage/test-qr.png';
    file_put_contents($qrPath, (new \chillerlan\QRCode\QRCode($options))->render($token));
    $photo = $staff->post('/entry/scan-photo', ['gate_id' => $gateId, 'qr_photo' => new CURLFile($qrPath, 'image/png', 'ticket.png')], '/entry/scan');
    check($photo['status'] === 303, 'uploaded QR photograph is decoded locally');
    unlink($qrPath);
    check($staff->request('/entry/scan?code=' . urlencode($token) . '&gate_id=' . $gateId)['status'] === 303, 'manual signed code routes to verification');
    query('INSERT INTO events(title,category,description,venue,starts_at,checkin_enabled) VALUES(?,?,?,?,?,1)', ['Concurrent admission', 'Sports', 'Concurrency fixture event.', 'Test Stadium', date('Y-m-d H:i:s', time()+86400)]);
    $raceEvent = (int) db()->lastInsertId();
    query("INSERT INTO gates(event_id,name,is_open) VALUES(?,'Race Gate',1)", [$raceEvent]);
    $raceGate = (int) db()->lastInsertId();
    query("INSERT INTO seats(event_id,gate_id,section,row_label,number) VALUES(?,?,'Main','A',1)", [$raceEvent,$raceGate]);
    $raceSeat = (int) db()->lastInsertId();
    $raceTicket = confirm_booking($raceEvent, [$raceSeat], $aliceUser, [], [])[0];
    $staffId = (int) scalar("SELECT id FROM users WHERE email='staff@campus.example'");
    $barrier = dirname(__DIR__) . '/storage/checkin-' . bin2hex(random_bytes(5));
    $workers = [];
    try {
        foreach ([$staffId, $adminId] as $actorId) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/checkin_worker.php', (string)$raceTicket, (string)$raceGate, (string)$actorId, $barrier], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $workerPipes, dirname(__DIR__));
            fclose($workerPipes[0]);
            $workers[] = [$process, $workerPipes];
        }
        $deadline = microtime(true)+5;
        while (count(glob($barrier . '.*.ready')) < 2 && microtime(true)<$deadline) { usleep(1000); }
        check(count(glob($barrier . '.*.ready')) === 2, 'two independent scanners are ready concurrently');
        touch($barrier);
        $outcomes = [];
        foreach ($workers as [$process,$workerPipes]) {
            $outcomes[] = trim(stream_get_contents($workerPipes[1]));
            $error = stream_get_contents($workerPipes[2]);
            fclose($workerPipes[1]); fclose($workerPipes[2]);
            check(proc_close($process) === 0, 'scanner worker completes without database failure ' . $error);
        }
        $workers = [];
        sort($outcomes);
        check($outcomes === ['admitted','rejected'], 'concurrent scanners admit the ticket exactly once');
        $raceRecord = ticket_row($raceTicket);
        check($raceRecord['checked_in_at'] && $raceRecord['checked_in_by_name'] && (int)$raceRecord['checked_in_gate_id']===$raceGate, 'concurrent admission retains its winning audit record');
    } finally {
        foreach ($workers as [$process,$workerPipes]) { if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
        foreach (glob($barrier . '.*.ready') as $readyFile) { unlink($readyFile); }
        if (is_file($barrier)) { unlink($barrier); }
    }
    $ticketCount = (int) scalar('SELECT COUNT(*) FROM tickets');
    $bookingCount = (int) scalar('SELECT COUNT(*) FROM bookings');
    check($admin->post('/admin/events/' . $eventId . '/delete')['status'] === 303, 'administrator can delete a booked event');
    check((int) scalar('SELECT COUNT(*) FROM tickets') === $ticketCount, 'deletion retains ticket history');
    check((int) scalar('SELECT COUNT(*) FROM bookings') === $bookingCount, 'deletion retains booking history');
    check(one('SELECT * FROM tickets WHERE id=?', [$ticket['id']])['status'] === 'checked_in', 'deletion retains recorded admission');
    check((int) scalar("SELECT COUNT(*) FROM tickets WHERE event_id=? AND status IN ('active','unassigned')", [$eventId]) === 0, 'event deletion invalidates unused passes');
    check((int) scalar('SELECT COUNT(*) FROM gates WHERE event_id=? AND is_open=1', [$eventId]) === 0, 'deleted event gates close');
    check($public->request('/events/' . $eventId)['status'] === 404, 'deleted event details are hidden');
    check($public->request('/api/events/' . $eventId . '/seats')['status'] === 404, 'deleted event availability is hidden');
    check(!str_contains($public->request('/events')['body'], 'Integration Cup'), 'deleted event removed from catalog');
    check(str_contains($admin->request('/admin/events?status=deleted')['body'], 'Integration Cup'), 'administrator can inspect deleted event history');
    $mailCount = (int) scalar('SELECT COUNT(*) FROM mail_outbox');
    check($admin->post('/admin/events/' . $eventId . '/delete')['status'] === 303, 'repeated deletion is safe');
    check((int) scalar('SELECT COUNT(*) FROM mail_outbox') === $mailCount, 'repeated deletion does not duplicate notifications');
    $newAdmin = new Browser($base);
    $oldCsrf = $newAdmin->csrf('/admin/login');
    check($newAdmin->request('/admin/login', ['csrf_token' => $oldCsrf, 'email' => 'admin@campus.example', 'password' => $password])['status'] === 303, 'normal admin password login after activation');
    check($newAdmin->request('/admin/events/new', ['csrf_token' => $oldCsrf])['status'] === 400, 'login rotates stale CSRF token');
    issue_admin_key($adminId, true);
    check($newAdmin->request('/admin')['status'] === 303, 'admin reset revokes existing sessions');
    echo "\n{$checks} integration checks passed.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($ownsDatabase) { $adminConnection->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`'); }
}
