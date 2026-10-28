<?php

declare(strict_types=1);

/*
 * Create a real office (tenant) with its first manager.
 *
 *   php scripts/create_tenant.php "Office name" office-slug manager_username "Manager Full Name"
 *
 * The password is prompted (not echoed) or read from SANAD_ADMIN_PASSWORD.
 */

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Config\Database;
use SanadAi\Support\Uuid;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

[$script, $name, $slug, $username, $fullName] = array_pad($argv, 5, null);

if (!$name || !$slug || !$username || !$fullName) {
    fwrite(STDERR, "Usage: php scripts/create_tenant.php \"Office name\" office-slug username \"Full Name\"\n");
    exit(1);
}

if (!preg_match('/^[a-z0-9-]{2,100}$/', $slug) || !preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username)) {
    fwrite(STDERR, "Invalid slug or username.\n");
    exit(1);
}

$password = getenv('SANAD_ADMIN_PASSWORD') ?: '';

if ($password === '') {
    fwrite(STDOUT, 'Password (min 12 chars): ');
    system('stty -echo 2>/dev/null');
    $password = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}

if (mb_strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$pdo = Database::connection();

$roleId = $pdo->query("SELECT id FROM roles WHERE slug = 'manager' AND tenant_id IS NULL")->fetchColumn();

if (!$roleId) {
    fwrite(STDERR, "Roles not seeded. Run: php scripts/seed.php\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $pdo->prepare("INSERT INTO tenants (public_id, name, slug) VALUES (?, ?, ?)")
        ->execute([Uuid::v4(), $name, $slug]);

    $tenantId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO users (public_id, tenant_id, role_id, is_platform_admin, username, password_hash, full_name, role, status)
        VALUES (?, ?, ?, 0, ?, ?, ?, 'manager', 'active')
    ")->execute([
        Uuid::v4(),
        $tenantId,
        (int) $roleId,
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $fullName,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Office '{$name}' created with manager '{$username}'.\n";
