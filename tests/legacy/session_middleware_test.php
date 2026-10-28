<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Auth\CookieManager;
use SanadAi\Auth\CsrfManager;
use SanadAi\Auth\SessionContext;
use SanadAi\Auth\SessionManager;
use SanadAi\Auth\SessionMiddleware;
use SanadAi\Config\Database;
use SanadAi\Support\LocalStore;
use SanadAi\Tenancy\TenantContext;

$pdo = Database::connection();
$store = new LocalStore();

$tenantContext = new TenantContext();
$sessionContext = new SessionContext();

$sessionManager = new SessionManager($store);
$csrfManager = new CsrfManager($store);

$sessionId = $sessionManager->create(
    userId: 2,
    tenantId: 3,
    ipAddress: '127.0.0.1',
    userAgent: 'Sanad AI Middleware Test'
);
$csrfToken = $csrfManager->generate($sessionId);
$sessionManager->setCsrfToken($sessionId, $csrfToken);

$_COOKIE['SANAD_SESSION'] = $sessionId;

$cookies = new CookieManager(
    name: 'SANAD_SESSION',
    secure: false
);

$middleware = new SessionMiddleware(
    sessionManager: $sessionManager,
    cookies: $cookies,
    csrfManager: $csrfManager,
    pdo: $pdo,
    tenantContext: $tenantContext,
    sessionContext: $sessionContext
);

$tests = [];

$middleware->authenticate();

$tests['User authenticated'] =
    $sessionContext->isAuthenticated();

$tests['User ID correct'] =
    $sessionContext->userId() === 2;

$tests['Tenant ID correct'] =
    $sessionContext->tenantId() === 3;

$tests['Tenant context initialized'] =
    $tenantContext->has();

$tests['Tenant context correct'] =
    $tenantContext->get() === 3;

$tests['CSRF token initialized'] =
    $sessionContext->hasCsrfToken();

$csrfToken = $sessionContext->csrfToken();

$tests['CSRF token format correct'] =
    preg_match('/^[a-f0-9]{64}$/', $csrfToken) === 1;

$tests['CSRF token validates'] =
    $csrfManager->validate(
        $sessionId,
        $csrfToken
    );

$tests['Session still exists'] =
    $sessionManager->exists($sessionId);

$middleware->logout();

$tests['CSRF token revoked'] =
    !$csrfManager->validate(
        $sessionId,
        $csrfToken
    );

$tests['Session destroyed after logout'] =
    !$sessionManager->exists($sessionId);

$tests['Session context cleared'] =
    !$sessionContext->isAuthenticated();

$tests['Tenant context cleared'] =
    !$tenantContext->has();

$tests['CSRF context cleared'] =
    !$sessionContext->hasCsrfToken();

foreach ($tests as $name => $passed) {
    echo $name . ': ' .
        ($passed ? 'OK' : 'FAILED') .
        PHP_EOL;
}

if (in_array(false, $tests, true)) {
    throw new RuntimeException(
        'SessionMiddleware test FAILED.'
    );
}

echo PHP_EOL;
echo "SessionMiddleware test PASSED." . PHP_EOL;