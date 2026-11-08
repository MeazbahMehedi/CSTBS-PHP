<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/ticketing.php';
function route(string $method, string $pattern, callable $handler): void {}
require dirname(__DIR__) . '/src/accounts.php';

$command = $argv[1] ?? 'help';
try {
    if ($command === 'install') {
        $path = dirname(__DIR__) . '/config.local.php';
        $local = is_file($path) ? require $path : require dirname(__DIR__) . '/config.example.php';
        if (!is_file($path) || strlen((string) config('secret')) < 32) {
            $local['secret'] = bin2hex(random_bytes(32));
            file_put_contents($path, "<?php\nreturn " . var_export($local, true) . ";\n", LOCK_EX);
            @chmod($path, 0600);
            $GLOBALS['config']['secret'] = $local['secret'];
            echo "Saved a private signing secret in config.local.php. Existing database settings were preserved.\n";
        }
        $connection = new PDO('mysql:host=' . config('db_host') . ';port=' . config('db_port') . ';charset=utf8mb4', config('db_user'), config('db_password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $name = config('db_name');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) { throw new RuntimeException('Invalid database name.'); }
        $connection->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        db()->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
        echo "Database schema installed.\n";
    } elseif (in_array($command, ['admin-setup', 'admin-key', 'admin-reset'], true)) {
        $email = strtolower(trim($argv[2] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Provide a valid administrator email.'); }
        $user = one("SELECT * FROM users WHERE email=? AND role='admin'", [$email]);
        if ($command === 'admin-setup') {
            if (scalar("SELECT COUNT(*) FROM users WHERE role='admin'") > 0) { throw new RuntimeException('An administrator already exists. Promote additional administrators through Users.'); }
            $name = trim($argv[3] ?? 'University Administrator');
            query("INSERT INTO users(full_name,email,password_hash,role,password_ready) VALUES(?,?,?,'admin',0)", [$name, $email, hash_password(bin2hex(random_bytes(32)))]);
            $user = one('SELECT * FROM users WHERE id=?', [db()->lastInsertId()]);
        }
        if (!$user) { throw new RuntimeException('Administrator not found.'); }
        $key = issue_admin_key((int) $user['id'], $command === 'admin-reset');
        echo "Single-use activation key for {$email}, expires in 20 minutes\n{$key}\n";
    } elseif ($command === 'seed-events') {
        if (scalar('SELECT COUNT(*) FROM events')) { echo "Events already exist.\n"; exit; }
        transaction(function () {
            foreach ([['Campus Football Night', 'Football', 7], ['Track & Field Showcase', 'Athletics', 12], ['Alumni Soccer Classic', 'Soccer', 19], ['Stadium Music Fest', 'Music', 26]] as [$title, $category, $days]) {
                query('INSERT INTO events(title,category,description,venue,starts_at) VALUES(?,?,?,?,?)', [$title, $category, 'Join your campus community for an unforgettable stadium event.', 'Main Stadium', date('Y-m-d 18:30:00', strtotime('+' . $days . ' days'))]);
                $id = (int) db()->lastInsertId();
                query("INSERT INTO gates(event_id,name) VALUES(?,'Gate 1')", [$id]);
                $gate = (int) db()->lastInsertId();
                for ($row = 0; $row < 6; $row++) {
                    for ($seat = 1; $seat <= 10; $seat++) { query('INSERT INTO seats(event_id,gate_id,section,row_label,number) VALUES(?,?,?,?,?)', [$id, $gate, 'Main', chr(65 + $row), $seat]); }
                }
            }
        });
        echo "Created four events with 60 seats each.\n";
    } elseif ($command === 'mail-work') {
        $sent = 0;
        while ($sent < 100) {
            $processed = transaction(function () {
                $mail = one('SELECT * FROM mail_outbox WHERE sent_at IS NULL AND attempts<5 ORDER BY id LIMIT 1 FOR UPDATE');
                if (!$mail) { return false; }
                query('UPDATE mail_outbox SET attempts=attempts+1 WHERE id=?', [$mail['id']]);
                if (config('mail_transport') === 'smtp') {
                    $sender = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $sender->isSMTP();
                    $sender->Host = config('mail_host');
                    $sender->Port = (int) config('mail_port');
                    $sender->SMTPAuth = true;
                    $sender->Username = config('mail_username');
                    $sender->Password = config('mail_password');
                    $sender->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $sender->setFrom(config('mail_from'), 'CSTBS');
                    $sender->addAddress($mail['recipient']);
                    $sender->Subject = $mail['subject'];
                    $sender->Body = $mail['body'];
                    try { $sender->send(); } catch (Throwable $error) { error_log('CSTBS mail delivery failed for outbox ID ' . $mail['id']); return true; }
                } else {
                    file_put_contents(dirname(__DIR__) . '/storage/mail.log', json_encode($mail) . PHP_EOL, FILE_APPEND | LOCK_EX);
                }
                query('UPDATE mail_outbox SET sent_at=UTC_TIMESTAMP() WHERE id=?', [$mail['id']]);
                return true;
            });
            if (!$processed) { break; }
            $sent++;
        }
        echo "Processed {$sent} outbox entries.\n";
    } else {
        echo "Commands\n  install\n  seed-events\n  admin-setup EMAIL [NAME]\n  admin-key EMAIL\n  admin-reset EMAIL\n  mail-work\n";
    }
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); }
