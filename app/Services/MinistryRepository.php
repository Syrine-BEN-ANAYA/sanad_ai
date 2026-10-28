<?php

declare(strict_types=1);

namespace SanadAi\Services;

use PDO;

final class MinistryRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function exists(int $ministryId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ministries
            WHERE id = :id
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $ministryId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array{
     *     id:int,
     *     public_id:string,
     *     name_ar:string,
     *     name_en:?string,
     *     code:?string,
     *     status:string
     * }|null
     */
    public function findById(int $ministryId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                name_ar,
                name_en,
                code,
                status
            FROM ministries
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $ministryId,
        ]);

        $row = $stmt->fetch();

        return $row !== false
            ? [
                'id' => (int) $row['id'],
                'public_id' => (string) $row['public_id'],
                'name_ar' => (string) $row['name_ar'],
                'name_en' => $row['name_en'] !== null
                    ? (string) $row['name_en']
                    : null,
                'code' => $row['code'] !== null
                    ? (string) $row['code']
                    : null,
                'status' => (string) $row['status'],
            ]
            : null;
    }

    /**
     * All active ministries, for dropdowns.
     *
     * @return list<array{id:int, public_id:string, code:?string, name_ar:string, name_en:?string}>
     */
    public function allActive(): array
    {
        $rows = $this->pdo->query("
            SELECT id, public_id, code, name_ar, name_en
            FROM ministries
            WHERE status = 'active'
            ORDER BY name_ar ASC
        ")->fetchAll();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'public_id' => (string) $r['public_id'],
            'code' => $r['code'] !== null ? (string) $r['code'] : null,
            'name_ar' => (string) $r['name_ar'],
            'name_en' => $r['name_en'] !== null ? (string) $r['name_en'] : null,
        ], $rows);
    }

    /**
     * Active ministries indexed by internal id.
     *
     * @return array<int, array{id:int, public_id:string, code:?string, name_ar:string, name_en:?string}>
     */
    public function allById(): array
    {
        $indexed = [];

        foreach ($this->allActive() as $ministry) {
            $indexed[$ministry['id']] = $ministry;
        }

        return $indexed;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM ministries
            WHERE public_id = :public_id AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute(['public_id' => $publicId]);

        $id = $stmt->fetchColumn();

        return $id === false ? null : $this->findById((int) $id);
    }
}
