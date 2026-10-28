<?php

declare(strict_types=1);

namespace SanadAi\Tenancy;

use RuntimeException;
use SanadAi\Http\ForbiddenException;

final class TenantContext
{
    private ?int $tenantId = null;

    /**
     * Définit le tenant courant.
     */
    public function set(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new RuntimeException('Invalid tenant ID.');
        }

        $this->tenantId = $tenantId;
    }

    /**
     * Retourne le tenant courant.
     */
    public function get(): int
    {
        if ($this->tenantId === null) {
            throw new RuntimeException('Tenant context has not been initialized.');
        }

        return $this->tenantId;
    }

    /**
     * Current tenant ID, or null when none is set.
     */
    public function id(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Current tenant ID, or HTTP 403 when the user has no tenant
     * (e.g. a platform admin calling a tenant-scoped endpoint).
     */
    public function require(): int
    {
        if ($this->tenantId === null) {
            throw new ForbiddenException('Tenant context is required.');
        }

        return $this->tenantId;
    }

    /**
     * Vérifie si un tenant est défini.
     */
    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Vérifie qu'un ID appartient au tenant courant.
     */
    public function owns(int $tenantId): bool
    {
        return $this->has() && $this->tenantId === $tenantId;
    }

    /**
     * Réinitialise le contexte.
     */
    public function clear(): void
    {
        $this->tenantId = null;
    }
}