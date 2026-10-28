<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use PDO;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Http\UnauthorizedException;
use SanadAi\Users\UserService;

/**
 * Login, logout, current user and password change.
 */
final class AccountController
{
    public function __construct(
        private readonly AuthenticationService $authentication,
        private readonly SessionMiddleware $sessionMiddleware,
        private readonly SessionContext $sessionContext,
        private readonly CookieManager $cookies,
        private readonly Authorization $authorization,
        private readonly LoginThrottle $throttle,
        private readonly UserService $users,
        private readonly PDO $pdo,
        private readonly Response $response,
    ) {
    }

    public function login(Request $request): void
    {
        $data = $request->json();

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (!is_string($username) || !is_string($password)) {
            throw new \InvalidArgumentException('Invalid request.');
        }

        $ip = $request->ip();

        $this->throttle->assertAllowed($ip, $username);

        try {
            $result = $this->authentication->login(
                username: $username,
                password: $password,
                ipAddress: $ip,
                userAgent: $request->userAgent()
            );
        } catch (UnauthorizedException $exception) {
            $this->throttle->recordFailure($ip, $username);

            throw new UnauthorizedException('Invalid credentials.');
        }

        $this->throttle->recordSuccess($ip, $username);

        $this->cookies->set($result['session_id']);

        $this->response->json([
            'success' => true,
            'user' => $this->presentUser($result['user']),
            'permissions' => $this->authorization->permissionsFor(
                $result['user']['id']
            ),
            'tenant' => $this->tenant($result['user']['tenant_id']),
            'csrf_token' => $result['csrf_token'],
        ]);
    }

    public function logout(Request $request): void
    {
        $this->sessionMiddleware->logout();

        $this->response->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }

    public function me(Request $request): void
    {
        $user = $this->sessionContext->user();

        $this->response->json([
            'success' => true,
            'user' => $this->presentUser($user),
            'permissions' => $this->authorization->permissionsFor(
                (int) $user['id']
            ),
            'tenant' => $this->tenant($user['tenant_id']),
            'csrf_token' => $this->sessionContext->hasCsrfToken()
                ? $this->sessionContext->csrfToken()
                : null,
        ]);
    }

    public function changePassword(Request $request): void
    {
        $data = $request->json();

        $this->users->changePassword(
            userId: $this->sessionContext->userId(),
            currentPassword: (string) ($data['current_password'] ?? ''),
            newPassword: (string) ($data['new_password'] ?? ''),
        );

        $this->response->json([
            'success' => true,
            'message' => 'Password updated.',
        ]);
    }

    /**
     * Only expose public fields (never internal numeric IDs).
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function presentUser(array $user): array
    {
        return [
            'public_id' => $user['public_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'is_platform_admin' => (bool) $user['is_platform_admin'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tenant(mixed $tenantId): ?array
    {
        if ($tenantId === null) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT public_id, name, timezone, currency
            FROM tenants
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => (int) $tenantId]);

        $tenant = $stmt->fetch();

        return $tenant === false ? null : $tenant;
    }
}
