<?php

declare(strict_types=1);

/*
 * Demo logins for testing (shown on the login page).
 *
 *   php scripts/seed.php        # once: roles, permissions, ministries
 *   php scripts/seed_demo.php   # demo office + demo users (idempotent)
 */

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Config\Database;
use SanadAi\Database\Seeders\DemoAccountsSeeder;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

if (APP_ENV === 'production') {
    fwrite(STDERR, "Refusing to create demo accounts (known password) when APP_ENV=production.\n");
    exit(1);
}

try {
    $result = DemoAccountsSeeder::run(Database::connection());
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$config = require __DIR__ . '/../config/demo.php';

echo "Demo office: {$result['tenant']}" . PHP_EOL;

foreach ($result['accounts'] as $account) {
    printf("  %-8s %-18s %s%s", $account['created'] ? 'created' : 'updated', $account['username'], $account['role'], PHP_EOL);
}

echo "Password for all: {$config['password']}" . PHP_EOL;
