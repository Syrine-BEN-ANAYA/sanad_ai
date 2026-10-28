<?php

declare(strict_types=1);

namespace SanadAi\Transactions;

use PDO;
use RuntimeException;
use SanadAi\Http\NotFoundException;
use SanadAi\Clients\ClientRepository;
use SanadAi\Services\ServiceRepository;

final class TransactionManager
{
    private const STATUSES = [
        'inquiry',
        'open',
        'waiting_documents',
        'in_progress',
        'waiting_payment',
        'waiting_external',
        'waiting_client',
        'completed',
        'cancelled',
    ];

    private const SOURCES = [
        'dashboard',
        'whatsapp',
        'converted_inquiry',
    ];

    private const PRIORITIES = [
        'normal',
        'high',
        'urgent',
    ];

    /**
     * Allowed state transitions.
     *
     * null represents the initial state when creating a transaction.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'inquiry' => [
            'open',
            'cancelled',
        ],
        'open' => [
            'waiting_documents',
            'in_progress',
            'waiting_external',
            'waiting_client',
            'waiting_payment',
            'cancelled',
        ],
        'waiting_documents' => [
            'in_progress',
            'waiting_client',
            'cancelled',
        ],
        'in_progress' => [
            'waiting_documents',
            'waiting_payment',
            'waiting_external',
            'waiting_client',
            'completed',
            'cancelled',
        ],
        'waiting_payment' => [
            'in_progress',
            'completed',
            'cancelled',
        ],
        'waiting_external' => [
            'in_progress',
            'waiting_client',
            'cancelled',
        ],
        'waiting_client' => [
            'in_progress',
            'cancelled',
        ],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly TransactionRepository $repository,
        private readonly ClientRepository $clientRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Statuses a transaction may move to from the given status.
     *
     * @return list<string>
     */
    public function allowedTransitions(string $status): array
    {
        return self::ALLOWED_TRANSITIONS[$status] ?? [];
    }

    /**
     * Create a new transaction.
     */
    public function create(
        int $tenantId,
        int $clientId,
        int $serviceId,
        string $title,
        ?string $description = null,
        ?int $assignedUserId = null,
        string $source = 'dashboard',
        string $priority = 'normal',
        ?string $dueAt = null,
        ?int $createdByUserId = null,
    ): Transaction {
        $this->validateTenantId($tenantId);

        /*
         * Verify that the client belongs to the current tenant.
         */
        $client = $this->clientRepository->findById(
            $tenantId,
            $clientId
        );

        if ($client === null) {
            throw new \InvalidArgumentException('Client not found.');
        }

        /*
         * Verify that the service belongs to the current tenant.
         */
        $service = $this->serviceRepository->findById(
            $tenantId,
            $serviceId
        );

        if ($service === null) {
            throw new \InvalidArgumentException('Service not found.');
        }

        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException(
                'Transaction title is required.'
            );
        }

        if (mb_strlen($title) > 255) {
            throw new \InvalidArgumentException(
                'Transaction title must not exceed 255 characters.'
            );
        }

        $description = $this->normalizeNullableText(
            $description,
            5000,
            'Transaction description'
        );

        $source = $this->validateChoice(
            $source,
            self::SOURCES,
            'Invalid transaction source.'
        );

        $priority = $this->validateChoice(
            $priority,
            self::PRIORITIES,
            'Invalid transaction priority.'
        );

        $this->validateAssignedUser(
            $tenantId,
            $assignedUserId
        );

        $dueAt = $this->normalizeDateTime($dueAt);

        $publicId = $this->generatePublicId();

        $reference = $this->generateReference(
            $tenantId
        );

        $openedAt = date('Y-m-d H:i:s');

        /*
         * New transactions start as "open". The row and its first
         * history entry are written atomically.
         */
        $this->pdo->beginTransaction();

        try {
            $transaction = $this->repository->create(
                tenantId: $tenantId,
                publicId: $publicId,
                clientId: $clientId,
                serviceId: $serviceId,
                reference: $reference,
                title: $title,
                description: $description,
                status: 'open',
                assignedUserId: $assignedUserId,
                source: $source,
                priority: $priority,
                openedAt: $openedAt,
                dueAt: $dueAt,
            );

            $this->repository->addStatusHistory(
                tenantId: $tenantId,
                transactionId: $transaction->id,
                fromStatus: null,
                toStatus: 'open',
                changedByUserId: $createdByUserId,
                reason: 'Transaction created.',
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return $transaction;
    }

    /**
     * Change transaction status.
     */
    public function changeStatus(
        int $tenantId,
        string $publicId,
        string $newStatus,
        ?int $changedByUserId = null,
        ?string $reason = null,
    ): Transaction {
        $this->validateTenantId($tenantId);

        $transaction = $this->repository->findByPublicId(
            $tenantId,
            $publicId
        );

        if ($transaction === null) {
            throw new NotFoundException('Transaction not found.');
        }

        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Invalid transaction status.'
            );
        }

        if ($transaction->status === $newStatus) {
            throw new \InvalidArgumentException(
                'Transaction is already in this status.'
            );
        }

        $allowed = self::ALLOWED_TRANSITIONS[
            $transaction->status
        ] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid status transition: %s -> %s.',
                    $transaction->status,
                    $newStatus
                )
            );
        }

        $reason = $this->normalizeNullableText(
            $reason,
            500,
            'Transition reason'
        );

        $completedAt = $transaction->completedAt;
        $cancelledAt = $transaction->cancelledAt;

        if ($newStatus === 'completed') {
            $completedAt = date('Y-m-d H:i:s');
        }

        if ($newStatus === 'cancelled') {
            $cancelledAt = date('Y-m-d H:i:s');
        }

        $this->pdo->beginTransaction();

        try {
            $updated = $this->repository->updateStatus(
                tenantId: $tenantId,
                publicId: $publicId,
                status: $newStatus,
                completedAt: $completedAt,
                cancelledAt: $cancelledAt,
            );

            if (!$updated) {
                throw new RuntimeException(
                    'Transaction status update failed.'
                );
            }

            $this->repository->addStatusHistory(
                tenantId: $tenantId,
                transactionId: $transaction->id,
                fromStatus: $transaction->status,
                toStatus: $newStatus,
                changedByUserId: $changedByUserId,
                reason: $reason,
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return $this->repository->findByPublicId(
            $tenantId,
            $publicId
        ) ?? throw new RuntimeException(
            'Transaction could not be reloaded.'
        );
    }

    /**
     * Update an existing transaction.
     */
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
    ): Transaction {
        $this->validateTenantId($tenantId);

        $existing = $this->repository->findByPublicId(
            $tenantId,
            $publicId
        );

        if ($existing === null) {
            throw new NotFoundException('Transaction not found.');
        }

        /*
         * Completed and cancelled transactions are immutable.
         */
        if (
            $existing->status === 'completed'
            || $existing->status === 'cancelled'
        ) {
            throw new \InvalidArgumentException(
                'Completed or cancelled transactions cannot be edited.'
            );
        }

        /*
         * Verify client belongs to current tenant.
         */
        if (
            $this->clientRepository->findById(
                $tenantId,
                $clientId
            ) === null
        ) {
            throw new \InvalidArgumentException('Client not found.');
        }

        /*
         * Verify service belongs to current tenant.
         */
        if (
            $this->serviceRepository->findById(
                $tenantId,
                $serviceId
            ) === null
        ) {
            throw new \InvalidArgumentException('Service not found.');
        }

        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException(
                'Transaction title is required.'
            );
        }

        if (mb_strlen($title) > 255) {
            throw new \InvalidArgumentException(
                'Transaction title must not exceed 255 characters.'
            );
        }

        $description = $this->normalizeNullableText(
            $description,
            5000,
            'Transaction description'
        );

        $source = $this->validateChoice(
            $source,
            self::SOURCES,
            'Invalid transaction source.'
        );

        $priority = $this->validateChoice(
            $priority,
            self::PRIORITIES,
            'Invalid transaction priority.'
        );

        $this->validateAssignedUser(
            $tenantId,
            $assignedUserId
        );

        $dueAt = $this->normalizeDateTime(
            $dueAt
        );

        $this->repository->update(
            tenantId: $tenantId,
            publicId: $publicId,
            clientId: $clientId,
            serviceId: $serviceId,
            title: $title,
            description: $description,
            assignedUserId: $assignedUserId,
            source: $source,
            priority: $priority,
            dueAt: $dueAt,
        );

        return $this->repository->findByPublicId(
            $tenantId,
            $publicId
        ) ?? throw new RuntimeException(
            'Transaction could not be reloaded.'
        );
    }

    /**
     * Find one transaction strictly inside the tenant.
     */
    public function find(
        int $tenantId,
        string $publicId,
    ): ?Transaction {
        $this->validateTenantId($tenantId);

        return $this->repository->findByPublicId(
            $tenantId,
            $publicId
        );
    }

    /**
     * List transactions strictly inside the tenant.
     *
     * @return list<Transaction>
     */
    public function list(
        int $tenantId,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $this->validateTenantId($tenantId);

        /*
         * Protect the API from unreasonable pagination values.
         */
        $limit = max(
            1,
            min($limit, 100)
        );

        $offset = max(
            0,
            $offset
        );

        return $this->repository->findAll(
            tenantId: $tenantId,
            limit: $limit,
            offset: $offset,
        );
    }

    /**
     * Validate tenant ID.
     */
    private function validateTenantId(
        int $tenantId
    ): void {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException(
                'Invalid tenant ID.'
            );
        }
    }

    /**
     * Validate that an assigned user belongs to the tenant
     * and is active.
     */
    private function validateAssignedUser(
        int $tenantId,
        ?int $assignedUserId,
    ): void {
        if ($assignedUserId === null) {
            return;
        }

        if ($assignedUserId <= 0) {
            throw new \InvalidArgumentException(
                'Invalid assigned user ID.'
            );
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM users
            WHERE id = :id
              AND tenant_id = :tenant_id
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $assignedUserId,
            ':tenant_id' => $tenantId,
        ]);

        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException('Assigned user not found.');
        }
    }

    /**
     * Validate a value against a predefined list.
     */
    private function validateChoice(
        string $value,
        array $allowed,
        string $message,
    ): string {
        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException(
                $message
            );
        }

        return $value;
    }

    /**
     * Normalize nullable text.
     */
    private function normalizeNullableText(
        ?string $value,
        int $maxLength,
        string $field,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new \InvalidArgumentException(
                "{$field} must not exceed {$maxLength} characters."
            );
        }

        return $value;
    }

    /**
     * Normalize and validate date/time.
     */
    private function normalizeDateTime(
        ?string $value,
    ): ?string {
        if (
            $value === null
            || trim($value) === ''
        ) {
            return null;
        }

        $value = trim($value);

        $date = false;

        foreach (['Y-m-d H:i:s', 'Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $candidate = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if (
                $candidate !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ) {
                $date = $candidate;
                break;
            }
        }

        if ($date === false) {
            throw new \InvalidArgumentException(
                'Invalid due date format.'
            );
        }

        return $date->format(
            'Y-m-d H:i:s'
        );
    }

    /**
     * Generate UUID v4-like public identifier.
     */
    private function generatePublicId(): string
    {
        $bytes = random_bytes(16);

        $bytes[6] = chr(
            (ord($bytes[6]) & 0x0f) | 0x40
        );

        $bytes[8] = chr(
            (ord($bytes[8]) & 0x3f) | 0x80
        );

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(
                substr($bytes, 0, 4)
            ),
            bin2hex(
                substr($bytes, 4, 2)
            ),
            bin2hex(
                substr($bytes, 6, 2)
            ),
            bin2hex(
                substr($bytes, 8, 2)
            ),
            bin2hex(
                substr($bytes, 10, 6)
            ),
        );
    }

    /**
     * Generate a unique tenant-scoped transaction reference.
     */
    private function generateReference(
        int $tenantId
    ): string {
        do {
            $reference = sprintf(
                'SAN-%s-%s',
                date('Ymd'),
                strtoupper(
                    bin2hex(
                        random_bytes(4)
                    )
                )
            );

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM transactions
                WHERE tenant_id = :tenant_id
                  AND reference = :reference
                LIMIT 1
            ");

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':reference' => $reference,
            ]);
        } while (
            $stmt->fetchColumn() !== false
        );

        return $reference;
    }
}

