<?php

declare(strict_types=1);

namespace SanadAi\Auth;


use RuntimeException;
use SanadAi\Support\LocalStore;

final class SessionManager
{
    private const SESSION_PREFIX = 'sanad:session:';

    private const DEFAULT_TTL = 7200; // 2 heures

    public function __construct(
        private LocalStore $store,
        private int $ttl = self::DEFAULT_TTL
    ) {
        if ($this->ttl <= 0) {
            throw new RuntimeException(
                'Invalid session TTL.'
            );
        }
    }

    /**
     * Create a new session.
     */
    public function create(
        int $userId,
        ?int $tenantId,
        string $ipAddress,
        string $userAgent
    ): string {
        if ($userId <= 0) {
            throw new RuntimeException(
                'Invalid user ID.'
            );
        }

        if (
            $tenantId !== null
            && $tenantId <= 0
        ) {
            throw new RuntimeException(
                'Invalid tenant ID.'
            );
        }

        $sessionId = bin2hex(
            random_bytes(32)
        );

        $sessionData = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => time(),
            'last_activity' => time(),
            'csrf_token' => null,
        ];

        $this->write(
            $sessionId,
            $sessionData
        );

        return $sessionId;
    }

    /**
     * Store the CSRF token inside the server-side session.
     *
     * The token is returned to the client only through the
     * authentication response. It is never stored in MySQL.
     */
    public function setCsrfToken(
        string $sessionId,
        string $csrfToken
    ): bool {
        if (
            !$this->isValidSessionId($sessionId)
            || !$this->isValidCsrfToken($csrfToken)
        ) {
            return false;
        }

        $session = $this->get($sessionId);

        if ($session === null) {
            return false;
        }

        $session['csrf_token'] = $csrfToken;

        return $this->write(
            $sessionId,
            $session
        );
    }

    /**
     * Retrieve the CSRF token stored in the session.
     */
    public function getCsrfToken(
        string $sessionId
    ): ?string {
        if (!$this->isValidSessionId($sessionId)) {
            return null;
        }

        $session = $this->get($sessionId);

        if ($session === null) {
            return null;
        }

        $token = $session['csrf_token'] ?? null;

        if (
            !is_string($token)
            || !$this->isValidCsrfToken($token)
        ) {
            return null;
        }

        return $token;
    }

    /**
     * Retrieve a session.
     */
    public function get(
        string $sessionId
    ): ?array {
        if (!$this->isValidSessionId($sessionId)) {
            return null;
        }

        $data = $this->store->get(
            $this->key($sessionId)
        );

        if (
            $data === false
            || $data === null
        ) {
            return null;
        }

        $session = json_decode(
            $data,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($session)) {
            return null;
        }

        return $session;
    }

    /**
     * Refresh session expiration and activity.
     */
    public function touch(
        string $sessionId
    ): bool {
        if (!$this->isValidSessionId($sessionId)) {
            return false;
        }

        $session = $this->get($sessionId);

        if ($session === null) {
            return false;
        }

        $session['last_activity'] = time();

        return $this->write(
            $sessionId,
            $session
        );
    }

    /**
     * Destroy a session.
     */
    public function destroy(
        string $sessionId
    ): bool {
        if (!$this->isValidSessionId($sessionId)) {
            return false;
        }

        return $this->store->delete(
            $this->key($sessionId)
        ) > 0;
    }

    /**
     * Check whether a session exists.
     */
    public function exists(
        string $sessionId
    ): bool {
        if (!$this->isValidSessionId($sessionId)) {
            return false;
        }

        return (bool) $this->store->exists(
            $this->key($sessionId)
        );
    }

    /**
     * Get remaining TTL.
     */
    public function ttl(
        string $sessionId
    ): int {
        if (!$this->isValidSessionId($sessionId)) {
            return -2;
        }

        return $this->store->ttl(
            $this->key($sessionId)
        );
    }

    /**
     * Write a session and refresh its TTL.
     */
    private function write(
        string $sessionId,
        array $session
    ): bool {
        if (!$this->isValidSessionId($sessionId)) {
            return false;
        }

        $result = $this->store->set(
            $this->key($sessionId),
            json_encode(
                $session,
                JSON_THROW_ON_ERROR
            ),
            $this->ttl
        );

        return $result === true;
    }

    private function key(
        string $sessionId
    ): string {
        return self::SESSION_PREFIX . $sessionId;
    }

    private function isValidSessionId(
        string $sessionId
    ): bool {
        return (bool) preg_match(
            '/^[a-f0-9]{64}$/',
            $sessionId
        );
    }

    private function isValidCsrfToken(
        string $csrfToken
    ): bool {
        return (bool) preg_match(
            '/^[a-f0-9]{64}$/',
            $csrfToken
        );
    }
}
