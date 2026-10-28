<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Http\Router;

$router = new Router();

$events = [];

$router->get(
    '/public',
    static function () use (&$events): void {
        $events[] = 'handler';
    }
);

$router->get(
    '/protected',
    static function () use (&$events): void {
        $events[] = 'handler';
    },
    [
        static function () use (&$events): void {
            $events[] = 'auth';
        },
    ]
);

$router->post(
    '/csrf',
    static function () use (&$events): void {
        $events[] = 'handler';
    },
    [
        static function () use (&$events): void {
            $events[] = 'auth';
        },
        static function () use (&$events): void {
            $events[] = 'csrf';
        },
    ]
);

/*
 * Public route
 */
$events = [];

$router->dispatch('GET', '/public');

$tests = [];

$tests['Public route works'] =
    $events === ['handler'];

/*
 * Authentication middleware
 */
$events = [];

$router->dispatch('GET', '/protected');

$tests['Middleware runs before handler'] =
    $events === ['auth', 'handler'];

/*
 * Authentication + CSRF
 */
$events = [];

$router->dispatch('POST', '/csrf');

$tests['Multiple middleware order is correct'] =
    $events === ['auth', 'csrf', 'handler'];

/*
 * Missing route
 */
try {
    $router->dispatch('GET', '/does-not-exist');

    $tests['Missing route rejected'] = false;
} catch (RuntimeException $e) {
    $tests['Missing route rejected'] =
        $e->getMessage() === 'Route not found.';
}

foreach ($tests as $name => $passed) {
    echo $name . ': ' .
        ($passed ? 'OK' : 'FAILED') .
        PHP_EOL;
}

if (in_array(false, $tests, true)) {
    throw new RuntimeException(
        'Router middleware test FAILED.'
    );
}

echo PHP_EOL;
echo "Router middleware test PASSED." . PHP_EOL;
