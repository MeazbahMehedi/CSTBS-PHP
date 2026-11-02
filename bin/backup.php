<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';

$directory = dirname(__DIR__) . '/storage/backups';
if (!is_dir($directory)) { mkdir($directory, 0700, true); }
$path = $directory . '/cstbs-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
$temporary = $path . '.tmp';
$output = fopen($temporary, 'xb');
if (!$output) { throw new RuntimeException('Could not create a private backup file.'); }
@chmod($temporary, 0600);
try {
    db()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    db()->beginTransaction();
    fwrite($output, "-- CSTBS consistent MariaDB snapshot " . gmdate('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $tables = ['schema_versions', 'users', 'admin_keys', 'events', 'gates', 'seats', 'bookings', 'tickets', 'rate_limits', 'mail_outbox'];
    foreach ($tables as $table) {
        $definition = one('SHOW CREATE TABLE `' . $table . '`');
        fwrite($output, $definition['Create Table'] . ";\n");
        $columns = array_column(array_filter(all('SHOW COLUMNS FROM `' . $table . '`'), fn($column) => !str_contains($column['Extra'], 'GENERATED')), 'Field');
        $columnList = '`' . implode('`,`', $columns) . '`';
        $rows = query('SELECT ' . $columnList . ' FROM `' . $table . '`');
        while ($row = $rows->fetch()) {
            $values = array_map(fn($value) => $value === null ? 'NULL' : db()->quote((string) $value), array_values($row));
            fwrite($output, 'INSERT INTO `' . $table . '` (' . $columnList . ') VALUES (' . implode(',', $values) . ");\n");
        }
    }
    fwrite($output, "SET FOREIGN_KEY_CHECKS=1;\n");
    db()->commit();
    fclose($output);
    if (!rename($temporary, $path)) { throw new RuntimeException('Could not finalize backup.'); }
    echo 'Backup saved to ' . $path . PHP_EOL;
} catch (Throwable $error) {
    if (db()->inTransaction()) { db()->rollBack(); }
    if (is_resource($output)) { fclose($output); }
    if (is_file($temporary)) { unlink($temporary); }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
