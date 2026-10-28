<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use PDO;
use SanadAi\Http\UnauthorizedException;
use SanadAi\Tenancy\TenantContext;

final class SessionMiddleware
{
    public function __construct(
        private SessionManager $sessionManager,
        private CookieManager $cookies,
        private CsrfManager $csrfManager,
        private PDO $pdo,
        private TenantContext $tenantContext,
        private SessionContext $sessionContext
    ) {
    }

    /**
     * Authenticate the current HTTP request.
     *
     * This middleware:
     * - validates the session cookie
     * - loads the authenticated user
     * - validates the user's tenant
     * - initializes the tenant context
     * - initializes the authenticated user context
     * - refreshes the session TTL
     *
     * CSRF generation is intentionally NOT done here.
     *
     * The CSRF token must be generated once during login
     * and then supplied by the client on protected state-changing
     * requests.
     */
    public function authenticate(): void
    {
        $sessionId = $this->cookies->get();

        if ($sessionId === null) {
            throw new UnauthorizedException(
                'Authentication required.'
            );
        }

        /*
         * Load the server-side session.
         */
        $session = $this->sessionManager->get(
            $sessionId
        );

        if ($session === null) {
            $this->cookies->clear();

            throw new UnauthorizedException(
                'Session expired.'
            );
        }

        /*
         * Extract user ID from session.
         */
        $userId = (int) (
            $session['user_id'] ?? 0
        );

        if ($userId <= 0) {
            $this->sessionManager->destroy(
                $sessionId
            );

            $this->csrfManager->revoke(
                $sessionId
            );

            $this->cookies->clear();

            throw new UnauthorizedException(
                'Invalid session.'
            );
        }

        /*
         * Load the current user from MySQL.
         *
         * We intentionally do not trust user information
         * stored inside the server-side session.
         */
        $statement = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                role_id,
                is_platform_admin,
                username,
                full_name,
                role,
                status
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $userId,
        ]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        /*
         * User must still exist and be active.
         */
        if (
            !$user
            || $user['status'] !== 'active'
        ) {
            $this->csrfManager->revoke(
                $sessionId
            );

            $this->sessionManager->destroy(
                $sessionId
            );

            $this->cookies->clear();

            throw new UnauthorizedException(
                'User account is unavailable.'
            );
        }

        /*
         * Resolve tenant.
         */
        $tenantId = $user['tenant_id'] !== null
            ? (int) $user['tenant_id']
            : null;

        /*
         * Normal users must belong to a tenant.
         */
        if (
            !$user['is_platform_admin']
            && $tenantId === null
        ) {
            $this->csrfManager->revoke(
                $sessionId
            );

            $this->sessionManager->destroy(
                $sessionId
            );

            $this->cookies->clear();

            throw new UnauthorizedException(
                'User tenant configuration is invalid.'
            );
        }

        /*
         * Validate tenant status.
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
                $this->csrfManager->revoke(
                    $sessionId
                );

                $this->sessionManager->destroy(
                    $sessionId
                );

                $this->cookies->clear();

                throw new UnauthorizedException(
                    'Tenant is unavailable.'
                );
            }

            /*
             * Initialize tenant isolation context.
             */
            $this->tenantContext->set(
                $tenantId
            );
        } else {
            /*
             * Platform admin without a tenant.
             */
            $this->tenantContext->clear();
        }

        /*
         * Normalize database values.
         */
        $user['id'] = (int) $user['id'];

        $user['tenant_id'] = $tenantId;

        $user['role_id'] = $user['role_id'] !== null
            ? (int) $user['role_id']
            : null;

        $user['is_platform_admin'] =
            (bool) $user['is_platform_admin'];

        /*
         * Store authenticated user in request context.
         */
       $this->sessionContext->setUser(
    $user
);

/*
 * The CSRF token was generated once during login.
 *
 * We retrieve the existing token from the server-side
 * server-side session. We NEVER generate a new token here.
 */
$csrfToken = $this->sessionManager->getCsrfToken(
    $sessionId
);

if ($csrfToken === null) {
    $this->sessionManager->destroy(
        $sessionId
    );

    $this->csrfManager->revoke(
        $sessionId
    );

    $this->tenantContext->clear();

    $this->sessionContext->clear();

    $this->cookies->clear();

    throw new UnauthorizedException(
        'Session CSRF token is unavailable.'
    );
}

$this->sessionContext->setCsrfToken(
    $csrfToken
);

$this->sessionManager->touch(
    $sessionId
);
    }

    /**
     * Logout the current session.
     *
     * This revokes:
     * - CSRF token
     * - server-side session
     * - tenant context
     * - authenticated user context
     * - authentication cookie
     */
    public function logout(): void
    {
        $sessionId = $this->cookies->get();

        if ($sessionId !== null) {
            /*
             * Revoke CSRF token first.
             */
            $this->csrfManager->revoke(
                $sessionId
            );

            /*
             * Destroy the server-side authentication session.
             */
            $this->sessionManager->destroy(
                $sessionId
            );
        }

        /*
         * Clear tenant isolation context.
         */
        $this->tenantContext->clear();

        /*
         * Clear authenticated user and CSRF
         * information from the current request.
         */
        $this->sessionContext->clear();

        /*
         * Remove authentication cookie.
         */
        $this->cookies->clear();
    }
}