<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Auth\CsrfManager;
use SanadAi\Auth\SessionManager;
use SanadAi\Support\LocalStore;

$store = new LocalStore();

$sessionManager = new SessionManager($store);
$csrfManager = new CsrfManager($store);

$sessionId = $sessionManager->create(
    userId: 2,
    tenantId: 3,
    ipAddress: '127.0.0.1',
    userAgent: 'Sanad AI CSRF Test'
);

$token = $csrfManager->generate($sessionId);

$tests = [];

$tests['CSRF token generated'] =
    preg_match('/^[a-f0-9]{64}$/', $token) === 1;

$tests['Valid token accepted'] =
    $csrfManager->validate(
        $sessionId,
        $token
    );

$tests['Invalid token rejected'] =
    !$csrfManager->validate(
        $sessionId,
        str_repeat('a', 64)
    );

$tests['Invalid session rejected'] =
    !$csrfManager->validate(
        str_repeat('b', 64),
        $token
    );

$tests['Token revoked'] =
    $csrfManager->revoke($sessionId);

$tests['Revoked token rejected'] =
    !$csrfManager->validate(
        $sessionId,
        $token
    );

$sessionManager->destroy($sessionId);

foreach ($tests as $name => $passed) {
    echo $name . ': ' .
        ($passed ? 'OK' : 'FAILED') .
        PHP_EOL;
}

if (in_array(false, $tests, true)) {
    throw new RuntimeException(
        'CsrfManager test FAILED.'
    );
}

echo PHP_EOL;
echo "CsrfManager test PASSED." . PHP_EOL;