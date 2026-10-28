<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use PDO;
use RuntimeException;
use SanadAi\Http\UnauthorizedException;
use SanadAi\Tenancy\TenantContext;

final class AuthenticationService
{
    public function __construct(
        private PDO $pdo,
        private SessionManager $sessionManager,
        private CsrfManager $csrfManager,
        private TenantContext $tenantContext
    ) {
    }

    /**
     * Authenticate a user and create a secure session.
     *
     * @return array{
     *     session_id: string,
     *     csrf_token: string,
     *     user: array{
     *         id: int,
     *         public_id: string,
     *         tenant_id: ?int,
     *         role_id: ?int,
     *         is_platform_admin: bool,
     *         username: string,
     *         full_name: string,
     *         role: string,
     *         status: string
     *     }
     * }
     */
    public function login(
        string $username,
        string $password,
        string $ipAddress,
        string $userAgent
    ): array {
        $username = trim($username);

        if (
            $username === ''
            || $password === ''
        ) {
            throw new UnauthorizedException(
                'Invalid credentials.'
            );
        }

        if (strlen($username) > 100) {
            throw new UnauthorizedException(
                'Invalid credentials.'
            );
        }

        /*
         * Load user.
         */
        $statement = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                role_id,
                is_platform_admin,
                username,
                password_hash,
                full_name,
                role,
                status
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $statement->execute([
            'username' => $username,
        ]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        /*
         * Do not reveal whether the username exists.
         */
        if (!$user) {
            // Constant-ish time: burn a hash verification so that
            // "unknown user" is not measurably faster than "bad password".
            password_verify(
                $password,
                '$2y$12$ceML7nT2SC49jMkvvfByX.Ubdzy.Y5Qwziy2mnbYwBX2QSew2K9KC'
            );

            throw new UnauthorizedException(
                'Invalid credentials.'
            );
        }

        /*
         * Only active users may authenticate.
         */
        if ($user['status'] !== 'active') {
            throw new UnauthorizedException(
                'Invalid credentials.'
            );
        }

        /*
         * Verify password.
         */
        if (
            !password_verify(
                $password,
                $user['password_hash']
            )
        ) {
            throw new UnauthorizedException(
                'Invalid credentials.'
            );
        }

        $userId = (int) $user['id'];

        $tenantId = $user['tenant_id'] !== null
            ? (int) $user['tenant_id']
            : null;

        $isPlatformAdmin =
            (bool) $user['is_platform_admin'];

        /*
         * Normal tenant users MUST belong to a tenant.
         */
        if (
            !$isPlatformAdmin
            && $tenantId === null
        ) {
            throw new UnauthorizedException(
                'Invalid credentials.'
            );
        }

        /*
         * Verify that the tenant exists and is active.
         */
        if ($tenantId !== null) {
            $tenantStatement = $this->pdo->prepare("
                SELECT id
                FROM tenants
                WHERE id = :tenant_id
                  AND status = 'active'
                LIMIT 1
            ");

            $tenantStatement->execute([
                'tenant_id' => $tenantId,
            ]);

            if (!$tenantStatement->fetchColumn()) {
                throw new UnauthorizedException(
                    'Invalid credentials.'
                );
            }

            /*
             * Initialize tenant context.
             */
            $this->tenantContext->set(
                $tenantId
            );
        } else {
            /*
             * Platform admin without tenant.
             */
            $this->tenantContext->clear();
        }

        /*
         * Create a server-side authentication session.
         */
        $sessionId = $this->sessionManager->create(
            userId: $userId,
            tenantId: $tenantId,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        /*
         * Generate the CSRF token ONCE.
         *
         * Only its SHA-256 hash is stored in the local server-side store.
         */
      $csrfToken = $this->csrfManager->generate(
    $sessionId
);

if (
    !$this->sessionManager->setCsrfToken(
        $sessionId,
        $csrfToken
    )
) {
    $this->csrfManager->revoke(
        $sessionId
    );

    $this->sessionManager->destroy(
        $sessionId
    );

    $this->tenantContext->clear();

    throw new RuntimeException(
        'Unable to initialize session security.'
    );
}

        /*
         * Update last login timestamp.
         */
        $update = $this->pdo->prepare("
            UPDATE users
            SET last_login_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
        ");

        $update->execute([
            'user_id' => $userId,
        ]);

        /*
         * Never expose password_hash.
         */
        unset($user['password_hash']);

        /*
         * Normalize values.
         */
        $user['id'] = (int) $user['id'];

        $user['tenant_id'] = $tenantId;

        $user['role_id'] = $user['role_id'] !== null
            ? (int) $user['role_id']
            : null;

        $user['is_platform_admin'] =
            $isPlatformAdmin;

        return [
            'session_id' => $sessionId,
            'csrf_token' => $csrfToken,
            'user' => $user,
        ];
    }

    /**
     * Logout and destroy the session.
     *
     * CSRF revocation is handled by SessionMiddleware
     * because it owns the complete HTTP session lifecycle.
     */
    public function logout(string $sessionId): void
    {
        $this->sessionManager->destroy(
            $sessionId
        );

        $this->tenantContext->clear();
    }
}