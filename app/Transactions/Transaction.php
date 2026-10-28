<?php

declare(strict_types=1);

namespace SanadAi\Transactions;

final class Transaction
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $tenantId,
        public readonly int $clientId,
        public readonly int $serviceId,
        public readonly string $reference,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $status,
        public readonly ?int $assignedUserId,
        public readonly string $source,
        public readonly string $priority,
        public readonly ?string $openedAt,
        public readonly ?string $dueAt,
        public readonly ?string $completedAt,
        public readonly ?string $cancelledAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }
}