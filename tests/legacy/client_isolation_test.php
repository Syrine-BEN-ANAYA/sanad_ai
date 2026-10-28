<?php

declare(strict_types=1);

use SanadAi\Clients\ClientRepository;
use SanadAi\Config\Database;

require_once __DIR__ . '/../config/bootstrap.php';

$pdo = Database::connection();
$repository = new ClientRepository($pdo);

$tenantA = 3;
$tenantB = 4;

$publicId = sprintf(
    '%s-%s-%s-%s-%s',
    bin2hex(random_bytes(4)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(6))
);

echo "=== Client Isolation Test ===\n\n";

echo "1. Creating client for Tenant A...\n";

$client = $repository->create(
    tenantId: $tenantA,
    publicId: $publicId,
    clientType: 'person',
    fullName: 'Isolation Test Client',
    companyName: null,
    phone: '+96890000000',
    whatsappNumber: '+96890000000',
    email: null,
    preferredLanguage: 'ar',
    notes: 'Temporary isolation test',
);

echo "Client created.\n";
echo "Public ID: {$client->publicId}\n";
echo "Tenant ID: {$client->tenantId}\n\n";

echo "2. Tenant A tries to access its own client...\n";

$clientFromA = $repository->findByPublicId(
    $tenantA,
    $publicId
);

if ($clientFromA === null) {
    throw new RuntimeException(
        'FAIL: Tenant A cannot access its own client.'
    );
}

echo "PASS: Tenant A can access its own client.\n\n";

echo "3. Tenant B tries to access Tenant A's client...\n";

$clientFromB = $repository->findByPublicId(
    $tenantB,
    $publicId
);

if ($clientFromB !== null) {
    throw new RuntimeException(
        'FAIL: Tenant B can access Tenant A client!'
    );
}

echo "PASS: Tenant B cannot access Tenant A client.\n\n";

echo "4. Listing Tenant B clients...\n";

$tenantBClients = $repository->findAll($tenantB);

foreach ($tenantBClients as $tenantBClient) {
    if ($tenantBClient->publicId === $publicId) {
        throw new RuntimeException(
            'FAIL: Tenant A client appeared in Tenant B list!'
        );
    }
}

echo "PASS: Tenant A client is not visible in Tenant B list.\n\n";

echo "=== ALL ISOLATION TESTS PASSED ===\n";