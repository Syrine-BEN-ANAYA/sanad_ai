<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use SanadAi\Http\TooManyRequestsException;
use SanadAi\Support\LocalStore;

/**
 * Brute-force protection for the login endpoint.
 *
 * Two independent counters live in the local file store:
 *  - per (IP + username): slows down targeted password guessing
 *  - per IP: slows down password spraying across usernames
 *
 * Counters expire automatically after the window.
 */
final class LoginThrottle
{
    private const PREFIX = 'sanad:login:';

    public function __construct(
        private readonly LocalStore $store,
        private readonly int $maxPerAccount = 5,
        private readonly int $maxPerIp = 30,
        private readonly int $windowSeconds = 900,
    ) {
    }

    /**
     * Throw HTTP 429 when the caller is currently locked out.
     */
    public function assertAllowed(string $ip, string $username): void
    {
        $account = (int) $this->store->get($this->accountKey($ip, $username));
        $perIp = (int) $this->store->get($this->ipKey($ip));

        if ($account >= $this->maxPerAccount || $perIp >= $this->maxPerIp) {
            $ttl = $this->store->ttl($this->accountKey($ip, $username));

            throw new TooManyRequestsException(
                'Too many login attempts. Please try again later.',
                $ttl > 0 ? $ttl : $this->windowSeconds
            );
        }
    }

    public function recordFailure(string $ip, string $username): void
    {
        $this->store->increment(
            $this->accountKey($ip, $username),
            $this->windowSeconds
        );
        $this->store->increment(
            $this->ipKey($ip),
            $this->windowSeconds
        );
    }

    public function recordSuccess(string $ip, string $username): void
    {
        $this->store->delete($this->accountKey($ip, $username));
    }

    private function accountKey(string $ip, string $username): string
    {
        return self::PREFIX . 'acct:' . hash('sha256', $ip . '|' . strtolower($username));
    }

    private function ipKey(string $ip): string
    {
        return self::PREFIX . 'ip:' . hash('sha256', $ip);
    }
}
