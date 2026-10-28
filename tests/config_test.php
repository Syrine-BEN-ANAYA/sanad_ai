<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

echo 'APP_NAME: ' . ($_ENV['APP_NAME'] ?? 'NOT FOUND') . PHP_EOL;
echo 'DB_HOST: ' . ($_ENV['DB_HOST'] ?? 'NOT FOUND') . PHP_EOL;
echo 'DB_NAME: ' . ($_ENV['DB_NAME'] ?? 'NOT FOUND') . PHP_EOL;
echo 'DB_PORT: ' . ($_ENV['DB_PORT'] ?? 'NOT FOUND') . PHP_EOL;