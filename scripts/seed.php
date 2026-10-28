<?php

declare(strict_types=1);

/*
 * Usage:
 *   php scripts/seed.php           roles, permissions, ministries (safe for production)
 *   php scripts/seed.php --demo    + two demo offices, demo users, sample data and the demo logins
 *                                  listed on the login page (see config/demo.php)
 */

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Config\Database;
use SanadAi\Database\Seeders\CatalogSeeder;
use SanadAi\Database\Seeders\DemoAccountsSeeder;
use SanadAi\Database\Seeders\DemoDataSeeder;
use SanadAi\Database\Seeders\RbacSeeder;
use SanadAi\Database\Seeders\TestTenantSeeder;

$pdo = Database::connection();

RbacSeeder::run($pdo);
CatalogSeeder::run($pdo);

if (in_array('--demo', $argv ?? [], true)) {
    if (APP_ENV === 'production') {
        fwrite(STDERR, "Refusing to load demo data (known password) when APP_ENV=production.\n");
        exit(1);
    }

    TestTenantSeeder::run($pdo);
    DemoDataSeeder::run($pdo);
    DemoAccountsSeeder::run($pdo);
}

echo "Seeding completed." . PHP_EOL;
