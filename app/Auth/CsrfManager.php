<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use RuntimeException;
use SanadAi\Support\LocalStore;

final class CsrfManager
{
    private const TOKEN_PREFIX = 'sanad:csrf:';

    private const TOKEN_TTL = 7200;

    public function __construct(
        private LocalStore $store
    ) {
    }

    /**
     * Generate a new CSRF token.
     *
     * The token itself is returned to the application,
     * while only its SHA-256 hash is stored locally.
     */
    public function generate(string $sessionId): string
    {
        $this->validateSessionId($sessionId);

        $token = bin2hex(
            random_bytes(32)
        );

        $this->store->set(
            self::TOKEN_PREFIX . $sessionId,
            hash('sha256', $token),
            self::TOKEN_TTL
        );

        return $token;
    }

    /**
     * Check whether a CSRF token already exists.
     */
    public function exists(string $sessionId): bool
    {
        if (!$this->isValidSessionId($sessionId)) {
            return false;
        }

        return (bool) $this->store->exists(
            self::TOKEN_PREFIX . $sessionId
        );
    }

    /**
     * Validate a CSRF token.
     */
    public function validate(
        string $sessionId,
        string $token
    ): bool {
        if (
            !$this->isValidSessionId($sessionId)
            || !$this->isValidToken($token)
        ) {
            return false;
        }

        $storedHash = $this->store->get(
            self::TOKEN_PREFIX . $sessionId
        );

        if (
            $storedHash === false
            || !is_string($storedHash)
        ) {
            return false;
        }

        return hash_equals(
            $storedHash,
            hash('sha256', $token)
        );
    }

    /**
     * Revoke the CSRF token.
     */
    public function revoke(string $sessionId): bool
    {
        if (!$this->isValidSessionId($sessionId)) {
            return false;
        }

        return $this->store->delete(
            self::TOKEN_PREFIX . $sessionId
        ) > 0;
    }

    private function validateSessionId(
        string $sessionId
    ): void {
        if (!$this->isValidSessionId($sessionId)) {
            throw new RuntimeException(
                'Invalid session ID.'
            );
        }
    }

    private function isValidSessionId(
        string $sessionId
    ): bool {
        return (bool) preg_match(
            '/^[a-f0-9]{64}$/',
            $sessionId
        );
    }

    private function isValidToken(
        string $token
    ): bool {
        return (bool) preg_match(
            '/^[a-f0-9]{64}$/',
            $token
        );
    }
}