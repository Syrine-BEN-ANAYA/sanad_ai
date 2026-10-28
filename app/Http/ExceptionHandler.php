<?php

declare(strict_types=1);

namespace SanadAi\Http;

use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Single place where every uncaught exception becomes an HTTP response.
 *
 *  - HttpException            -> its own status code (message is safe)
 *  - InvalidArgumentException -> 422 (validation error, message is safe)
 *  - JsonException            -> 400
 *  - anything else            -> 500, logged, generic message
 *                                (real message only when APP_DEBUG=true)
 */
final class ExceptionHandler
{
    public function __construct(
        private Response $response
    ) {
    }

    public function handle(Throwable $exception): void
    {
        if ($exception instanceof HttpException) {
            if ($exception instanceof TooManyRequestsException) {
                header('Retry-After: ' . $exception->retryAfter());
            }

            $this->send($exception->statusCode(), $exception->getMessage());

            return;
        }

        if ($exception instanceof InvalidArgumentException) {
            $this->send(422, $exception->getMessage());

            return;
        }

        if ($exception instanceof JsonException) {
            $this->send(400, 'Malformed JSON body.');

            return;
        }

        $this->log($exception);

        $debug = defined('APP_DEBUG') && APP_DEBUG === true;

        $this->send(
            500,
            $debug ? $exception->getMessage() : 'Internal server error.'
        );
    }

    private function send(int $status, string $message): void
    {
        if (!headers_sent()) {
            $this->response->json(
                ['success' => false, 'message' => $message],
                $status
            );
        }
    }

    private function log(Throwable $exception): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('c'),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        $dir = dirname(__DIR__, 2) . '/storage/logs';

        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);

            return;
        }

        error_log($line);
    }
}
