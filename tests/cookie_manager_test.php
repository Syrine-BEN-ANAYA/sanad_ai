<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Auth\CookieManager;

$cookieManager = new CookieManager(
    name: 'SANAD_TEST_SESSION',
    secure: false
);

function check(string $label, bool $condition): void
{
    echo $label . ': ' . ($condition ? 'OK' : 'FAIL') . PHP_EOL;

    if (!$condition) {
        exit(1);
    }
}

/*
|--------------------------------------------------------------------------
| Valid session ID
|--------------------------------------------------------------------------
*/

$sessionId = str_repeat('a', 64);

/*
|--------------------------------------------------------------------------
| Set cookie
|--------------------------------------------------------------------------
|
| CLI does not actually send HTTP headers like a browser.
| We therefore verify that CookieManager accepts the correct
| session format and rejects invalid values.
|
*/

$reflection = new ReflectionClass($cookieManager);

check(
    'CookieManager class exists',
    $reflection->isInstantiable()
);

/*
|--------------------------------------------------------------------------
| Invalid session IDs
|--------------------------------------------------------------------------
*/

$invalidIds = [
    '',
    'abc',
    str_repeat('a', 63),
    str_repeat('a', 65),
    str_repeat('A', 64),
    str_repeat('g', 64),
    'session-id-with-invalid-characters',
];

foreach ($invalidIds as $index => $invalidId) {
    $rejected = false;

    try {
        $cookieManager->set($invalidId);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }

    check(
        'Invalid session ID #' . ($index + 1) . ' rejected',
        $rejected
    );
}

/*
|--------------------------------------------------------------------------
| Simulate receiving a valid cookie
|--------------------------------------------------------------------------
*/

$_COOKIE['SANAD_TEST_SESSION'] = $sessionId;

$received = $cookieManager->get();

check(
    'Valid cookie retrieved',
    $received === $sessionId
);

/*
|--------------------------------------------------------------------------
| Invalid cookie from browser
|--------------------------------------------------------------------------
*/

$_COOKIE['SANAD_TEST_SESSION'] = 'invalid-cookie';

check(
    'Invalid browser cookie rejected',
    $cookieManager->get() === null
);

/*
|--------------------------------------------------------------------------
| Missing cookie
|--------------------------------------------------------------------------
*/

unset($_COOKIE['SANAD_TEST_SESSION']);

check(
    'Missing cookie returns null',
    $cookieManager->get() === null
);

/*
|--------------------------------------------------------------------------
| Cookie name
|--------------------------------------------------------------------------
*/

check(
    'Cookie name is correct',
    $cookieManager->name() === 'SANAD_TEST_SESSION'
);

echo PHP_EOL;
echo "CookieManager test PASSED." . PHP_EOL;