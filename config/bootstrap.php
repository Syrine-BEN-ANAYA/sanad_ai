<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Load environment variables
|--------------------------------------------------------------------------
*/

$dotenv = Dotenv::createImmutable(
    dirname(__DIR__)
);

$dotenv->load();

/*
|--------------------------------------------------------------------------
| Application configuration
|--------------------------------------------------------------------------
*/

$appConfig = require __DIR__ . '/app.php';

/*
|--------------------------------------------------------------------------
| Application constants
|--------------------------------------------------------------------------
*/

if (!defined('APP_ENV')) {
    define(
        'APP_ENV',
        $_ENV['APP_ENV'] ?? 'local'
    );
}

if (!defined('APP_DEBUG')) {
    define(
        'APP_DEBUG',
        filter_var(
            $_ENV['APP_DEBUG'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        )
    );
}

if (!defined('APP_NAME')) {
    define(
        'APP_NAME',
        $_ENV['APP_NAME'] ?? 'Sanad AI'
    );
}

if (!defined('APP_URL')) {
    define(
        'APP_URL',
        $_ENV['APP_URL'] ?? 'http://localhost'
    );
}

/*
|--------------------------------------------------------------------------
| Timezone
|--------------------------------------------------------------------------
*/

date_default_timezone_set(
    $appConfig['timezone'] ?? 'Asia/Muscat'
);

/*
|--------------------------------------------------------------------------
| Required environment variables
|--------------------------------------------------------------------------
*/

$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER']);

/*
|--------------------------------------------------------------------------
| Error reporting
|--------------------------------------------------------------------------
|
| Errors are never printed to API clients in production; the
| ExceptionHandler logs them to storage/logs/app.log instead.
*/

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}
