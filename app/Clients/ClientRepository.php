<?php declare(strict_types=1); namespace SanadAi\Clients;
namespace SanadAi\Clients;

use PDO;
use RuntimeException;

final class ClientRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Create a client inside the current tenant.
     */
    public function create(
        int $tenantId,
        string $publicId,
        string $clientType,
        string $fullName,
        ?string $companyName,
        ?string $phone,
        ?string $whatsappNumber,
        ?string $email,
        string $preferredLanguage,
        ?string $notes,
    ): Client {
        $sql = "
            INSERT INTO clients (
                public_id,
                tenant_id,
                client_type,
                full_name,
                company_name,
                phone,
                whatsapp_number,
                email,
                preferred_language,
                notes
            ) VALUES (
                :public_id,
                :tenant_id,
                :client_type,
                :full_name,
                :company_name,
                :phone,
                :whatsapp_number,
                :email,
                :preferred_language,
                :notes
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'client_type' => $clientType,
            'full_name' => $fullName,
            'company_name' => $companyName,
            'phone' => $phone,
            'whatsapp_number' => $whatsappNumber,
            'email' => $email,
            'preferred_language' => $preferredLanguage,
            'notes' => $notes,
        ]);

        return $this->findByPublicId($tenantId, $publicId)
            ?? throw new RuntimeException('Client creation failed.');
    }

    /**
     * Find one client by public ID, strictly inside the tenant.
     */
    public function findByPublicId(
        int $tenantId,
        string $publicId,
    ): ?Client {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                client_type,
                full_name,
                company_name,
                phone,
                whatsapp_number,
                email,
                preferred_language,
                notes,
                status,
                created_at,
                updated_at
            FROM clients
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
            LIMIT 1
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
        ]);

        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->mapToClient($row);
    }

    /**
     * Find one client by internal ID, strictly inside the tenant.
     *
     * The tenant condition is mandatory to prevent cross-tenant access.
     */
    public function findById(
        int $tenantId,
        int $clientId,
    ): ?Client {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                client_type,
                full_name,
                company_name,
                phone,
                whatsapp_number,
                email,
                preferred_language,
                notes,
                status,
                created_at,
                updated_at
            FROM clients
            WHERE tenant_id = :tenant_id
              AND id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $clientId,
        ]);

        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->mapToClient($row);
    }

    /**
     * List clients belonging only to the tenant.
     */
    public function findAll(
        int $tenantId,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                client_type,
                full_name,
                company_name,
                phone,
                whatsapp_number,
                email,
                preferred_language,
                notes,
                status,
                created_at,
                updated_at
            FROM clients
            WHERE tenant_id = :tenant_id
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
        ]);

        $clients = [];

        while ($row = $stmt->fetch()) {
            $clients[] = $this->mapToClient($row);
        }

        return $clients;
    }

    /**
     * Search clients inside the tenant.
     *
     * @return list<Client>
     */
    public function search(
        int $tenantId,
        ?string $query,
        ?string $status,
        int $limit = 25,
        int $offset = 0,
    ): array {
        [$where, $params] = $this->filters($tenantId, $query, $status);

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare("
            SELECT
                id, public_id, tenant_id, client_type, full_name,
                company_name, phone, whatsapp_number, email,
                preferred_language, notes, status, created_at, updated_at
            FROM clients
            WHERE {$where}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return array_map(
            fn (array $row): Client => $this->mapToClient($row),
            $stmt->fetchAll()
        );
    }

    public function count(int $tenantId, ?string $query, ?string $status): int
    {
        [$where, $params] = $this->filters($tenantId, $query, $status);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM clients WHERE {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filters(int $tenantId, ?string $query, ?string $status): array
    {
        $where = ['tenant_id = :tenant_id'];
        $params = ['tenant_id' => $tenantId];

        if ($status !== null && in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($query !== null && trim($query) !== '') {
            $like = '%' . addcslashes(trim($query), '\\%_') . '%';
            $cols = ['full_name', 'company_name', 'phone', 'whatsapp_number', 'email'];
            $parts = [];

            foreach ($cols as $i => $col) {
                $parts[] = "{$col} LIKE :q{$i}";
                $params["q{$i}"] = $like;
            }

            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Update a client, strictly inside the tenant.
     */
    public function update(
        int $tenantId,
        string $publicId,
        string $clientType,
        string $fullName,
        ?string $companyName,
        ?string $phone,
        ?string $whatsappNumber,
        ?string $email,
        string $preferredLanguage,
        ?string $notes,
        string $status,
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE clients
            SET
                client_type = :client_type,
                full_name = :full_name,
                company_name = :company_name,
                phone = :phone,
                whatsapp_number = :whatsapp_number,
                email = :email,
                preferred_language = :preferred_language,
                notes = :notes,
                status = :status
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
            'client_type' => $clientType,
            'full_name' => $fullName,
            'company_name' => $companyName,
            'phone' => $phone,
            'whatsapp_number' => $whatsappNumber,
            'email' => $email,
            'preferred_language' => $preferredLanguage,
            'notes' => $notes,
            'status' => $status,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete by marking the client inactive.
     */
    public function deactivate(
        int $tenantId,
        string $publicId,
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE clients
            SET status = 'inactive'
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
              AND status <> 'inactive'
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Convert a database row into the domain model.
     *
     * @param array<string, mixed> $row
     */
    private function mapToClient(array $row): Client
    {
        return new Client(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            tenantId: (int) $row['tenant_id'],
            clientType: (string) $row['client_type'],
            fullName: (string) $row['full_name'],
            companyName: $row['company_name'] !== null
                ? (string) $row['company_name']
                : null,
            phone: $row['phone'] !== null
                ? (string) $row['phone']
                : null,
            whatsappNumber: $row['whatsapp_number'] !== null
                ? (string) $row['whatsapp_number']
                : null,
            email: $row['email'] !== null
                ? (string) $row['email']
                : null,
            preferredLanguage: (string) $row['preferred_language'],
            notes: $row['notes'] !== null
                ? (string) $row['notes']
                : null,
            status: (string) $row['status'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
