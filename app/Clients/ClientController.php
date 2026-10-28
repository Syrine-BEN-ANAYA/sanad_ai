<?php

declare(strict_types=1);

namespace SanadAi\Clients;

use SanadAi\Http\Input;
use SanadAi\Http\NotFoundException;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Tenancy\TenantContext;

final class ClientController
{
    public function __construct(
        private readonly ClientService $service,
        private readonly ClientRepository $repository,
        private readonly TenantContext $tenantContext,
        private readonly Response $response,
    ) {
    }

    /** GET /api/clients?q=&status=&limit=&offset= */
    public function index(Request $request): void
    {
        $tenantId = $this->tenantContext->require();
        [$limit, $offset] = Input::pagination($request);
        $query = Input::queryString($request, 'q');
        $status = Input::queryString($request, 'status');

        $clients = $this->repository->search($tenantId, $query, $status, $limit, $offset);

        $this->response->json([
            'success' => true,
            'data' => array_map(fn (Client $c): array => $this->serialize($c), $clients),
            'meta' => [
                'total' => $this->repository->count($tenantId, $query, $status),
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /** GET /api/clients/{publicId} */
    public function show(Request $request, string $publicId): void
    {
        $client = $this->repository->findByPublicId(
            $this->tenantContext->require(),
            $publicId
        );

        if ($client === null) {
            throw new NotFoundException('Client not found.');
        }

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($client),
        ]);
    }

    /** POST /api/clients */
    public function store(Request $request): void
    {
        $data = $request->json();

        $client = $this->service->create(
            tenantId: $this->tenantContext->require(),
            clientType: Input::string($data, 'client_type', 'person'),
            fullName: Input::string($data, 'full_name'),
            companyName: Input::nullableString($data, 'company_name'),
            phone: Input::nullableString($data, 'phone'),
            whatsappNumber: Input::nullableString($data, 'whatsapp_number'),
            email: Input::nullableString($data, 'email'),
            preferredLanguage: Input::string($data, 'preferred_language', 'ar'),
            notes: Input::nullableString($data, 'notes'),
        );

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($client),
        ], 201);
    }

    /**
     * PUT /api/clients/{publicId}
     *
     * Fields that are absent keep their current value; fields sent as
     * null or "" are cleared.
     */
    public function update(Request $request, string $publicId): void
    {
        $tenantId = $this->tenantContext->require();

        $existing = $this->repository->findByPublicId($tenantId, $publicId);

        if ($existing === null) {
            throw new NotFoundException('Client not found.');
        }

        $data = $request->json();

        $optional = static fn (string $key, ?string $current): ?string =>
            array_key_exists($key, $data)
                ? Input::nullableString($data, $key)
                : $current;

        $clientType = strtolower(trim(Input::string($data, 'client_type', $existing->clientType)));
        $fullName = trim(Input::string($data, 'full_name', $existing->fullName));
        $companyName = $optional('company_name', $existing->companyName);
        $phone = $optional('phone', $existing->phone);
        $whatsapp = $optional('whatsapp_number', $existing->whatsappNumber);
        $email = $optional('email', $existing->email);
        $language = strtolower(trim(Input::string($data, 'preferred_language', $existing->preferredLanguage)));
        $notes = $optional('notes', $existing->notes);
        $status = strtolower(trim(Input::string($data, 'status', $existing->status)));

        $this->service->validateForUpdate(
            clientType: $clientType,
            fullName: $fullName,
            companyName: $companyName,
            phone: $phone,
            whatsappNumber: $whatsapp,
            email: $email,
            preferredLanguage: $language,
            notes: $notes,
            status: $status,
        );

        $this->repository->update(
            tenantId: $tenantId,
            publicId: $publicId,
            clientType: $clientType,
            fullName: $fullName,
            companyName: $companyName,
            phone: $phone,
            whatsappNumber: $whatsapp,
            email: $email,
            preferredLanguage: $language,
            notes: $notes,
            status: $status,
        );

        $client = $this->repository->findByPublicId($tenantId, $publicId);

        $this->response->json([
            'success' => true,
            'data' => $client !== null ? $this->serialize($client) : null,
        ]);
    }

    /** DELETE /api/clients/{publicId} (soft: marks inactive) */
    public function destroy(Request $request, string $publicId): void
    {
        $tenantId = $this->tenantContext->require();

        if ($this->repository->findByPublicId($tenantId, $publicId) === null) {
            throw new NotFoundException('Client not found.');
        }

        $this->repository->deactivate($tenantId, $publicId);

        $this->response->json([
            'success' => true,
            'message' => 'Client deactivated.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Client $client): array
    {
        return [
            'public_id' => $client->publicId,
            'client_type' => $client->clientType,
            'full_name' => $client->fullName,
            'company_name' => $client->companyName,
            'phone' => $client->phone,
            'whatsapp_number' => $client->whatsappNumber,
            'email' => $client->email,
            'preferred_language' => $client->preferredLanguage,
            'notes' => $client->notes,
            'status' => $client->status,
            'created_at' => $client->createdAt,
            'updated_at' => $client->updatedAt,
        ];
    }
}
