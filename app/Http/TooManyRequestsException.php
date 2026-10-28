<?php

declare(strict_types=1);

namespace SanadAi\Http;

final class TooManyRequestsException extends HttpException
{
    public function __construct(
        string $message = 'Too many requests.',
        private readonly int $retryAfter = 60
    ) {
        parent::__construct($message, 429);
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
