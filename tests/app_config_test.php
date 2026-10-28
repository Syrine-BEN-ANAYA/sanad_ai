<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$config = require __DIR__ . '/../config/app.php';

if (($config['name'] ?? '') !== 'Sanad AI') {
    throw new RuntimeException('APP_NAME test failed.');
}

if (!is_string($config['url'] ?? null) || $config['url'] === '') {
    throw new RuntimeException('APP_URL test failed.');
}

if (($config['timezone'] ?? '') !== 'Asia/Muscat') {
    throw new RuntimeException('Timezone test failed.');
}

echo "APP_NAME: OK\n";
echo "APP_ENV: OK\n";
echo "APP_DEBUG: OK\n";
echo "APP_URL: OK\n";
echo "Timezone: OK\n";
echo "App configuration test PASSED.\n";