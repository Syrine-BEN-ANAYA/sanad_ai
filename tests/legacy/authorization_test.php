<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SanadAi\Auth\Authorization;
use SanadAi\Auth\PermissionMiddleware;
use SanadAi\Auth\SessionContext;
use SanadAi\Tenancy\TenantContext;

$dotenv = Dotenv\Dotenv::createImmutable(
    dirname(__DIR__)
);

$dotenv->safeLoad();

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        $_ENV['DB_PORT'] ?? '3306',
        $_ENV['DB_NAME'] ?? 'sanad_ai'
    ),
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$tenantContext = new TenantContext();

$authorization = new Authorization(
    $pdo,
    $tenantContext
);

$sessionContext = new SessionContext();

echo "=== Authorization Test ===" . PHP_EOL;

function loadUser(
    PDO $pdo,
    SessionContext $sessionContext,
    int $userId
): void {
    $statement = $pdo->prepare("
        SELECT
            id,
            tenant_id,
            role_id,
            is_platform_admin
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");

    $statement->execute([
        'user_id' => $userId,
    ]);

    $user = $statement->fetch();

    if (!$user) {
        throw new RuntimeException(
            "User {$userId} not found."
        );
    }

    $user['id'] = (int) $user['id'];

    $user['tenant_id'] = $user['tenant_id'] !== null
        ? (int) $user['tenant_id']
        : null;

    $user['role_id'] = $user['role_id'] !== null
        ? (int) $user['role_id']
        : null;

    $user['is_platform_admin'] =
        (bool) $user['is_platform_admin'];

    $sessionContext->setUser($user);
}

echo PHP_EOL;
echo "1. Manager A clients.view..." . PHP_EOL;

loadUser(
    $pdo,
    $sessionContext,
    2
);

$tenantContext->set(3);

$middleware = new PermissionMiddleware(
    $authorization,
    $sessionContext,
    'clients.view'
);

try {
    $middleware();

    echo "PASS: Manager A has clients.view." . PHP_EOL;
} catch (Throwable $e) {
    echo "FAIL: Manager A should have clients.view." . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL;
echo "2. Manager A clients.create..." . PHP_EOL;

$middleware = new PermissionMiddleware(
    $authorization,
    $sessionContext,
    'clients.create'
);

try {
    $middleware();

    echo "PASS: Manager A has clients.create." . PHP_EOL;
} catch (Throwable $e) {
    echo "FAIL: Manager A should have clients.create." . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL;
echo "3. Accountant A clients.view..." . PHP_EOL;

loadUser(
    $pdo,
    $sessionContext,
    4
);

$tenantContext->set(3);

$middleware = new PermissionMiddleware(
    $authorization,
    $sessionContext,
    'clients.view'
);

try {
    $middleware();

    echo "PASS: Accountant A has clients.view." . PHP_EOL;
} catch (Throwable $e) {
    echo "FAIL: Accountant A should have clients.view." . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL;
echo "4. Manager B with Tenant A context..." . PHP_EOL;

loadUser(
    $pdo,
    $sessionContext,
    5
);

$tenantContext->set(3);

$middleware = new PermissionMiddleware(
    $authorization,
    $sessionContext,
    'clients.view'
);

try {
    $middleware();

    echo "FAIL: Manager B must not access Tenant A context." . PHP_EOL;
    exit(1);
} catch (Throwable $e) {
    echo "PASS: Cross-tenant authorization rejected." . PHP_EOL;
}

echo PHP_EOL;
echo "5. Manager B with Tenant B context..." . PHP_EOL;

$tenantContext->set(4);

try {
    $middleware();

    echo "PASS: Manager B can access its own tenant." . PHP_EOL;
} catch (Throwable $e) {
    echo "FAIL: Manager B should access Tenant B." . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL;
echo "6. Customer Service A clients.delete..." . PHP_EOL;

loadUser(
    $pdo,
    $sessionContext,
    3
);

$tenantContext->set(3);

$middleware = new PermissionMiddleware(
    $authorization,
    $sessionContext,
    'clients.delete'
);

try {
    $middleware();

    echo "FAIL: Customer Service A must not have clients.delete." . PHP_EOL;
    exit(1);
} catch (Throwable $e) {
    if (
        $e->getMessage()
        === 'Permission denied: clients.delete'
    ) {
        echo "PASS: Customer Service A cannot delete clients." . PHP_EOL;
    } else {
        echo "FAIL: Unexpected authorization error." . PHP_EOL;
        echo $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

echo PHP_EOL;
echo "7. Manager A clients.delete..." . PHP_EOL;

loadUser(
    $pdo,
    $sessionContext,
    2
);

$tenantContext->set(3);

$middleware = new PermissionMiddleware(
    $authorization,
    $sessionContext,
    'clients.delete'
);

try {
    $middleware();

    echo "PASS: Manager A can delete clients." . PHP_EOL;
} catch (Throwable $e) {
    echo "FAIL: Manager A should have clients.delete." . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL;
echo "=== ALL AUTHORIZATION TESTS PASSED ===" . PHP_EOL;