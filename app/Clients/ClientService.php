<?php

declare(strict_types=1);

namespace SanadAi\Clients;

use InvalidArgumentException;

final class ClientService
{
    public function __construct(
        private readonly ClientRepository $repository,
    ) {
    }

    public function create(
        int $tenantId,
        string $clientType,
        string $fullName,
        ?string $companyName = null,
        ?string $phone = null,
        ?string $whatsappNumber = null,
        ?string $email = null,
        string $preferredLanguage = 'ar',
        ?string $notes = null,
    ): Client {
        $this->validateTenantId($tenantId);

        $clientType = strtolower(trim($clientType));
        $fullName = trim($fullName);
        $companyName = $this->nullableTrim($companyName);
        $phone = $this->nullableTrim($phone);
        $whatsappNumber = $this->nullableTrim($whatsappNumber);
        $email = $this->nullableTrim($email);
        $preferredLanguage = strtolower(trim($preferredLanguage));
        $notes = $this->nullableTrim($notes);

        $this->validateClientData(
            clientType: $clientType,
            fullName: $fullName,
            companyName: $companyName,
            phone: $phone,
            whatsappNumber: $whatsappNumber,
            email: $email,
            preferredLanguage: $preferredLanguage,
            notes: $notes,
        );

        $publicId = $this->generatePublicId();

        return $this->repository->create(
            tenantId: $tenantId,
            publicId: $publicId,
            clientType: $clientType,
            fullName: $fullName,
            companyName: $companyName,
            phone: $phone,
            whatsappNumber: $whatsappNumber,
            email: $email,
            preferredLanguage: $preferredLanguage,
            notes: $notes,
        );
    }

    public function validateForUpdate(
        string $clientType,
        string $fullName,
        ?string $companyName,
        ?string $phone,
        ?string $whatsappNumber,
        ?string $email,
        string $preferredLanguage,
        ?string $notes,
        string $status,
    ): void {
        $clientType = strtolower(trim($clientType));
        $fullName = trim($fullName);
        $companyName = $this->nullableTrim($companyName);
        $phone = $this->nullableTrim($phone);
        $whatsappNumber = $this->nullableTrim($whatsappNumber);
        $email = $this->nullableTrim($email);
        $preferredLanguage = strtolower(trim($preferredLanguage));
        $notes = $this->nullableTrim($notes);
        $status = strtolower(trim($status));

        $this->validateClientData(
            clientType: $clientType,
            fullName: $fullName,
            companyName: $companyName,
            phone: $phone,
            whatsappNumber: $whatsappNumber,
            email: $email,
            preferredLanguage: $preferredLanguage,
            notes: $notes,
        );

        if (!in_array(
            $status,
            ['active', 'inactive'],
            true
        )) {
            throw new InvalidArgumentException(
                'Invalid client status.'
            );
        }
    }

    private function validateClientData(
        string $clientType,
        string $fullName,
        ?string $companyName,
        ?string $phone,
        ?string $whatsappNumber,
        ?string $email,
        string $preferredLanguage,
        ?string $notes,
    ): void {
        if (!in_array(
            $clientType,
            ['person', 'company'],
            true
        )) {
            throw new InvalidArgumentException(
                'Invalid client type.'
            );
        }

        if ($fullName === '') {
            throw new InvalidArgumentException(
                'Full name is required.'
            );
        }

        if (mb_strlen($fullName) > 150) {
            throw new InvalidArgumentException(
                'Full name is too long.'
            );
        }

        if (
            $clientType === 'company'
            && $companyName === null
        ) {
            throw new InvalidArgumentException(
                'Company name is required for company clients.'
            );
        }

        if (
            $companyName !== null
            && mb_strlen($companyName) > 200
        ) {
            throw new InvalidArgumentException(
                'Company name is too long.'
            );
        }

        if (
            $phone !== null
            && mb_strlen($phone) > 30
        ) {
            throw new InvalidArgumentException(
                'Phone number is too long.'
            );
        }

        if (
            $whatsappNumber !== null
            && mb_strlen($whatsappNumber) > 30
        ) {
            throw new InvalidArgumentException(
                'WhatsApp number is too long.'
            );
        }

        if ($email !== null) {
            if (mb_strlen($email) > 190) {
                throw new InvalidArgumentException(
                    'Email address is too long.'
                );
            }

            if (!filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )) {
                throw new InvalidArgumentException(
                    'Invalid email address.'
                );
            }
        }

        if (!in_array(
            $preferredLanguage,
            ['ar', 'en'],
            true
        )) {
            throw new InvalidArgumentException(
                'Invalid preferred language.'
            );
        }

        if (
            $notes !== null
            && mb_strlen($notes) > 5000
        ) {
            throw new InvalidArgumentException(
                'Notes are too long.'
            );
        }
    }

    private function validateTenantId(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException(
                'Invalid tenant ID.'
            );
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function generatePublicId(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }
}