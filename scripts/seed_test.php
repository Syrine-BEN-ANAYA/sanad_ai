<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Config\Database;
use SanadAi\Database\Seeders\TestTenantSeeder;

$pdo = Database::connection();

TestTenantSeeder::run($pdo);

echo "Test seeding completed." . PHP_EOL;