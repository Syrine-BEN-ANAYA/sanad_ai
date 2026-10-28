<?php

declare(strict_types=1);

namespace SanadAi\Services;

use PDO;

final class ServiceRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        int $tenantId,
        string $publicId,
        ?int $ministryId,
        string $nameAr,
        ?string $nameEn,
        ?string $descriptionAr,
        ?string $descriptionEn,
        int $officialFeeBaisa,
        int $officeFeeBaisa,
        ?int $targetSlaMinutes,
    ): Service {
        $stmt = $this->pdo->prepare("
            INSERT INTO services (
                public_id,
                tenant_id,
                ministry_id,
                name_ar,
                name_en,
                description_ar,
                description_en,
                official_fee_baisa,
                office_fee_baisa,
                target_sla_minutes
            ) VALUES (
                :public_id,
                :tenant_id,
                :ministry_id,
                :name_ar,
                :name_en,
                :description_ar,
                :description_en,
                :official_fee_baisa,
                :office_fee_baisa,
                :target_sla_minutes
            )
        ");

        $stmt->execute([
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'ministry_id' => $ministryId,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'official_fee_baisa' => $officialFeeBaisa,
            'office_fee_baisa' => $officeFeeBaisa,
            'target_sla_minutes' => $targetSlaMinutes,
        ]);

        $service = $this->findByPublicId(
            $tenantId,
            $publicId
        );

        if ($service === null) {
            throw new \RuntimeException(
                'Service was created but could not be loaded.'
            );
        }

        return $service;
    }

    /**
     * Find one service by public ID, strictly inside the tenant.
     */
    public function findByPublicId(
        int $tenantId,
        string $publicId
    ): ?Service {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                ministry_id,
                name_ar,
                name_en,
                description_ar,
                description_en,
                official_fee_baisa,
                office_fee_baisa,
                target_sla_minutes,
                status,
                created_at,
                updated_at
            FROM services
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

        return $this->mapToService($row);
    }

    /**
     * Find one service by internal ID, strictly inside the tenant.
     *
     * The tenant condition prevents cross-tenant access.
     */
    public function findById(
        int $tenantId,
        int $serviceId
    ): ?Service {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                ministry_id,
                name_ar,
                name_en,
                description_ar,
                description_en,
                official_fee_baisa,
                office_fee_baisa,
                target_sla_minutes,
                status,
                created_at,
                updated_at
            FROM services
            WHERE tenant_id = :tenant_id
              AND id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $serviceId,
        ]);

        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->mapToService($row);
    }

    /**
     * @return list<Service>
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
                ministry_id,
                name_ar,
                name_en,
                description_ar,
                description_en,
                official_fee_baisa,
                office_fee_baisa,
                target_sla_minutes,
                status,
                created_at,
                updated_at
            FROM services
            WHERE tenant_id = :tenant_id
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(
            ':tenant_id',
            $tenantId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            fn (array $row): Service =>
                $this->mapToService($row),
            $rows
        );
    }

    /**
     * Search services inside the tenant.
     *
     * @return list<Service>
     */
    public function search(
        int $tenantId,
        ?string $query,
        ?string $status,
        int $limit = 50,
        int $offset = 0,
    ): array {
        [$where, $params] = $this->filters($tenantId, $query, $status);

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare("
            SELECT
                id, public_id, tenant_id, ministry_id,
                name_ar, name_en, description_ar, description_en,
                official_fee_baisa, office_fee_baisa, target_sla_minutes,
                status, created_at, updated_at
            FROM services
            WHERE {$where}
            ORDER BY name_ar ASC, id ASC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return array_map(
            fn (array $row): Service => $this->mapToService($row),
            $stmt->fetchAll()
        );
    }

    public function count(
        int $tenantId,
        ?string $query,
        ?string $status,
    ): int {
        [$where, $params] = $this->filters($tenantId, $query, $status);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM services WHERE {$where}");
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
            $where[] = '(name_ar LIKE :q1 OR name_en LIKE :q2)';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        return [implode(' AND ', $where), $params];
    }

    public function update(
        int $tenantId,
        string $publicId,
        ?int $ministryId,
        string $nameAr,
        ?string $nameEn,
        ?string $descriptionAr,
        ?string $descriptionEn,
        int $officialFeeBaisa,
        int $officeFeeBaisa,
        ?int $targetSlaMinutes,
        string $status,
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE services
            SET
                ministry_id = :ministry_id,
                name_ar = :name_ar,
                name_en = :name_en,
                description_ar = :description_ar,
                description_en = :description_en,
                official_fee_baisa = :official_fee_baisa,
                office_fee_baisa = :office_fee_baisa,
                target_sla_minutes = :target_sla_minutes,
                status = :status
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
            'ministry_id' => $ministryId,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'official_fee_baisa' => $officialFeeBaisa,
            'office_fee_baisa' => $officeFeeBaisa,
            'target_sla_minutes' => $targetSlaMinutes,
            'status' => $status,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivate(
        int $tenantId,
        string $publicId
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE services
            SET status = 'inactive'
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
              AND status = 'active'
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'public_id' => $publicId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapToService(array $row): Service
    {
        return new Service(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            tenantId: (int) $row['tenant_id'],
            ministryId: $row['ministry_id'] !== null
                ? (int) $row['ministry_id']
                : null,
            nameAr: (string) $row['name_ar'],
            nameEn: $row['name_en'] !== null
                ? (string) $row['name_en']
                : null,
            descriptionAr: $row['description_ar'] !== null
                ? (string) $row['description_ar']
                : null,
            descriptionEn: $row['description_en'] !== null
                ? (string) $row['description_en']
                : null,
            officialFeeBaisa: (int) $row['official_fee_baisa'],
            officeFeeBaisa: (int) $row['office_fee_baisa'],
            targetSlaMinutes: $row['target_sla_minutes'] !== null
                ? (int) $row['target_sla_minutes']
                : null,
            status: (string) $row['status'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
