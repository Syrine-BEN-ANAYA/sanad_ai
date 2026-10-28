<?php

declare(strict_types=1);

namespace SanadAi\Transactions;

use InvalidArgumentException;
use SanadAi\Auth\SessionContext;
use SanadAi\Clients\ClientRepository;
use SanadAi\Http\Input;
use SanadAi\Http\ForbiddenException;
use SanadAi\Http\NotFoundException;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Services\ServiceRepository;
use SanadAi\Tenancy\TenantContext;
use SanadAi\Users\UserRepository;

/**
 * Transactions API.
 *
 * Clients, services and assignees are referenced by PUBLIC id only.
 * Internal numeric ids never leave the server.
 */
final class TransactionController
{
    public function __construct(
        private readonly TransactionManager $manager,
        private readonly TransactionRepository $repository,
        private readonly ClientRepository $clients,
        private readonly ServiceRepository $services,
        private readonly UserRepository $users,
        private readonly TenantContext $tenantContext,
        private readonly SessionContext $sessionContext,
        private readonly Response $response,
    ) {
    }

    /** GET /api/transactions?q=&status=&priority=&client=&overdue=1&limit=&offset= */
    public function index(Request $request): void
    {
        $tenantId = $this->tenantContext->require();
        [$limit, $offset] = Input::pagination($request);

        $filters = [
            'q' => Input::queryString($request, 'q'),
            'status' => Input::queryString($request, 'status'),
            'priority' => Input::queryString($request, 'priority'),
            'client' => Input::queryString($request, 'client'),
            'overdue' => $request->query('overdue') === '1',
        ];

        $rows = $this->repository->searchDetailed($tenantId, $filters, $limit, $offset);

        $this->response->json([
            'success' => true,
            'data' => array_map(fn (array $r): array => $this->serialize($r), $rows),
            'meta' => [
                'total' => $this->repository->countDetailed($tenantId, $filters),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /** GET /api/transactions/stats */
    public function stats(Request $request): void
    {
        $this->response->json([
            'success' => true,
            'data' => $this->repository->stats($this->tenantContext->require()),
        ]);
    }

    /** GET /api/transactions/{publicId} */
    public function show(Request $request, string $publicId): void
    {
        $tenantId = $this->tenantContext->require();

        $row = $this->repository->findDetailed($tenantId, $publicId);

        if ($row === null) {
            throw new NotFoundException('Transaction not found.');
        }

        $history = $this->repository->findStatusHistory($tenantId, $publicId);

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($row),
            'allowed_transitions' => $this->manager->allowedTransitions((string) $row['status']),
            'history' => array_map(
                static fn (array $h): array => [
                    'from_status' => $h['from_status'] ?? null,
                    'to_status' => $h['to_status'] ?? null,
                    'changed_by' => $h['changed_by_name'] ?? null,
                    'reason' => $h['reason'] ?? null,
                    'created_at' => $h['created_at'] ?? null,
                ],
                $history
            ),
        ]);
    }

    /** POST /api/transactions */
    public function store(Request $request): void
    {
        $tenantId = $this->tenantContext->require();
        $data = $request->json();

        if (array_key_exists('assigned_user_public_id', $data) && !$this->canAssignTransactions()) {
            throw new ForbiddenException('Only managers can assign transactions.');
        }

        $transaction = $this->manager->create(
            tenantId: $tenantId,
            clientId: $this->clientId($tenantId, Input::string($data, 'client_public_id')),
            serviceId: $this->serviceId($tenantId, Input::string($data, 'service_public_id')),
            title: Input::string($data, 'title'),
            description: Input::nullableString($data, 'description'),
            assignedUserId: $this->assigneeId($tenantId, Input::nullableString($data, 'assigned_user_public_id')),
            source: Input::string($data, 'source', 'dashboard'),
            priority: Input::string($data, 'priority', 'normal'),
            dueAt: Input::nullableString($data, 'due_at'),
            createdByUserId: $this->sessionContext->userId(),
        );

        $this->respondWith($tenantId, $transaction->publicId, 201);
    }

    /** PUT /api/transactions/{publicId} */
    public function update(Request $request, string $publicId): void
    {
        $tenantId = $this->tenantContext->require();
        $data = $request->json();

        if (array_key_exists('assigned_user_public_id', $data) && !$this->canAssignTransactions()) {
            throw new ForbiddenException('Only managers can assign transactions.');
        }

        $existing = $this->repository->findDetailed($tenantId, $publicId);

        if ($existing === null) {
            throw new NotFoundException('Transaction not found.');
        }

        $this->manager->update(
            tenantId: $tenantId,
            publicId: $publicId,
            clientId: $this->clientId($tenantId, Input::string($data, 'client_public_id', (string) $existing['client_public_id'])),
            serviceId: $this->serviceId($tenantId, Input::string($data, 'service_public_id', (string) $existing['service_public_id'])),
            title: Input::string($data, 'title', (string) $existing['title']),
            description: array_key_exists('description', $data)
                ? Input::nullableString($data, 'description')
                : ($existing['description'] === null ? null : (string) $existing['description']),
            assignedUserId: array_key_exists('assigned_user_public_id', $data)
                ? $this->assigneeId($tenantId, Input::nullableString($data, 'assigned_user_public_id'))
                : $this->assigneeId($tenantId, $existing['assignee_public_id'] === null ? null : (string) $existing['assignee_public_id']),
            source: Input::string($data, 'source', (string) $existing['source']),
            priority: Input::string($data, 'priority', (string) $existing['priority']),
            dueAt: array_key_exists('due_at', $data)
                ? Input::nullableString($data, 'due_at')
                : ($existing['due_at'] === null ? null : (string) $existing['due_at']),
        );

        $this->respondWith($tenantId, $publicId);
    }

    /** POST /api/transactions/{publicId}/status */
    public function changeStatus(Request $request, string $publicId): void
    {
        $tenantId = $this->tenantContext->require();
        $data = $request->json();

        $this->manager->changeStatus(
            tenantId: $tenantId,
            publicId: $publicId,
            newStatus: Input::string($data, 'status'),
            changedByUserId: $this->sessionContext->userId(),
            reason: Input::nullableString($data, 'reason'),
        );

        $this->respondWith($tenantId, $publicId);
    }

    private function respondWith(int $tenantId, string $publicId, int $status = 200): void
    {
        $row = $this->repository->findDetailed($tenantId, $publicId);

        if ($row === null) {
            throw new NotFoundException('Transaction not found.');
        }

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($row),
        ], $status);
    }

    private function canAssignTransactions(): bool
    {
        $role = (string) ($this->sessionContext->user()['role'] ?? '');

        return $role === 'manager' || $role === 'super_admin';
    }

    private function clientId(int $tenantId, string $publicId): int
    {
        $client = $publicId === '' ? null : $this->clients->findByPublicId($tenantId, $publicId);

        if ($client === null) {
            throw new InvalidArgumentException('Client not found.');
        }

        if ($client->status !== 'active') {
            throw new InvalidArgumentException('Client is inactive.');
        }

        return $client->id;
    }

    private function serviceId(int $tenantId, string $publicId): int
    {
        $service = $publicId === '' ? null : $this->services->findByPublicId($tenantId, $publicId);

        if ($service === null) {
            throw new InvalidArgumentException('Service not found.');
        }

        if ($service->status !== 'active') {
            throw new InvalidArgumentException('Service is inactive.');
        }

        return $service->id;
    }

    private function assigneeId(int $tenantId, ?string $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        $user = $this->users->findByPublicId($tenantId, $publicId);

        if ($user === null) {
            throw new InvalidArgumentException('Assigned user not found.');
        }

        return (int) $user['id'];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function serialize(array $row): array
    {
        return [
            'public_id' => $row['public_id'],
            'reference' => $row['reference'],
            'title' => $row['title'],
            'description' => $row['description'],
            'status' => $row['status'],
            'priority' => $row['priority'],
            'source' => $row['source'],
            'client' => [
                'public_id' => $row['client_public_id'],
                'full_name' => $row['client_name'],
                'phone' => $row['client_phone'],
            ],
            'service' => [
                'public_id' => $row['service_public_id'],
                'name_ar' => $row['service_name_ar'],
                'name_en' => $row['service_name_en'],
                'official_fee_baisa' => (int) $row['official_fee_baisa'],
                'office_fee_baisa' => (int) $row['office_fee_baisa'],
            ],
            'assignee' => $row['assignee_public_id'] === null ? null : [
                'public_id' => $row['assignee_public_id'],
                'full_name' => $row['assignee_name'],
            ],
            'opened_at' => $row['opened_at'],
            'due_at' => $row['due_at'],
            'completed_at' => $row['completed_at'],
            'cancelled_at' => $row['cancelled_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}
