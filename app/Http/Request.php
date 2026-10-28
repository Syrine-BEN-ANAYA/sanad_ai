<?php

declare(strict_types=1);

namespace SanadAi\Http;

final class Request
{
    public function method(): string
    {
        return strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );
    }

   public function path(): string
{
    $path = parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    );

    if (!is_string($path) || $path === '') {
        return '/';
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    if (is_string($scriptName) && $scriptName !== '') {
        $basePath = rtrim(
            str_replace(
                '\\',
                '/',
                dirname($scriptName)
            ),
            '/'
        );

        if (
            $basePath !== ''
            && $basePath !== '/'
            && str_starts_with(
                $path,
                $basePath . '/'
            )
        ) {
            $path = substr(
                $path,
                strlen($basePath)
            );
        }
    }

    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }

    return $path;
}

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * Get a query-string parameter.
     */
    public function query(
        string $key,
        mixed $default = null
    ): mixed {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get all query-string parameters.
     *
     * @return array<string, mixed>
     */
    public function queryAll(): array
    {
        return $_GET;
    }

    /**
     * Decode a JSON request body.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}