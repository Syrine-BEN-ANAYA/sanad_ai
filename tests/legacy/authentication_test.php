<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Auth\AuthenticationService;
use SanadAi\Auth\CsrfManager;
use SanadAi\Auth\SessionManager;
use SanadAi\Config\Database;
use SanadAi\Http\UnauthorizedException;
use SanadAi\Support\LocalStore;
use SanadAi\Tenancy\TenantContext;

$pdo = Database::connection();
$store = new LocalStore();

$tenantContext = new TenantContext();

$sessionManager = new SessionManager(
    $store,
    120
);

$authentication = new AuthenticationService(
    $pdo,
    $sessionManager,
    new CsrfManager($store),
    $tenantContext
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
| Login — tenant user
|--------------------------------------------------------------------------
*/

$result = $authentication->login(
    username: 'manager_a',
    password: 'TestPassword123!',
    ipAddress: '127.0.0.1',
    userAgent: 'Sanad AI Test Browser'
);

check(
    'Manager login succeeds',
    isset($result['session_id'])
);

check(
    'Session ID is valid',
    preg_match(
        '/^[a-f0-9]{64}$/',
        $result['session_id']
    ) === 1
);

check(
    'Manager user ID is correct',
    $result['user']['id'] === 2
);

check(
    'Manager tenant is correct',
    $result['user']['tenant_id'] === 3
);

check(
    'Tenant context initialized',
    $tenantContext->has()
);

check(
    'Tenant context is tenant 3',
    $tenantContext->get() === 3
);

check(
    'Password hash is not returned',
    !array_key_exists(
        'password_hash',
        $result['user']
    )
);

check(
    'Local session exists',
    $sessionManager->exists(
        $result['session_id']
    )
);

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

$sessionId = $result['session_id'];

$authentication->logout($sessionId);

check(
    'Logout destroys session',
    !$sessionManager->exists($sessionId)
);

check(
    'Tenant context cleared',
    !$tenantContext->has()
);

/*
|--------------------------------------------------------------------------
| Wrong password
|--------------------------------------------------------------------------
*/

$wrongPasswordRejected = false;

try {
    $authentication->login(
        username: 'manager_a',
        password: 'WrongPassword!',
        ipAddress: '127.0.0.1',
        userAgent: 'Sanad AI Test Browser'
    );
} catch (UnauthorizedException $e) {
    $wrongPasswordRejected = true;
}

check(
    'Wrong password rejected',
    $wrongPasswordRejected
);

/*
|--------------------------------------------------------------------------
| Unknown user
|--------------------------------------------------------------------------
*/

$unknownUserRejected = false;

try {
    $authentication->login(
        username: 'user_that_does_not_exist',
        password: 'TestPassword123!',
        ipAddress: '127.0.0.1',
        userAgent: 'Sanad AI Test Browser'
    );
} catch (UnauthorizedException $e) {
    $unknownUserRejected = true;
}

check(
    'Unknown user rejected',
    $unknownUserRejected
);

/*
|--------------------------------------------------------------------------
| Inactive user
|--------------------------------------------------------------------------
*/

$inactiveUserCreated = false;

$insert = $pdo->prepare("
    INSERT INTO users (
        public_id,
        tenant_id,
        role_id,
        is_platform_admin,
        username,
        password_hash,
        full_name,
        role,
        status
    )
    SELECT
        UUID(),
        tenant_id,
        role_id,
        0,
        'inactive_auth_test',
        :password_hash,
        'Inactive Auth Test',
        role,
        'inactive'
    FROM users
    WHERE id = 2
    LIMIT 1
");

$insert->execute([
    'password_hash' => password_hash(
        'TestPassword123!',
        PASSWORD_ARGON2ID
    ),
]);

$inactiveUserCreated = $insert->rowCount() === 1;

check(
    'Inactive test user created',
    $inactiveUserCreated
);

$inactiveRejected = false;

try {
    $authentication->login(
        username: 'inactive_auth_test',
        password: 'TestPassword123!',
        ipAddress: '127.0.0.1',
        userAgent: 'Sanad AI Test Browser'
    );
} catch (UnauthorizedException $e) {
    $inactiveRejected = true;
}

check(
    'Inactive user rejected',
    $inactiveRejected
);

/*
|--------------------------------------------------------------------------
| Cleanup
|--------------------------------------------------------------------------
*/

$cleanup = $pdo->prepare("
    DELETE FROM users
    WHERE username = 'inactive_auth_test'
");

$cleanup->execute();

echo PHP_EOL;
echo "Authentication test PASSED." . PHP_EOL;