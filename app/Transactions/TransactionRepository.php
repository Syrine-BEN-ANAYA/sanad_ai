<?php

declare(strict_types=1);

namespace SanadAi\Transactions;

use PDO;

final class TransactionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        int $tenantId,
        string $publicId,
        int $clientId,
        int $serviceId,
        string $reference,
        string $title,
        ?string $description,
        string $status,
        ?int $assignedUserId,
        string $source,
        string $priority,
        ?string $openedAt,
        ?string $dueAt,
    ): Transaction {
        $sql = "
            INSERT INTO transactions (
                public_id,
                tenant_id,
                client_id,
                service_id,
                reference,
                title,
                description,
                status,
                assigned_user_id,
                source,
                priority,
                opened_at,
                due_at
            ) VALUES (
                :public_id,
                :tenant_id,
                :client_id,
                :service_id,
                :reference,
                :title,
                :description,
                :status,
                :assigned_user_id,
                :source,
                :priority,
                :opened_at,
                :due_at
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':public_id' => $publicId,
            ':tenant_id' => $tenantId,
            ':client_id' => $clientId,
            ':service_id' => $serviceId,
            ':reference' => $reference,
            ':title' => $title,
            ':description' => $description,
            ':status' => $status,
            ':assigned_user_id' => $assignedUserId,
            ':source' => $source,
            ':priority' => $priority,
            ':opened_at' => $openedAt,
            ':due_at' => $dueAt,
        ]);

        return $this->findByPublicId($tenantId, $publicId)
            ?? throw new \RuntimeException('Transaction creation failed.');
    }

    public function findByPublicId(
        int $tenantId,
        string $publicId,
    ): ?Transaction {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                client_id,
                service_id,
                reference,
                title,
                description,
                status,
                assigned_user_id,
                source,
                priority,
                opened_at,
                due_at,
                completed_at,
                cancelled_at,
                created_at,
                updated_at
            FROM transactions
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':public_id' => $publicId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false
            ? null
            : $this->mapToTransaction($row);
    }

    /**
     * @return list<Transaction>
     */
    public function findAll(
        int $tenantId,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                tenant_id,
                client_id,
                service_id,
                reference,
                title,
                description,
                status,
                assigned_user_id,
                source,
                priority,
                opened_at,
                due_at,
                completed_at,
                cancelled_at,
                created_at,
                updated_at
            FROM transactions
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

        $transactions = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $this->mapToTransaction($row);
        }

        return $transactions;
    }

    public function update(
        int $tenantId,
        string $publicId,
        int $clientId,
        int $serviceId,
        string $title,
        ?string $description,
        ?int $assignedUserId,
        string $source,
        string $priority,
        ?string $dueAt,
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE transactions
            SET
                client_id = :client_id,
                service_id = :service_id,
                title = :title,
                description = :description,
                assigned_user_id = :assigned_user_id,
                source = :source,
                priority = :priority,
                due_at = :due_at
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':public_id' => $publicId,
            ':client_id' => $clientId,
            ':service_id' => $serviceId,
            ':title' => $title,
            ':description' => $description,
            ':assigned_user_id' => $assignedUserId,
            ':source' => $source,
            ':priority' => $priority,
            ':due_at' => $dueAt,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateStatus(
        int $tenantId,
        string $publicId,
        string $status,
        ?string $completedAt,
        ?string $cancelledAt,
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE transactions
            SET
                status = :status,
                completed_at = :completed_at,
                cancelled_at = :cancelled_at
            WHERE tenant_id = :tenant_id
              AND public_id = :public_id
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':public_id' => $publicId,
            ':status' => $status,
            ':completed_at' => $completedAt,
            ':cancelled_at' => $cancelledAt,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function addStatusHistory(
        int $tenantId,
        int $transactionId,
        ?string $fromStatus,
        string $toStatus,
        ?int $changedByUserId,
        ?string $reason,
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO transaction_status_history (
                tenant_id,
                transaction_id,
                from_status,
                to_status,
                changed_by_user_id,
                reason
            ) VALUES (
                :tenant_id,
                :transaction_id,
                :from_status,
                :to_status,
                :changed_by_user_id,
                :reason
            )
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':transaction_id' => $transactionId,
            ':from_status' => $fromStatus,
            ':to_status' => $toStatus,
            ':changed_by_user_id' => $changedByUserId,
            ':reason' => $reason,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findStatusHistory(
        int $tenantId,
        string $publicId,
    ): array {
        $stmt = $this->pdo->prepare("
            SELECT
                h.id,
                h.transaction_id,
                h.from_status,
                h.to_status,
                h.changed_by_user_id,
                h.reason,
                h.created_at,
                u.full_name AS changed_by_name
            FROM transaction_status_history h
            INNER JOIN transactions t
                ON t.id = h.transaction_id
               AND t.tenant_id = h.tenant_id
            LEFT JOIN users u
                ON u.id = h.changed_by_user_id
            WHERE h.tenant_id = :tenant_id
              AND t.public_id = :public_id
            ORDER BY h.id ASC
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':public_id' => $publicId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private const DETAIL_SELECT = "
        SELECT
            t.id,
            t.public_id,
            t.reference,
            t.title,
            t.description,
            t.status,
            t.priority,
            t.source,
            t.opened_at,
            t.due_at,
            t.completed_at,
            t.cancelled_at,
            t.created_at,
            t.updated_at,
            c.public_id AS client_public_id,
            c.full_name AS client_name,
            c.phone AS client_phone,
            s.public_id AS service_public_id,
            s.name_ar AS service_name_ar,
            s.name_en AS service_name_en,
            s.official_fee_baisa,
            s.office_fee_baisa,
            u.public_id AS assignee_public_id,
            u.full_name AS assignee_name
        FROM transactions t
        INNER JOIN clients c
            ON c.id = t.client_id AND c.tenant_id = t.tenant_id
        INNER JOIN services s
            ON s.id = t.service_id AND s.tenant_id = t.tenant_id
        LEFT JOIN users u
            ON u.id = t.assigned_user_id
    ";

    /**
     * Transactions with client / service / assignee names (read model).
     *
     * @param array{q?:?string,status?:?string,priority?:?string,client?:?string,overdue?:bool} $filters
     * @return list<array<string, mixed>>
     */
    public function searchDetailed(
        int $tenantId,
        array $filters,
        int $limit,
        int $offset,
    ): array {
        [$where, $params] = $this->detailFilters($tenantId, $filters);

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stmt = $this->pdo->prepare(
            self::DETAIL_SELECT . " WHERE {$where} ORDER BY t.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{q?:?string,status?:?string,priority?:?string,client?:?string,overdue?:bool} $filters
     */
    public function countDetailed(int $tenantId, array $filters): int
    {
        [$where, $params] = $this->detailFilters($tenantId, $filters);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM transactions t
            INNER JOIN clients c
                ON c.id = t.client_id AND c.tenant_id = t.tenant_id
            WHERE {$where}
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDetailed(int $tenantId, string $publicId): ?array
    {
        $stmt = $this->pdo->prepare(
            self::DETAIL_SELECT . " WHERE t.tenant_id = :tenant_id AND t.public_id = :public_id LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'public_id' => $publicId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Dashboard counters.
     *
     * @return array{total:int, by_status:array<string,int>, overdue:int, completed_this_month:int, office_fees_baisa_this_month:int}
     */
    public function stats(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) AS n
            FROM transactions
            WHERE tenant_id = :tenant_id
            GROUP BY status
        ");
        $stmt->execute(['tenant_id' => $tenantId]);

        $byStatus = [];
        $total = 0;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byStatus[(string) $row['status']] = (int) $row['n'];
            $total += (int) $row['n'];
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM transactions
            WHERE tenant_id = :tenant_id
              AND due_at IS NOT NULL
              AND due_at < NOW()
              AND status NOT IN ('completed', 'cancelled')
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $overdue = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS n, COALESCE(SUM(s.office_fee_baisa), 0) AS fees
            FROM transactions t
            INNER JOIN services s ON s.id = t.service_id
            WHERE t.tenant_id = :tenant_id
              AND t.status = 'completed'
              AND t.completed_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $month = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'overdue' => $overdue,
            'completed_this_month' => (int) $month['n'],
            'office_fees_baisa_this_month' => (int) $month['fees'],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function detailFilters(int $tenantId, array $filters): array
    {
        $where = ['t.tenant_id = :tenant_id'];
        $params = ['tenant_id' => $tenantId];

        $statuses = [
            'inquiry', 'open', 'waiting_documents', 'in_progress', 'waiting_payment',
            'waiting_external', 'waiting_client', 'completed', 'cancelled',
        ];

        if (isset($filters['status']) && in_array($filters['status'], $statuses, true)) {
            $where[] = 't.status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['priority']) && in_array($filters['priority'], ['normal', 'high', 'urgent'], true)) {
            $where[] = 't.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['client']) && is_string($filters['client'])) {
            $where[] = 'c.public_id = :client';
            $params['client'] = $filters['client'];
        }

        if (!empty($filters['overdue'])) {
            $where[] = "t.due_at IS NOT NULL AND t.due_at < NOW() AND t.status NOT IN ('completed', 'cancelled')";
        }

        if (!empty($filters['q']) && is_string($filters['q'])) {
            $like = '%' . addcslashes(trim($filters['q']), '\\%_') . '%';
            $where[] = '(t.reference LIKE :q1 OR t.title LIKE :q2 OR c.full_name LIKE :q3)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        return [implode(' AND ', $where), $params];
    }

    private function mapToTransaction(array $row): Transaction
    {
        return new Transaction(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            tenantId: (int) $row['tenant_id'],
            clientId: (int) $row['client_id'],
            serviceId: (int) $row['service_id'],
            reference: (string) $row['reference'],
            title: (string) $row['title'],
            description: $row['description'] !== null
                ? (string) $row['description']
                : null,
            status: (string) $row['status'],
            assignedUserId: $row['assigned_user_id'] !== null
                ? (int) $row['assigned_user_id']
                : null,
            source: (string) $row['source'],
            priority: (string) $row['priority'],
            openedAt: $row['opened_at'] !== null
                ? (string) $row['opened_at']
                : null,
            dueAt: $row['due_at'] !== null
                ? (string) $row['due_at']
                : null,
            completedAt: $row['completed_at'] !== null
                ? (string) $row['completed_at']
                : null,
            cancelledAt: $row['cancelled_at'] !== null
                ? (string) $row['cancelled_at']
                : null,
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}