<?php

declare(strict_types=1);

namespace SanadAi\Http;

final class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Authentication required.')
    {
        parent::__construct($message, 401);
    }
}
