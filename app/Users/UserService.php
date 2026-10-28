<?php

declare(strict_types=1);

namespace SanadAi\Users;

use InvalidArgumentException;
use SanadAi\Http\NotFoundException;
use SanadAi\Support\Uuid;

/**
 * User management rules.
 */
final class UserService
{
    /** Roles a tenant manager may hand out. */
    public const ASSIGNABLE_ROLES = [
        'manager',
        'customer_service',
        'accountant',
        'clearance_employee',
    ];

    public const MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        int $tenantId,
        string $username,
        string $password,
        string $fullName,
        string $roleSlug,
    ): array {
        $username = strtolower(trim($username));
        $fullName = trim($fullName);

        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
            throw new InvalidArgumentException(
                'Username must be 3-50 characters: letters, digits, dot, dash or underscore.'
            );
        }

        $this->validateFullName($fullName);
        $this->validatePassword($password);
        $roleId = $this->resolveRole($roleSlug);

        if ($this->repository->usernameExists($username)) {
            throw new InvalidArgumentException(
                'This username is already taken.'
            );
        }

        $publicId = Uuid::v4();

        $this->repository->create(
            tenantId: $tenantId,
            publicId: $publicId,
            username: $username,
            passwordHash: password_hash($password, PASSWORD_DEFAULT),
            fullName: $fullName,
            roleSlug: $roleSlug,
            roleId: $roleId,
        );

        return $this->mustFind($tenantId, $publicId);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(
        int $tenantId,
        int $actorUserId,
        string $publicId,
        string $fullName,
        string $roleSlug,
        string $status,
    ): array {
        $existing = $this->mustFind($tenantId, $publicId);

        if ((bool) $existing['is_platform_admin']) {
            throw new InvalidArgumentException(
                'Platform administrators cannot be modified here.'
            );
        }

        $fullName = trim($fullName);
        $this->validateFullName($fullName);

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Invalid user status.');
        }

        $isSelf = (int) $existing['id'] === $actorUserId;

        if ($isSelf && $status !== 'active') {
            throw new InvalidArgumentException(
                'You cannot deactivate your own account.'
            );
        }

        if ($isSelf && $roleSlug !== $existing['role']) {
            throw new InvalidArgumentException(
                'You cannot change your own role.'
            );
        }

        $roleId = $this->resolveRole($roleSlug);

        $this->repository->update(
            tenantId: $tenantId,
            publicId: $publicId,
            fullName: $fullName,
            roleSlug: $roleSlug,
            roleId: $roleId,
            status: $status,
        );

        return $this->mustFind($tenantId, $publicId);
    }

    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
    ): void {
        $hash = $this->repository->passwordHash($userId);

        if ($hash === null || !password_verify($currentPassword, $hash)) {
            throw new InvalidArgumentException(
                'Current password is incorrect.'
            );
        }

        $this->validatePassword($newPassword);

        if (hash_equals($currentPassword, $newPassword)) {
            throw new InvalidArgumentException(
                'The new password must be different from the current one.'
            );
        }

        $this->repository->updatePassword(
            $userId,
            password_hash($newPassword, PASSWORD_DEFAULT)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mustFind(int $tenantId, string $publicId): array
    {
        $user = $this->repository->findByPublicId($tenantId, $publicId);

        if ($user === null) {
            throw new NotFoundException('User not found.');
        }

        return $user;
    }

    private function resolveRole(string $roleSlug): int
    {
        if (!in_array($roleSlug, self::ASSIGNABLE_ROLES, true)) {
            throw new InvalidArgumentException('Invalid role.');
        }

        $roleId = $this->repository->systemRoleId($roleSlug);

        if ($roleId === null) {
            throw new InvalidArgumentException(
                'Role is not configured. Run the RBAC seeder.'
            );
        }

        return $roleId;
    }

    private function validateFullName(string $fullName): void
    {
        if ($fullName === '' || mb_strlen($fullName) > 150) {
            throw new InvalidArgumentException(
                'Full name is required (150 characters maximum).'
            );
        }
    }

    private function validatePassword(string $password): void
    {
        if (
            mb_strlen($password) < self::MIN_PASSWORD_LENGTH
            || !preg_match('/[A-Za-z]/', $password)
            || !preg_match('/\d/', $password)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Password must be at least %d characters and contain letters and digits.',
                self::MIN_PASSWORD_LENGTH
            ));
        }

        if (strlen($password) > 200) {
            throw new InvalidArgumentException('Password is too long.');
        }
    }
}
