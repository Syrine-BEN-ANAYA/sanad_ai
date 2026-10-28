<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Config\Database;

try {
    $pdo = Database::connection();

    $result = $pdo->query('SELECT DATABASE() AS db, VERSION() AS version')
                  ->fetch();

    echo 'Database connection OK' . PHP_EOL;
    echo 'Database: ' . $result['db'] . PHP_EOL;
    echo 'Server version: ' . $result['version'] . PHP_EOL;

} catch (Throwable $e) {
    echo 'Database ERROR: ' . $e->getMessage() . PHP_EOL;
}