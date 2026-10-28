<?php

declare(strict_types=1);

namespace SanadAi\Auth;

final class CookieManager
{
    private const DEFAULT_NAME = 'SANAD_SESSION';

    public function __construct(
        private string $name = self::DEFAULT_NAME,
        private bool $secure = false
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException(
                'Cookie name cannot be empty.'
            );
        }
    }

    /**
     * Set the authentication session cookie.
     */
    public function set(string $sessionId): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $sessionId)) {
            throw new \InvalidArgumentException(
                'Invalid session ID.'
            );
        }

        setcookie(
            $this->name,
            $sessionId,
            [
                'expires' => 0,
                'path' => '/',
                'secure' => $this->secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Get the authentication session cookie.
     */
    public function get(): ?string
    {
        $value = $_COOKIE[$this->name] ?? null;

        if (!is_string($value)) {
            return null;
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Delete the authentication session cookie.
     */
    public function clear(): void
    {
        setcookie(
            $this->name,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $this->secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        unset($_COOKIE[$this->name]);
    }

    public function name(): string
    {
        return $this->name;
    }
}