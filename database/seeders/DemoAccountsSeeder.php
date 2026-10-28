<?php

declare(strict_types=1);

namespace SanadAi\Database\Seeders;

use PDO;
use RuntimeException;
use SanadAi\Support\Uuid;

/**
 * Creates (or refreshes) the demo office and its demo logins.
 *
 * Idempotent: safe to run many times. Existing demo users get their
 * password, role and status reset to the values in config/demo.php.
 * Requires the system roles to exist (php scripts/seed.php).
 */
final class DemoAccountsSeeder
{
    /**
     * @return array{tenant: string, accounts: list<array{username: string, role: string, created: bool}>}
     */
    public static function run(PDO $pdo): array
    {
        /** @var array{tenant: array{name: string, slug: string}, password: string, accounts: list<array{username: string, full_name: string, role: string}>} $config */
        $config = require dirname(__DIR__, 2) . '/config/demo.php';

        $pdo->beginTransaction();

        try {
            $tenantId = self::tenant($pdo, $config['tenant']['name'], $config['tenant']['slug']);
            $hash = password_hash($config['password'], PASSWORD_DEFAULT);
            $result = [];

            foreach ($config['accounts'] as $account) {
                $roleId = self::roleId($pdo, $account['role']);
                $created = self::user($pdo, $tenantId, $roleId, $account, $hash);

                $result[] = [
                    'username' => $account['username'],
                    'role' => $account['role'],
                    'created' => $created,
                ];
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return ['tenant' => $config['tenant']['slug'], 'accounts' => $result];
    }

    private static function tenant(PDO $pdo, string $name, string $slug): int
    {
        $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            $pdo->prepare("UPDATE tenants SET status = 'active' WHERE id = :id")
                ->execute(['id' => $id]);

            return (int) $id;
        }

        $pdo->prepare("INSERT INTO tenants (public_id, name, slug, status) VALUES (:public_id, :name, :slug, 'active')")
            ->execute(['public_id' => Uuid::v4(), 'name' => $name, 'slug' => $slug]);

        return (int) $pdo->lastInsertId();
    }

    private static function roleId(PDO $pdo, string $slug): int
    {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug AND tenant_id IS NULL LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("System role '{$slug}' not found. Run: php scripts/seed.php");
        }

        return (int) $id;
    }

    /**
     * @param array{username: string, full_name: string, role: string} $account
     * @return bool true when the user was created, false when refreshed
     */
    private static function user(PDO $pdo, int $tenantId, int $roleId, array $account, string $hash): bool
    {
        $stmt = $pdo->prepare('SELECT id, tenant_id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $account['username']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            // Usernames are globally unique; never hijack a real office's user.
            if ((int) $existing['tenant_id'] !== $tenantId) {
                throw new RuntimeException(
                    "Username '{$account['username']}' already belongs to another office."
                );
            }

            $pdo->prepare("
                UPDATE users
                SET password_hash = :hash, full_name = :full_name, role = :role,
                    role_id = :role_id, status = 'active'
                WHERE id = :id
            ")->execute([
                'hash' => $hash,
                'full_name' => $account['full_name'],
                'role' => $account['role'],
                'role_id' => $roleId,
                'id' => $existing['id'],
            ]);

            return false;
        }

        $pdo->prepare("
            INSERT INTO users (public_id, tenant_id, role_id, is_platform_admin, username, password_hash, full_name, role, status)
            VALUES (:public_id, :tenant_id, :role_id, 0, :username, :hash, :full_name, :role, 'active')
        ")->execute([
            'public_id' => Uuid::v4(),
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'username' => $account['username'],
            'hash' => $hash,
            'full_name' => $account['full_name'],
            'role' => $account['role'],
        ]);

        return true;
    }
}
