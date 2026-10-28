<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use PDO;
use SanadAi\Http\ForbiddenException;
use RuntimeException;
use SanadAi\Tenancy\TenantContext;

final class Authorization
{
    public function __construct(
        private PDO $pdo,
        private TenantContext $tenantContext
    ) {
    }

    /**
     * Vérifie si un utilisateur possède une permission.
     */
    public function can(int $userId, string $permission): bool
    {
        if ($userId <= 0 || trim($permission) === '') {
            return false;
        }

        /*
         * ---------------------------------------------------------
         * 1. Vérifier l'utilisateur
         * ---------------------------------------------------------
         */

        $userStatement = $this->pdo->prepare("
            SELECT
                id,
                tenant_id,
                role_id,
                is_platform_admin
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");

        $userStatement->execute([
            'user_id' => $userId,
        ]);

        $user = $userStatement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        /*
         * ---------------------------------------------------------
         * 2. Vérifier la permission
         * ---------------------------------------------------------
         */

        $permissionStatement = $this->pdo->prepare("
            SELECT 1
            FROM permissions
            WHERE slug = :permission
            LIMIT 1
        ");

        $permissionStatement->execute([
            'permission' => $permission,
        ]);

        if (!$permissionStatement->fetchColumn()) {
            return false;
        }

        /*
         * ---------------------------------------------------------
         * 3. Platform Admin
         * ---------------------------------------------------------
         *
         * Un Platform Admin n'est pas limité à un tenant.
         */

        if ((bool) $user['is_platform_admin']) {
            return $this->roleHasPermission(
                (int) $user['role_id'],
                $permission
            );
        }

        /*
         * ---------------------------------------------------------
         * 4. Tenant User
         * ---------------------------------------------------------
         */

        if (!$this->tenantContext->has()) {
            return false;
        }

        $currentTenantId = $this->tenantContext->get();

        /*
         * Protection contre l'accès à un autre tenant.
         */

        if (
            $user['tenant_id'] === null ||
            (int) $user['tenant_id'] !== $currentTenantId
        ) {
            return false;
        }

        return $this->roleHasPermission(
            (int) $user['role_id'],
            $permission
        );
    }

    /**
     * Vérifie si un rôle possède une permission.
     */
    private function roleHasPermission(
        int $roleId,
        string $permission
    ): bool {
        if ($roleId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare("
            SELECT 1
            FROM role_permissions rp
            INNER JOIN permissions p
                ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
              AND p.slug = :permission
            LIMIT 1
        ");

        $statement->execute([
            'role_id' => $roleId,
            'permission' => $permission,
        ]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * Vérifie une permission et lève une exception si refusée.
     */
    public function require(
        int $userId,
        string $permission
    ): void {
        if (!$this->can($userId, $permission)) {
            throw new ForbiddenException(
                "Permission denied: {$permission}"
            );
        }
    }

    /**
     * All permission slugs granted to the user (used by the UI to
     * show/hide features; the server still enforces every request).
     *
     * @return list<string>
     */
    public function permissionsFor(int $userId): array
    {
        $statement = $this->pdo->prepare("
            SELECT p.slug
            FROM users u
            INNER JOIN role_permissions rp ON rp.role_id = u.role_id
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE u.id = :user_id
              AND u.status = 'active'
            ORDER BY p.slug
        ");

        $statement->execute(['user_id' => $userId]);

        return array_map(
            'strval',
            $statement->fetchAll(\PDO::FETCH_COLUMN)
        );
    }
}