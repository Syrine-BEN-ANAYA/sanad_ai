<?php

declare(strict_types=1);

namespace SanadAi\Services;

final class Service
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $tenantId,
        public readonly ?int $ministryId,
        public readonly string $nameAr,
        public readonly ?string $nameEn,
        public readonly ?string $descriptionAr,
        public readonly ?string $descriptionEn,
        public readonly int $officialFeeBaisa,
        public readonly int $officeFeeBaisa,
        public readonly ?int $targetSlaMinutes,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }
}