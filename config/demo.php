<?php

declare(strict_types=1);

/*
 * Demo / test accounts.
 *
 * Single source of truth for:
 *   - database/seeders/DemoAccountsSeeder.php (creates them)
 *   - GET /api/demo-accounts                  (shows them on the login page)
 *
 * The login page only lists them when APP_ENV is not "production",
 * DEMO_LOGINS is not "false", and the accounts exist in the database.
 */

return [
    'tenant' => [
        'name' => 'Demo Sanad Office',
        'slug' => 'demo-office',
    ],

    'password' => 'Demo@12345',

    'accounts' => [
        ['username' => 'demo_manager',    'full_name' => 'Demo Manager',          'role' => 'manager'],
        ['username' => 'demo_service',    'full_name' => 'Demo Customer Service', 'role' => 'customer_service'],
        ['username' => 'demo_accountant', 'full_name' => 'Demo Accountant',       'role' => 'accountant'],
        ['username' => 'demo_clearance',  'full_name' => 'Demo Clearance Officer', 'role' => 'clearance_employee'],
    ],
];
