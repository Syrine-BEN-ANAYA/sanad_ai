<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Auth\SessionManager;
use SanadAi\Support\LocalStore;

$sessionManager = new SessionManager(new LocalStore(), 120);

function check(string $label, bool $condition): void
{
    echo $label . ': ' . ($condition ? 'OK' : 'FAIL') . PHP_EOL;

    if (!$condition) {
        exit(1);
    }
}

/*
|--------------------------------------------------------------------------
| Create session
|--------------------------------------------------------------------------
*/

$sessionId = $sessionManager->create(
    userId: 2,
    tenantId: 3,
    ipAddress: '127.0.0.1',
    userAgent: 'Sanad AI Test Browser'
);

check(
    'Session ID generated',
    preg_match('/^[a-f0-9]{64}$/', $sessionId) === 1
);

check(
    'Session exists',
    $sessionManager->exists($sessionId)
);

/*
|--------------------------------------------------------------------------
| Read session
|--------------------------------------------------------------------------
*/

$session = $sessionManager->get($sessionId);

check(
    'Session retrieved',
    is_array($session)
);

check(
    'User ID stored correctly',
    (int) $session['user_id'] === 2
);

check(
    'Tenant ID stored correctly',
    (int) $session['tenant_id'] === 3
);

check(
    'IP address stored correctly',
    $session['ip_address'] === '127.0.0.1'
);

check(
    'User agent stored correctly',
    $session['user_agent'] === 'Sanad AI Test Browser'
);

/*
|--------------------------------------------------------------------------
| TTL
|--------------------------------------------------------------------------
*/

$ttl = $sessionManager->ttl($sessionId);

check(
    'Session TTL is active',
    $ttl > 0 && $ttl <= 120
);

/*
|--------------------------------------------------------------------------
| Touch session
|--------------------------------------------------------------------------
*/

check(
    'Session touch works',
    $sessionManager->touch($sessionId)
);

check(
    'Session still exists after touch',
    $sessionManager->exists($sessionId)
);

/*
|--------------------------------------------------------------------------
| Invalid session IDs
|--------------------------------------------------------------------------
*/

check(
    'Invalid session ID rejected',
    !$sessionManager->exists('invalid-session-id')
);

check(
    'Invalid session ID cannot be retrieved',
    $sessionManager->get('invalid-session-id') === null
);

/*
|--------------------------------------------------------------------------
| Destroy session
|--------------------------------------------------------------------------
*/

check(
    'Session destroyed',
    $sessionManager->destroy($sessionId)
);

check(
    'Destroyed session no longer exists',
    !$sessionManager->exists($sessionId)
);

check(
    'Destroyed session cannot be retrieved',
    $sessionManager->get($sessionId) === null
);

echo PHP_EOL;
echo "SessionManager test PASSED." . PHP_EOL;