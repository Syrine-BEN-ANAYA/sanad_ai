<?php

declare(strict_types=1);

namespace SanadAi\Http;

final class Response
{
    public function json(
        array $data,
        int $statusCode = 200
    ): void {
        http_response_code($statusCode);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        header('Cache-Control: no-store');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );
    }

    public function html(
        string $content,
        int $statusCode = 200
    ): void {
        http_response_code($statusCode);

        header(
            'Content-Type: text/html; charset=utf-8'
        );

        echo $content;
    }

    public function noContent(): void
    {
        http_response_code(204);
    }
}