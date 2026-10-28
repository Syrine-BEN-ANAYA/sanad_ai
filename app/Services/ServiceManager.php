<?php

declare(strict_types=1);

namespace SanadAi\Services;

use InvalidArgumentException;
use SanadAi\Http\NotFoundException;
use SanadAi\Support\Uuid;

final class ServiceManager
{
    public function __construct(
        private readonly ServiceRepository $repository,
        private readonly MinistryRepository $ministryRepository,
    ) {
    }

    public function create(
        int $tenantId,
        ?int $ministryId,
        string $nameAr,
        ?string $nameEn,
        ?string $descriptionAr,
        ?string $descriptionEn,
        int $officialFeeBaisa,
        int $officeFeeBaisa,
        ?int $targetSlaMinutes,
    ): Service {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException(
                'Invalid tenant.'
            );
        }

        $nameAr = trim($nameAr);
        $nameEn = $this->normalizeNullable($nameEn);
        $descriptionAr = $this->normalizeNullable($descriptionAr);
        $descriptionEn = $this->normalizeNullable($descriptionEn);

        $this->validateText(
            $nameAr,
            'Arabic service name',
            200,
            true
        );

        $this->validateText(
            $nameEn,
            'English service name',
            200
        );

        $this->validateText(
            $descriptionAr,
            'Arabic description',
            5000
        );

        $this->validateText(
            $descriptionEn,
            'English description',
            5000
        );

        $this->validateFees(
            $officialFeeBaisa,
            $officeFeeBaisa
        );

        $this->validateSla($targetSlaMinutes);

        $this->validateMinistry($ministryId);

        return $this->repository->create(
            tenantId: $tenantId,
            publicId: $this->generatePublicId(),
            ministryId: $ministryId,
            nameAr: $nameAr,
            nameEn: $nameEn,
            descriptionAr: $descriptionAr,
            descriptionEn: $descriptionEn,
            officialFeeBaisa: $officialFeeBaisa,
            officeFeeBaisa: $officeFeeBaisa,
            targetSlaMinutes: $targetSlaMinutes,
        );
    }

    public function find(int $tenantId, string $publicId): ?Service
    {
        return $this->repository->findByPublicId($tenantId, trim($publicId));
    }

    /**
     * @return array{items: list<Service>, total: int}
     */
    public function search(
        int $tenantId,
        ?string $query,
        ?string $status,
        int $limit,
        int $offset,
    ): array {
        return [
            'items' => $this->repository->search($tenantId, $query, $status, $limit, $offset),
            'total' => $this->repository->count($tenantId, $query, $status),
        ];
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
    ): Service {
        if ($this->repository->findByPublicId($tenantId, $publicId) === null) {
            throw new NotFoundException('Service not found.');
        }

        $this->validateForUpdate(
            ministryId: $ministryId,
            nameAr: $nameAr,
            nameEn: $nameEn,
            descriptionAr: $descriptionAr,
            descriptionEn: $descriptionEn,
            officialFeeBaisa: $officialFeeBaisa,
            officeFeeBaisa: $officeFeeBaisa,
            targetSlaMinutes: $targetSlaMinutes,
            status: $status,
        );

        $this->repository->update(
            tenantId: $tenantId,
            publicId: $publicId,
            ministryId: $ministryId,
            nameAr: trim($nameAr),
            nameEn: $this->normalizeNullable($nameEn),
            descriptionAr: $this->normalizeNullable($descriptionAr),
            descriptionEn: $this->normalizeNullable($descriptionEn),
            officialFeeBaisa: $officialFeeBaisa,
            officeFeeBaisa: $officeFeeBaisa,
            targetSlaMinutes: $targetSlaMinutes,
            status: $status,
        );

        $service = $this->repository->findByPublicId($tenantId, $publicId);

        if ($service === null) {
            throw new NotFoundException('Service not found.');
        }

        return $service;
    }

    public function validateForUpdate(
        ?int $ministryId,
        string $nameAr,
        ?string $nameEn,
        ?string $descriptionAr,
        ?string $descriptionEn,
        int $officialFeeBaisa,
        int $officeFeeBaisa,
        ?int $targetSlaMinutes,
        string $status,
    ): void {
        $nameAr = trim($nameAr);
        $nameEn = $this->normalizeNullable($nameEn);
        $descriptionAr = $this->normalizeNullable($descriptionAr);
        $descriptionEn = $this->normalizeNullable($descriptionEn);

        $this->validateText(
            $nameAr,
            'Arabic service name',
            200,
            true
        );

        $this->validateText(
            $nameEn,
            'English service name',
            200
        );

        $this->validateText(
            $descriptionAr,
            'Arabic description',
            5000
        );

        $this->validateText(
            $descriptionEn,
            'English description',
            5000
        );

        $this->validateFees(
            $officialFeeBaisa,
            $officeFeeBaisa
        );

        $this->validateSla($targetSlaMinutes);

        $this->validateMinistry($ministryId);

        if (!in_array(
            $status,
            ['active', 'inactive'],
            true
        )) {
            throw new InvalidArgumentException(
                'Invalid service status.'
            );
        }
    }
public function delete(
    int $tenantId,
    string $publicId
): bool {
    if ($tenantId <= 0) {
        throw new InvalidArgumentException(
            'Invalid tenant.'
        );
    }

    $publicId = trim($publicId);

    if ($publicId === '') {
        throw new InvalidArgumentException(
            'Invalid service ID.'
        );
    }

    return $this->repository->deactivate(
        tenantId: $tenantId,
        publicId: $publicId,
    );
}
    private function validateMinistry(
        ?int $ministryId
    ): void {
        if ($ministryId === null) {
            return;
        }

        if ($ministryId <= 0) {
            throw new InvalidArgumentException(
                'Invalid ministry.'
            );
        }

        if (!$this->ministryRepository->exists($ministryId)) {
            throw new InvalidArgumentException(
                'Ministry not found or inactive.'
            );
        }
    }

    private function validateFees(
        int $officialFeeBaisa,
        int $officeFeeBaisa
    ): void {
        if ($officialFeeBaisa < 0) {
            throw new InvalidArgumentException(
                'Official fee cannot be negative.'
            );
        }

        if ($officeFeeBaisa < 0) {
            throw new InvalidArgumentException(
                'Office fee cannot be negative.'
            );
        }
    }

    private function validateSla(
        ?int $targetSlaMinutes
    ): void {
        if (
            $targetSlaMinutes !== null
            && $targetSlaMinutes < 0
        ) {
            throw new InvalidArgumentException(
                'Target SLA cannot be negative.'
            );
        }
    }

    private function validateText(
        ?string $value,
        string $field,
        int $maxLength,
        bool $required = false,
    ): void {
        if ($value === null) {
            if ($required) {
                throw new InvalidArgumentException(
                    "{$field} is required."
                );
            }

            return;
        }

        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException(
                    "{$field} is required."
                );
            }

            return;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(
                "{$field} exceeds the maximum length."
            );
        }
    }

    private function normalizeNullable(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function generatePublicId(): string
    {
        return Uuid::v4();
    }
}
