<?php

declare(strict_types=1);

namespace SanadAi\Http;

use RuntimeException;

/**
 * Exception carrying an HTTP status code.
 *
 * The message is always safe to expose to API clients.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
