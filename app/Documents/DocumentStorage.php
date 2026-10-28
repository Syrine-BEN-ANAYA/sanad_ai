<?php

declare(strict_types=1);

namespace SanadAi\Documents;

use RuntimeException;

final class DocumentStorage
{
    private const BASE_DIRECTORY = 'storage/uploads';

    public function __construct(
        private readonly string $projectRoot
    ) {
    }

    /**
     * Return the absolute storage directory.
     */
    public function baseDirectory(): string
    {
        return $this->projectRoot
            . DIRECTORY_SEPARATOR
            . self::BASE_DIRECTORY;
    }

    /**
     * Create a tenant-specific directory.
     */
    public function tenantDirectory(int $tenantId): string
    {
        if ($tenantId <= 0) {
            throw new RuntimeException('Invalid tenant ID.');
        }

        $directory = $this->baseDirectory()
            . DIRECTORY_SEPARATOR
            . 'tenant-' . $tenantId;

        $this->ensureDirectory($directory);

        return $directory;
    }

    /**
     * Create a transaction-specific directory.
     */
    public function transactionDirectory(
        int $tenantId,
        int $transactionId
    ): string {
        if ($transactionId <= 0) {
            throw new RuntimeException('Invalid transaction ID.');
        }

        $directory = $this->tenantDirectory($tenantId)
            . DIRECTORY_SEPARATOR
            . 'transaction-' . $transactionId;

        $this->ensureDirectory($directory);

        return $directory;
    }

    /**
     * Generate a safe random physical filename.
     */
    public function generateFilename(
        string $originalName
    ): string {
        $originalName = trim($originalName);

        if ($originalName === '') {
            throw new RuntimeException(
                'Original filename is required.'
            );
        }

        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        if (
            $extension !== ''
            && !preg_match('/^[a-z0-9]{1,10}$/', $extension)
        ) {
            throw new RuntimeException(
                'Invalid file extension.'
            );
        }

        $randomName = bin2hex(random_bytes(32));

        return $extension !== ''
            ? $randomName . '.' . $extension
            : $randomName;
    }

    /**
     * Generate a safe relative storage key.
     */
    public function generateStorageKey(
        int $tenantId,
        int $transactionId,
        string $originalName
    ): string {
        if ($tenantId <= 0 || $transactionId <= 0) {
            throw new RuntimeException(
                'Invalid storage identifiers.'
            );
        }

        $filename = $this->generateFilename($originalName);

        return sprintf(
            'tenant-%d/transaction-%d/%s',
            $tenantId,
            $transactionId,
            $filename
        );
    }

    /**
     * Resolve a storage key to an absolute filesystem path.
     *
     * Path traversal is explicitly rejected.
     */
    public function absolutePath(
        string $storageKey
    ): string {
        $storageKey = trim($storageKey);

        if ($storageKey === '') {
            throw new RuntimeException(
                'Storage key is required.'
            );
        }

        if (
            str_contains($storageKey, "\0")
            || str_contains($storageKey, '..')
            || str_starts_with($storageKey, '/')
            || str_starts_with($storageKey, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $storageKey)
        ) {
            throw new RuntimeException(
                'Invalid storage key.'
            );
        }

        $normalizedKey = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $storageKey
        );

        $base = realpath($this->baseDirectory());

        if ($base === false) {
            $this->ensureDirectory(
                $this->baseDirectory()
            );

            $base = realpath($this->baseDirectory());
        }

        if ($base === false) {
            throw new RuntimeException(
                'Unable to resolve storage directory.'
            );
        }

        $path = $base
            . DIRECTORY_SEPARATOR
            . $normalizedKey;

        $parentDirectory = dirname($path);

        $this->ensureDirectory($parentDirectory);

        $realParent = realpath($parentDirectory);

        if ($realParent === false) {
            throw new RuntimeException(
                'Unable to resolve storage path.'
            );
        }

        $basePrefix = rtrim(
            $base,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR;

        $parentPrefix = rtrim(
            $realParent,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR;

        if (
            !str_starts_with(
                $parentPrefix,
                $basePrefix
            )
        ) {
            throw new RuntimeException(
                'Storage path escapes the storage directory.'
            );
        }

        return $realParent
            . DIRECTORY_SEPARATOR
            . basename($path);
    }

    /**
     * Store raw file contents.
     */
    public function store(
        string $storageKey,
        string $contents
    ): string {
        $path = $this->absolutePath($storageKey);

        $bytes = file_put_contents(
            $path,
            $contents,
            LOCK_EX
        );

        if ($bytes === false) {
            throw new RuntimeException(
                'Unable to store document.'
            );
        }

        return $path;
    }

    /**
     * Delete a stored file.
     */
    public function delete(
        string $storageKey
    ): bool {
        $path = $this->absolutePath($storageKey);

        if (!is_file($path)) {
            return false;
        }

        if (!unlink($path)) {
            throw new RuntimeException(
                'Unable to delete stored document.'
            );
        }

        return true;
    }

    /**
     * Check whether a stored file exists.
     */
    public function exists(
        string $storageKey
    ): bool {
        $path = $this->absolutePath($storageKey);

        return is_file($path);
    }

    private function ensureDirectory(
        string $directory
    ): void {
        if (is_dir($directory)) {
            return;
        }

        if (
            !mkdir(
                $directory,
                0700,
                true
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Unable to create storage directory.'
            );
        }
    }
}