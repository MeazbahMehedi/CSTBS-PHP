<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require __DIR__ . '/src/bootstrap.php';
    require __DIR__ . '/src/ticketing.php';
    $requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    $base = (string) config('base_path');
    if ($base !== '' && $requestPath !== $base && !str_starts_with($requestPath, $base . '/')) {
        throw new HttpError(404, 'Page not found.');
    }
    $GLOBALS['request_path'] = rtrim(substr($requestPath, strlen($base)), '/') ?: '/';
    function route(string $method, string $pattern, callable $handler): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== $method || !preg_match('#^' . $pattern . '$#u', $GLOBALS['request_path'], $matches)) { return; }
        array_shift($matches);
        $handler(...$matches);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); }
    route('GET', '/health', function () { scalar('SELECT 1'); json_response(['status' => 'ok', 'version' => '1.0.0']); });
    require __DIR__ . '/src/accounts.php';
    require __DIR__ . '/src/booking_routes.php';
    require __DIR__ . '/src/entry_routes.php';
    require __DIR__ . '/src/admin_routes.php';
    throw new HttpError(404, 'Page not found.');
} catch (Throwable $error) {
    if (isset($GLOBALS['config']) && class_exists('HttpError') && $error instanceof HttpError) {
        $code = $error->status;
        $message = $error->getMessage();
    } elseif ($error instanceof PDOException && $error->getCode() === '23000') {
        $code = 409;
        $message = 'This operation conflicts with an existing account, booking, or historical record. Refresh and try again.';
    } else {
        $code = 500;
        $message = 'The application could not complete this request. Check installation and server logs.';
        error_log((string) $error);
    }
    http_response_code($code);
    if (function_exists('render') && is_file(__DIR__ . '/views/error.php')) {
        try {
            render('error', ['title' => 'Request error', 'code' => $code, 'heading' => $code === 404 ? 'Page not found' : 'Please check your request', 'message' => $message]);
        } catch (Throwable $renderError) {
            error_log((string) $renderError);
            echo '<h1>CSTBS</h1><p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
    } else { echo '<h1>CSTBS setup</h1><p>' . htmlspecialchars($message) . '</p>'; }
}
