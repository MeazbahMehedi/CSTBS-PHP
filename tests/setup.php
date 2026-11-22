<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$fixture = $root . '/storage/setup-cli-' . bin2hex(random_bytes(5));
$name = 'cstbs_test_setup_' . bin2hex(random_bytes(5));
$settings = require $root . '/config.example.php';
if (is_file($root . '/config.local.php')) { $settings = array_replace($settings, require $root . '/config.local.php'); }
foreach (['db_host', 'db_port', 'db_user', 'db_password'] as $key) {
    $value = getenv('CSTBS_' . strtoupper($key));
    if ($value !== false) { $settings[$key] = $value; }
}
$settings['db_name'] = $name;
$settings['base_path'] = '/Setup-Fixture';
$settings['secret'] = '';
$settings['db_port'] = (string) $settings['db_port'];
$environment = getenv();
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'BASE_PATH', 'SECRET'] as $key) { unset($environment['CSTBS_' . $key]); }
$checks = 0;
$connection = new PDO('mysql:host=' . $settings['db_host'] . ';port=' . $settings['db_port'], $settings['db_user'], $settings['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function setup_check(bool $condition, string $description): void
{
    if (!$condition) { throw new RuntimeException('FAILED ' . $description); }
    $GLOBALS['checks']++;
    echo 'PASS ' . $description . PHP_EOL;
}
function setup_call(string $command, ?array $input = null, array $overrides = []): array
{
    $process = proc_open([PHP_BINARY, $GLOBALS['fixture'] . '/bin/setup.php', $command],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $GLOBALS['fixture'], array_replace($GLOBALS['environment'], $overrides));
    if (!is_resource($process)) { throw new RuntimeException('Could not launch installer.'); }
    if ($input !== null) { fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR)); }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => $output, 'error' => $error];
}
try {
    mkdir($fixture, 0700, true);
    foreach (['bin', 'src', 'database', 'vendor', 'storage'] as $directory) { mkdir($fixture . '/' . $directory, 0700); }
    foreach (['bin/setup.php', 'bin/console.php', 'bin/backup.php', 'src/bootstrap.php', 'src/accounts.php', 'src/ticketing.php', 'src/migrations.php', 'database/schema.sql', 'config.example.php'] as $file) {
        copy($root . '/' . $file, $fixture . '/' . $file);
    }
    file_put_contents($fixture . '/vendor/autoload.php', '<?php require ' . var_export($root . '/vendor/autoload.php', true) . ';');
    $path = $fixture . '/config.local.php';
    $check = setup_call('check');
    setup_check($check['code'] === 0 && !json_decode($check['out'], true)['configured'] && !file_exists($path), 'preflight does not write configuration');
    $bad = setup_call('configure', array_replace($settings, ['db_name' => 'unsafe;database']));
    setup_check($bad['code'] !== 0 && !file_exists($path), 'invalid database name rejected without writes');
    $bad = setup_call('configure', array_replace($settings, ['db_port' => '1']));
    setup_check($bad['code'] !== 0 && !file_exists($path) && !str_contains($bad['error'], 'PDOException'), 'connection failure leaves no partial configuration or database exception');
    $result = setup_call('configure', $settings);
    setup_check($result['code'] === 0 && is_file($path), 'fresh configuration succeeds without global Composer');
    $config = require $path;
    setup_check(strlen($config['secret']) === 64 && $config['db_name'] === $name && $config['base_path'] === '/Setup-Fixture', 'fresh setup generates a persistent secret and saves chosen settings');
    $bytes = file_get_contents($path);
    $result = setup_call('configure', array_replace($settings, ['db_name' => 'changed_database']));
    setup_check($result['code'] === 0 && file_get_contents($path) === $bytes, 'repeat configuration preserves exact private file and signing secret');
    $result = setup_call('configure', [], ['CSTBS_DB_NAME' => 'unexpected_database']);
    setup_check($result['code'] !== 0 && file_get_contents($path) === $bytes, 'environment overrides cannot redirect setup configuration');
    $result = setup_call('database');
    setup_check($result['code'] === 0, 'database schema installs on a fresh laptop');
    $connection->exec('USE `' . $name . '`');
    $status = setup_call('admin-status');
    setup_check(!json_decode($status['out'], true)['exists'], 'no default administrator password or account created');
    $result = setup_call('admin', ['email' => 'operator@campus.example', 'name' => 'Setup Operator']);
    setup_check($result['code'] === 0 && preg_match('/\b[a-f0-9]{48}\b/', $result['out']) === 1, 'administrator receives normal single-use activation key');
    $before = $connection->query('SELECT * FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $keys = $connection->query('SELECT * FROM admin_keys')->fetchAll(PDO::FETCH_ASSOC);
    $result = setup_call('admin', ['email' => 'replacement@campus.example', 'name' => 'Replacement Operator']);
    setup_check($result['code'] !== 0 && $connection->query('SELECT * FROM users')->fetchAll(PDO::FETCH_ASSOC) === $before
        && $connection->query('SELECT * FROM admin_keys')->fetchAll(PDO::FETCH_ASSOC) === $keys, 'repeat setup cannot replace administrators or invalidate keys');
    $result = setup_call('database');
    setup_check($result['code'] === 0 && count(glob($fixture . '/storage/backups/*.sql')) === 1, 'repeat database setup takes a private SQL backup');
    setup_check($connection->query('SELECT * FROM users')->fetchAll(PDO::FETCH_ASSOC) === $before && file_get_contents($path) === $bytes, 'repeat database setup preserves accounts credentials and configuration');
    $short = $config; $short['secret'] = 'do-not-replace';
    file_put_contents($path, '<?php return ' . var_export($short, true) . ';');
    $shortBytes = file_get_contents($path);
    setup_check(setup_call('configure', [])['code'] !== 0 && file_get_contents($path) === $shortBytes, 'existing short secret requires review and is never silently rotated');
    echo PHP_EOL . $checks . ' installer checks passed.' . PHP_EOL;
} finally {
    // Only this randomly named test database and fixture directory belong to this run.
    $connection->exec('DROP DATABASE IF EXISTS `' . $name . '`');
    if (is_dir($fixture) && str_starts_with($fixture, $root . '/storage/setup-cli-')) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
        rmdir($fixture);
    }
}
