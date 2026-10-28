<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use RuntimeException;

final class SessionContext
{
    private ?array $user = null;

    private ?string $csrfToken = null;

    public function setUser(array $user): void
    {
        if (
            !isset($user['id'])
            || !is_int($user['id'])
            || $user['id'] <= 0
        ) {
            throw new RuntimeException(
                'Invalid authenticated user.'
            );
        }

        $this->user = $user;
    }

    public function setCsrfToken(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new RuntimeException(
                'Invalid CSRF token.'
            );
        }

        $this->csrfToken = $token;
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    public function user(): array
    {
        if ($this->user === null) {
            throw new RuntimeException(
                'No authenticated user.'
            );
        }

        return $this->user;
    }

    public function userId(): int
    {
        return (int) $this->user()['id'];
    }

    public function tenantId(): ?int
    {
        $tenantId = $this->user()['tenant_id'] ?? null;

        return $tenantId !== null
            ? (int) $tenantId
            : null;
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) (
            $this->user()['is_platform_admin'] ?? false
        );
    }

    public function csrfToken(): string
    {
        if ($this->csrfToken === null) {
            throw new RuntimeException(
                'CSRF token has not been initialized.'
            );
        }

        return $this->csrfToken;
    }

    public function hasCsrfToken(): bool
    {
        return $this->csrfToken !== null;
    }

    public function clear(): void
    {
        $this->user = null;
        $this->csrfToken = null;
    }
}