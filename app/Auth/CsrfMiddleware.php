<?php declare(strict_types=1); namespace SanadAi\Auth;

use SanadAi\Http\ForbiddenException;
use SanadAi\Http\UnauthorizedException;

final class CsrfMiddleware
{
    public function __construct(
        private CsrfManager $csrfManager,
        private CookieManager $cookies
    ) {
    }

    /**
     * Validate the CSRF token sent by the client.
     *
     * The token must be sent through:
     *
     * X-CSRF-Token: <token>
     */
    public function validate(): void
    {
        $sessionId = $this->cookies->get();

        if ($sessionId === null) {
            throw new UnauthorizedException(
                'Authentication required.'
            );
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!is_string($token) || $token === '') {
            throw new ForbiddenException(
                'CSRF token required.'
            );
        }

        if (
            !$this->csrfManager->validate(
                $sessionId,
                $token
            )
        ) {
            throw new ForbiddenException(
                'Invalid CSRF token.'
            );
        }
    }
}
