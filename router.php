<?php
// Local development router. Apache uses the root .htaccess instead.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (preg_match('#^/public/assets/[^?]+\.(css|js|svg|png|jpg|gif|woff2?)$#i', $path)) {
    $asset = realpath(__DIR__ . $path);
    if ($asset && str_starts_with($asset, __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR)) { return false; }
}
require __DIR__ . '/index.php';
