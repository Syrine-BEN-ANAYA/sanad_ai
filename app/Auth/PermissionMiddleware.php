<?php

declare(strict_types=1);

namespace SanadAi\Auth;

use RuntimeException;
use SanadAi\Http\UnauthorizedException;

final class PermissionMiddleware
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly SessionContext $sessionContext,
        private readonly string $permission,
    ) {
        if (trim($this->permission) === '') {
            throw new RuntimeException(
                'Permission cannot be empty.'
            );
        }
    }

    public function __invoke(): void
    {
        if (!$this->sessionContext->isAuthenticated()) {
            throw new UnauthorizedException(
                'Authentication required.'
            );
        }

        $this->authorization->require(
            $this->sessionContext->userId(),
            $this->permission
        );
    }
}
