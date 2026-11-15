<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/ticketing.php';
function route(string $method, string $pattern, callable $handler): void {}
require dirname(__DIR__) . '/src/entry_routes.php';

$ticketId = (int) $argv[1];
$gateId = (int) $argv[2];
$actor = one('SELECT * FROM users WHERE id=?', [(int) $argv[3]]);
$barrier = $argv[4];
$ready = $barrier . '.' . getmypid() . '.ready';
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 10;
while (!is_file($barrier)) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Concurrency test timed out.'); }
    usleep(1000);
}
try {
    check_in($ticketId, $gateId, $actor);
    echo 'admitted';
} catch (HttpError $error) {
    if ($error->status !== 409) { throw $error; }
    echo 'rejected';
} finally {
    unlink($ready);
}
