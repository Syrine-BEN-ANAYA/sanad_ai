<?php

declare(strict_types=1);

namespace SanadAi\Users;

use PDO;

/**
 * User persistence. Every query is scoped to a tenant.
 */
final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(
        int $tenantId,
        ?string $search,
        int $limit,
        int $offset,
    ): array {
        [$where, $params] = $this->filters($tenantId, $search);

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare("
            SELECT
                public_id,
                username,
                full_name,
                role,
                status,
                last_login_at,
                created_at
            FROM users
            WHERE {$where}
            ORDER BY full_name ASC, id ASC
            LIMIT {$limit} OFFSET {$offset}
        ");

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function count(int $tenantId, ?string $search): int
    {
        [$where, $params] = $this->filters($tenantId, $search);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE {$where}"
        );

        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Minimal list used by "assign to" pickers.
     *
     * @return list<array<string, mixed>>
     */
    public function assignable(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT public_id, full_name, role
            FROM users
            WHERE tenant_id = :tenant_id
              AND status = 'active'
            ORDER BY full_name ASC
        ");

        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPublicId(int $tenantId, string $publicId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                username,
                full_name,
                role,
                status,
                is_platform_admin,
                last_login_at,
                created_at
            FROM users
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
            LIMIT 1
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Usernames are globally unique because login does not ask
     * for a tenant.
     */
    public function usernameExists(string $username): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE username = :username LIMIT 1'
        );

        $stmt->execute(['username' => $username]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Resolve a system role slug to its ID.
     */
    public function systemRoleId(string $slug): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM roles
            WHERE slug = :slug
              AND tenant_id IS NULL
            LIMIT 1
        ");

        $stmt->execute(['slug' => $slug]);

        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function systemRoles(): array
    {
        return $this->pdo
            ->query("
                SELECT slug, name, description
                FROM roles
                WHERE tenant_id IS NULL
                  AND slug <> 'super_admin'
                ORDER BY name
            ")
            ->fetchAll();
    }

    public function create(
        int $tenantId,
        string $publicId,
        string $username,
        string $passwordHash,
        string $fullName,
        string $roleSlug,
        int $roleId,
    ): void {
        $stmt = $this->pdo->prepare("
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
            ) VALUES (
                :public_id,
                :tenant_id,
                :role_id,
                0,
                :username,
                :password_hash,
                :full_name,
                :role,
                'active'
            )
        ");

        $stmt->execute([
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'username' => $username,
            'password_hash' => $passwordHash,
            'full_name' => $fullName,
            'role' => $roleSlug,
        ]);
    }

    public function update(
        int $tenantId,
        string $publicId,
        string $fullName,
        string $roleSlug,
        int $roleId,
        string $status,
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET full_name = :full_name,
                role = :role,
                role_id = :role_id,
                status = :status
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
              AND is_platform_admin = 0
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
            'full_name' => $fullName,
            'role' => $roleSlug,
            'role_id' => $roleId,
            'status' => $status,
        ]);
    }

    public function updatePassword(
        int $userId,
        string $passwordHash
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id'
        );

        $stmt->execute([
            'hash' => $passwordHash,
            'id' => $userId,
        ]);
    }

    public function passwordHash(int $userId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT password_hash FROM users WHERE id = :id LIMIT 1'
        );

        $stmt->execute(['id' => $userId]);

        $hash = $stmt->fetchColumn();

        return is_string($hash) ? $hash : null;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filters(int $tenantId, ?string $search): array
    {
        $where = ['tenant_id = :tenant_id'];
        $params = ['tenant_id' => $tenantId];

        $search = $search !== null ? trim($search) : '';

        if ($search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';

            $where[] = '(full_name LIKE :q1 OR username LIKE :q2)';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        return [implode(' AND ', $where), $params];
    }
}
