<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Http\ExceptionHandler;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Http\Router;

$response = new Response();
$exceptionHandler = new ExceptionHandler($response);

set_exception_handler(
    static fn (\Throwable $e) => $exceptionHandler->handle($e)
);

try {
    $request = new Request();
    $router = new Router();

    (require __DIR__ . '/../routes/web.php')($router);

    $result = $router->dispatch(
        $request->method(),
        $request->path()
    );

    if (is_array($result)) {
        $response->json($result);
    }
} catch (\Throwable $e) {
    $exceptionHandler->handle($e);
}
