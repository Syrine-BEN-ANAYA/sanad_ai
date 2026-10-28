<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$config = require __DIR__ . '/../config/database.php';

$expected = [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'sanad_ai',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

foreach ($expected as $key => $value) {
    if (($config[$key] ?? null) !== $value) {
        throw new RuntimeException(
            "Database configuration failed: {$key}"
        );
    }
}

echo "Driver: OK\n";
echo "Host: OK\n";
echo "Port: OK\n";
echo "Database: OK\n";
echo "Username: OK\n";
echo "Password: OK\n";
echo "Charset: OK\n";
echo "Collation: OK\n";
echo "Database configuration test PASSED.\n";