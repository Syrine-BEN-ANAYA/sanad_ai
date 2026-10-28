<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in server (development only):
 *   php -S 127.0.0.1:8080 -t public router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/') {
    header('Location: /index.html');
    exit;
}

// Static files are served as-is; everything else goes to the API front controller.
if (is_file(__DIR__ . '/public' . $path)) {
    return false;
}

require __DIR__ . '/public/index.php';
