<?php

declare(strict_types=1);

namespace SanadAi\Http;

use InvalidArgumentException;

/**
 * Strict, typed access to decoded JSON bodies and query strings.
 *
 * Anything with the wrong type is rejected with a 422 instead of being
 * silently cast.
 */
final class Input
{
    /**
     * @param array<string, mixed> $data
     */
    public static function string(array $data, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }

        if (!is_string($data[$key])) {
            throw new InvalidArgumentException("Field '{$key}' must be a string.");
        }

        return $data[$key];
    }

    /**
     * Empty strings and null both become null.
     *
     * @param array<string, mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = trim(self::string($data, $key));

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return $default;
        }

        $value = $data[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException("Field '{$key}' must be an integer.");
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        return self::int($data, $key);
    }

    /**
     * Pagination: ?limit= (1..100, default 25) and ?offset= (>= 0).
     *
     * @return array{0: int, 1: int}
     */
    public static function pagination(Request $request): array
    {
        $limit = (int) $request->query('limit', 25);
        $offset = (int) $request->query('offset', 0);

        return [max(1, min($limit, 100)), max(0, $offset)];
    }

    public static function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 100);
    }
}
