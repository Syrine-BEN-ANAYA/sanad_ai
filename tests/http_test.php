<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Http\Request;
use SanadAi\Http\Response;

$request = new Request();
$response = new Response();

$tests = [];

$tests['Request method'] =
    in_array($request->method(), ['GET', 'POST', 'PUT', 'DELETE'], true);

$tests['Request path'] =
    $request->path() !== '';

ob_start();

$response->json([
    'success' => true,
    'test' => 'http',
]);

$output = ob_get_clean();

$data = json_decode($output, true);

$tests['Response JSON'] =
    is_array($data)
    && ($data['success'] ?? false) === true
    && ($data['test'] ?? '') === 'http';

foreach ($tests as $name => $passed) {
    echo $name . ': ' . ($passed ? 'OK' : 'FAILED') . PHP_EOL;
}

if (in_array(false, $tests, true)) {
    throw new RuntimeException(
        'HTTP classes test FAILED.'
    );
}

echo "HTTP classes test PASSED." . PHP_EOL;