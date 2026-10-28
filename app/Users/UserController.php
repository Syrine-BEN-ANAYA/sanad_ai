<?php

declare(strict_types=1);

namespace SanadAi\Users;

use SanadAi\Auth\SessionContext;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Tenancy\TenantContext;

final class UserController
{
    public function __construct(
        private readonly UserService $service,
        private readonly UserRepository $repository,
        private readonly TenantContext $tenantContext,
        private readonly SessionContext $sessionContext,
        private readonly Response $response,
    ) {
    }

    public function index(Request $request): void
    {
        $tenantId = $this->tenantContext->require();

        $limit = max(1, min((int) $request->query('limit', 25), 100));
        $offset = max(0, (int) $request->query('offset', 0));
        $search = $request->query('q');
        $search = is_string($search) ? $search : null;

        $this->response->json([
            'success' => true,
            'data' => array_map(
                $this->serialize(...),
                $this->repository->list($tenantId, $search, $limit, $offset)
            ),
            'meta' => [
                'total' => $this->repository->count($tenantId, $search),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /**
     * GET /api/users/assignable
     */
    public function assignable(Request $request): void
    {
        $this->response->json([
            'success' => true,
            'data' => $this->repository->assignable(
                $this->tenantContext->require()
            ),
        ]);
    }

    /**
     * GET /api/roles
     */
    public function roles(Request $request): void
    {
        $this->response->json([
            'success' => true,
            'data' => array_values(array_filter(
                $this->repository->systemRoles(),
                static fn (array $role): bool => in_array(
                    $role['slug'],
                    UserService::ASSIGNABLE_ROLES,
                    true
                )
            )),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $request->json();

        $user = $this->service->create(
            tenantId: $this->tenantContext->require(),
            username: (string) ($data['username'] ?? ''),
            password: (string) ($data['password'] ?? ''),
            fullName: (string) ($data['full_name'] ?? ''),
            roleSlug: (string) ($data['role'] ?? ''),
        );

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($user),
        ], 201);
    }

    public function update(Request $request, string $publicId): void
    {
        $data = $request->json();

        $user = $this->service->update(
            tenantId: $this->tenantContext->require(),
            actorUserId: $this->sessionContext->userId(),
            publicId: $publicId,
            fullName: (string) ($data['full_name'] ?? ''),
            roleSlug: (string) ($data['role'] ?? ''),
            status: (string) ($data['status'] ?? 'active'),
        );

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($user),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function serialize(array $user): array
    {
        return [
            'public_id' => $user['public_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'status' => $user['status'],
            'last_login_at' => $user['last_login_at'],
            'created_at' => $user['created_at'],
        ];
    }
}
