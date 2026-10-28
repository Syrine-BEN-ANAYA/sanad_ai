<?php

declare(strict_types=1);

use SanadAi\Clients\ClientRepository;
use SanadAi\Clients\ClientService;
use SanadAi\Config\Database;

require_once __DIR__ . '/../config/bootstrap.php';

$pdo = Database::connection();

$repository = new ClientRepository($pdo);
$service = new ClientService($repository);

$tenantId = 3;

echo "=== Client Service Test ===\n\n";

/*
 * Test 1 — valid person
 */
echo "1. Creating valid person...\n";

$client = $service->create(
    tenantId: $tenantId,
    clientType: 'person',
    fullName: 'Ahmed Test',
    phone: '+96890000001',
    whatsappNumber: '+96890000001',
    email: 'ahmed.test@example.com',
    preferredLanguage: 'ar',
);

if ($client->tenantId !== $tenantId) {
    throw new RuntimeException(
        'FAIL: Wrong tenant ID.'
    );
}

if ($client->fullName !== 'Ahmed Test') {
    throw new RuntimeException(
        'FAIL: Wrong client name.'
    );
}

echo "PASS: Valid person created.\n\n";

/*
 * Test 2 — company without company name
 */
echo "2. Rejecting company without company name...\n";

try {
    $service->create(
        tenantId: $tenantId,
        clientType: 'company',
        fullName: 'Company Contact',
    );

    throw new RuntimeException(
        'FAIL: Company without company name was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Invalid company rejected.\n\n";
}

/*
 * Test 3 — invalid email
 */
echo "3. Rejecting invalid email...\n";

try {
    $service->create(
        tenantId: $tenantId,
        clientType: 'person',
        fullName: 'Invalid Email Test',
        email: 'not-an-email',
    );

    throw new RuntimeException(
        'FAIL: Invalid email was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Invalid email rejected.\n\n";
}

/*
 * Test 4 — invalid client type
 */
echo "4. Rejecting invalid client type...\n";

try {
    $service->create(
        tenantId: $tenantId,
        clientType: 'unknown',
        fullName: 'Invalid Type Test',
    );

    throw new RuntimeException(
        'FAIL: Invalid client type was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Invalid client type rejected.\n\n";
}

/*
 * Test 5 — invalid language
 */
echo "5. Rejecting invalid language...\n";

try {
    $service->create(
        tenantId: $tenantId,
        clientType: 'person',
        fullName: 'Invalid Language Test',
        preferredLanguage: 'xx',
    );

    throw new RuntimeException(
        'FAIL: Invalid language was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Invalid language rejected.\n\n";
}

/*
 * Cleanup
 */
echo "6. Cleaning test data...\n";

$pdo->exec("
    DELETE FROM clients
    WHERE full_name = 'Ahmed Test'
");

echo "PASS: Test data cleaned.\n\n";

echo "=== ALL CLIENT SERVICE TESTS PASSED ===\n";