<?php

declare(strict_types=1);

namespace SanadAi\Support;

use RuntimeException;

final class LocalStore
{
    private const CLEANUP_INTERVAL = 300;

    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory
            ?? dirname(__DIR__, 2)
                . DIRECTORY_SEPARATOR . 'storage'
                . DIRECTORY_SEPARATOR . 'cache'
                . DIRECTORY_SEPARATOR . 'auth-state';
    }

    public function get(string $key): string|false
    {
        return $this->synchronized(
            fn (): string|false => $this->readEntry($key)['value'] ?? false
        );
    }

    public function set(string $key, string $value, int $ttl): bool
    {
        if ($ttl <= 0) {
            throw new RuntimeException('Cache TTL must be positive.');
        }

        return $this->synchronized(
            fn (): bool => $this->writeEntry($key, $value, time() + $ttl)
        );
    }

    public function delete(string $key): int
    {
        return $this->synchronized(function () use ($key): int {
            if ($this->readEntry($key) === null) {
                return 0;
            }

            $path = $this->entryPath($key);

            if (!unlink($path)) {
                throw new RuntimeException('Unable to delete local cache entry.');
            }

            return 1;
        });
    }

    public function exists(string $key): int
    {
        return $this->synchronized(
            fn (): int => $this->readEntry($key) === null ? 0 : 1
        );
    }

    public function ttl(string $key): int
    {
        return $this->synchronized(function () use ($key): int {
            $entry = $this->readEntry($key);

            if ($entry === null) {
                return -2;
            }

            return max(0, $entry['expires_at'] - time());
        });
    }

    public function increment(string $key, int $ttl): int
    {
        if ($ttl <= 0) {
            throw new RuntimeException('Cache TTL must be positive.');
        }

        return $this->synchronized(function () use ($key, $ttl): int {
            $entry = $this->readEntry($key);
            $value = $entry['value'] ?? '0';

            if (!ctype_digit($value)) {
                throw new RuntimeException('Local cache counter is invalid.');
            }

            $count = (int) $value + 1;
            $expiresAt = $entry['expires_at'] ?? time() + $ttl;

            $this->writeEntry($key, (string) $count, $expiresAt);

            return $count;
        });
    }

    private function synchronized(callable $operation): mixed
    {
        $this->ensureDirectory();
        $lock = fopen($this->directory . DIRECTORY_SEPARATOR . '.lock', 'c+b');

        if ($lock === false) {
            throw new RuntimeException('Unable to open local cache lock.');
        }

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('Unable to lock local cache.');
        }

        try {
            if (!chmod($this->directory . DIRECTORY_SEPARATOR . '.lock', 0600)) {
                throw new RuntimeException('Unable to secure local cache lock.');
            }

            $this->removeExpiredEntries();

            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)
            && !mkdir($this->directory, 0700, true)
            && !is_dir($this->directory)
        ) {
            throw new RuntimeException('Unable to create local cache directory.');
        }

        if (!chmod($this->directory, 0700)) {
            throw new RuntimeException('Unable to secure local cache directory.');
        }
    }

    private function removeExpiredEntries(): void
    {
        $marker = $this->directory . DIRECTORY_SEPARATOR . '.last-cleanup';
        clearstatcache(true, $marker);
        $lastCleanup = is_file($marker) ? filemtime($marker) : false;

        if ($lastCleanup === false && is_file($marker)) {
            throw new RuntimeException('Unable to read local cache cleanup marker.');
        }

        if ($lastCleanup !== false && time() - $lastCleanup < self::CLEANUP_INTERVAL) {
            return;
        }

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('Unable to read local cache entry.');
            }

            $entry = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($entry)
                && isset($entry['expires_at'])
                && is_int($entry['expires_at'])
                && $entry['expires_at'] <= time()
                && !unlink($path)
            ) {
                throw new RuntimeException('Unable to remove expired local cache entry.');
            }
        }

        if (!touch($marker)) {
            throw new RuntimeException('Unable to update local cache cleanup marker.');
        }

        if (!chmod($marker, 0600)) {
            throw new RuntimeException('Unable to secure local cache cleanup marker.');
        }
    }

    /**
     * @return array{value: string, expires_at: int}|null
     */
    private function readEntry(string $key): ?array
    {
        $path = $this->entryPath($key);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read local cache entry.');
        }

        $entry = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($entry)
            || !is_string($entry['value'] ?? null)
            || !is_int($entry['expires_at'] ?? null)
        ) {
            throw new RuntimeException('Local cache entry is invalid.');
        }

        if ($entry['expires_at'] <= time()) {
            if (!unlink($path)) {
                throw new RuntimeException('Unable to remove expired local cache entry.');
            }

            return null;
        }

        return $entry;
    }

    private function writeEntry(string $key, string $value, int $expiresAt): bool
    {
        $path = $this->entryPath($key);
        $contents = json_encode(
            ['value' => $value, 'expires_at' => $expiresAt],
            JSON_THROW_ON_ERROR
        );
        $written = file_put_contents($path, $contents, LOCK_EX);

        if ($written !== strlen($contents)) {
            throw new RuntimeException('Unable to write local cache entry.');
        }

        if (!chmod($path, 0600)) {
            throw new RuntimeException('Unable to secure local cache entry.');
        }

        return true;
    }

    private function entryPath(string $key): string
    {
        return $this->directory
            . DIRECTORY_SEPARATOR
            . hash('sha256', $key)
            . '.json';
    }
}
