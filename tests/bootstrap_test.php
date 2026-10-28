<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (!isset($appConfig) || !is_array($appConfig)) {
    throw new RuntimeException(
        'App configuration was not loaded.'
    );
}

if (($appConfig['name'] ?? '') !== 'Sanad AI') {
    throw new RuntimeException(
        'App name configuration failed.'
    );
}

if (date_default_timezone_get() !== 'Asia/Muscat') {
    throw new RuntimeException(
        'Timezone configuration failed.'
    );
}

echo "Bootstrap loaded: OK\n";
echo "App configuration loaded: OK\n";
echo "Timezone: OK\n";
echo "Bootstrap test PASSED.\n";