<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SanadAi\Http\Router;

echo "=== Router Dynamic Route Test ===" . PHP_EOL;

$router = new Router();

$router->get(
    '/api/clients',
    function (): string {
        return 'CLIENTS_LIST';
    }
);

$router->get(
    '/api/clients/{publicId}',
    function (string $publicId): string {
        return 'CLIENT_' . $publicId;
    }
);

echo PHP_EOL . "1. Testing static route..." . PHP_EOL;

$result = $router->dispatch(
    'GET',
    '/api/clients'
);

if ($result === 'CLIENTS_LIST') {
    echo "PASS: Static route works." . PHP_EOL;
} else {
    echo "FAIL: Static route returned unexpected result." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "2. Testing dynamic route..." . PHP_EOL;

$result = $router->dispatch(
    'GET',
    '/api/clients/abc-123
'
);

if ($result === 'CLIENT_abc-123') {
    echo "PASS: Dynamic route works." . PHP_EOL;
} else {
    echo "FAIL: Dynamic route returned unexpected result." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "3. Testing encoded parameter..." . PHP_EOL;

$result = $router->dispatch(
    'GET',
    '/api/clients/client%20123'
);

if ($result === 'CLIENT_client 123') {
    echo "PASS: URL decoding works." . PHP_EOL;
} else {
    echo "FAIL: URL decoding failed." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "4. Testing missing route..." . PHP_EOL;

try {
    $router->dispatch(
        'GET',
        '/api/unknown'
    );

    echo "FAIL: Unknown route was accepted." . PHP_EOL;
    exit(1);
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'Route not found.') {
        echo "PASS: Unknown route rejected." . PHP_EOL;
    } else {
        echo "FAIL: Unexpected exception." . PHP_EOL;
        echo $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

echo PHP_EOL;
echo "=== ALL ROUTER TESTS PASSED ===" . PHP_EOL;
