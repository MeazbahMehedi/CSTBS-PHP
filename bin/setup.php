<?php
declare(strict_types=1);

// Local operator tool only. Never expose installation or provisioning over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$root = dirname(__DIR__);
$command = $argv[1] ?? '';

function setup_input(): array
{
    $data = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($data)) { throw new RuntimeException('Invalid setup input.'); }
    return $data;
}
function setup_run(array $arguments): void
{
    $process = proc_open([PHP_BINARY, ...$arguments], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process) || proc_close($process) !== 0) { throw new RuntimeException('Setup command failed. Review the message above.'); }
}

try {
    if ($command === 'check') {
        $required = ['pdo_mysql', 'mbstring', 'gd', 'dom', 'xml', 'fileinfo', 'zip', 'openssl', 'curl'];
        echo json_encode(['supported' => PHP_VERSION_ID >= 80200, 'version' => PHP_VERSION,
            'missing' => array_values(array_filter($required, fn($name) => !extension_loaded($name))),
            'ini' => php_ini_loaded_file(), 'configured' => is_file($root . '/config.local.php')], JSON_THROW_ON_ERROR);
    } elseif ($command === 'configure') {
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'BASE_PATH', 'SECRET'] as $key) {
            if (getenv('CSTBS_' . $key) !== false) {
                throw new RuntimeException('Close terminals with CSTBS environment overrides before running laptop setup. No configuration was changed.');
            }
        }
        $input = setup_input();
        $path = $root . '/config.local.php';
        $exists = is_file($path);
        $config = require $root . '/config.example.php';
        if ($exists) {
            $config = array_replace($config, require $path);
        } else {
            foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'base_path'] as $key) {
                if (!array_key_exists($key, $input) || !is_string($input[$key])) { throw new RuntimeException('Missing database setup field.'); }
                $config[$key] = $input[$key];
            }
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $config['db_name'])
            || !ctype_digit((string) $config['db_port']) || (int) $config['db_port'] < 1 || (int) $config['db_port'] > 65535
            || !preg_match('/^[A-Za-z0-9.:-]+$/', (string) $config['db_host'])
            || !preg_match('#^(?:/[A-Za-z0-9._~-]+)*$#D', (string) $config['base_path'])) {
            throw new RuntimeException('Invalid database host, port, name, or website folder path.');
        }
        if ($config['secret'] !== '' && strlen((string) $config['secret']) < 32) {
            throw new RuntimeException('The existing signing secret is too short. Setup will not replace an existing secret automatically. Review config.local.php.');
        }
        try {
            new PDO('mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'] . ';charset=utf8mb4',
                $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (PDOException $error) {
            throw new RuntimeException('Cannot connect to MySQL. Start MySQL in XAMPP and check the database settings. Existing configuration was preserved.');
        }
        if (!$exists || $config['secret'] === '') {
            $config['secret'] = bin2hex(random_bytes(32));
            $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
            try {
                if (file_put_contents($temporary, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) { throw new RuntimeException('Cannot write private configuration. Check folder permissions.'); }
                @chmod($temporary, 0600);
                if (!rename($temporary, $path)) { throw new RuntimeException('Cannot save private configuration.'); }
            } finally { if (is_file($temporary)) { unlink($temporary); } }
        }
        echo 'Private configuration ready. Existing settings and signing secrets were preserved.' . PHP_EOL;
    } elseif (in_array($command, ['database', 'admin-status', 'admin', 'summary'], true)) {
        require $root . '/src/bootstrap.php';
        if ($command === 'database') {
            $server = new PDO('mysql:host=' . config('db_host') . ';port=' . config('db_port'), config('db_user'), config('db_password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $statement = $server->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='users'");
            $statement->execute([config('db_name')]);
            if ((int) $statement->fetchColumn() > 0) { setup_run([$root . '/bin/backup.php']); }
            setup_run([$root . '/bin/console.php', 'install']);
        } elseif ($command === 'admin-status') {
            echo json_encode(['exists' => (int) scalar("SELECT COUNT(*) FROM users WHERE role='admin'") > 0], JSON_THROW_ON_ERROR);
        } elseif ($command === 'admin') {
            $input = setup_input();
            $email = strtolower(trim((string) ($input['email'] ?? '')));
            $name = trim((string) ($input['name'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($name) < 2 || strlen($name) > 120) {
                throw new RuntimeException('Enter a valid administrator email and a name of 2 to 120 characters.');
            }
            // Reuse the normal provisioning rules. Existing administrators are never reset.
            setup_run([$root . '/bin/console.php', 'admin-setup', $email, $name]);
        } else {
            echo json_encode(['url' => 'http://localhost' . config('base_path') . '/',
                'activation' => 'http://localhost' . config('base_path') . '/admin/activate'], JSON_THROW_ON_ERROR);
        }
    } else { throw new RuntimeException('Unknown local setup command.'); }
} catch (Throwable $error) {
    // Database exceptions can include credentials/paths. Keep those out of the setup screen.
    fwrite(STDERR, ($error instanceof PDOException ? 'Database operation failed. Check MySQL and config.local.php.' : $error->getMessage()) . PHP_EOL);
    exit(1);
}
