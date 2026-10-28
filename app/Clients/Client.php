<?php

declare(strict_types=1);

namespace SanadAi\Clients;

final class Client
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $tenantId,
        public readonly string $clientType,
        public readonly string $fullName,
        public readonly ?string $companyName,
        public readonly ?string $phone,
        public readonly ?string $whatsappNumber,
        public readonly ?string $email,
        public readonly string $preferredLanguage,
        public readonly ?string $notes,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }
}