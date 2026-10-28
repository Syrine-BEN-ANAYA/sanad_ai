<?php

declare(strict_types=1);

use SanadAi\Config\Database;
use SanadAi\Documents\DocumentRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/bootstrap.php';

$pdo = Database::connection();

$repository = new DocumentRepository($pdo);

$documentPublicId = 'f0272d54-f705-41fa-affd-0d40fa1f5f98';

echo "Documents tenant isolation test\n";
echo "===============================\n";

echo "Tenant A (3):\n";

$tenantA = $repository->findByPublicId(
    tenantId: 3,
    publicId: $documentPublicId
);

if ($tenantA !== null) {
    echo "PASS - Tenant A can access its document.\n";
    echo "Document: " . $tenantA['public_id'] . PHP_EOL;
} else {
    echo "FAIL - Tenant A cannot access its own document.\n";
}

echo PHP_EOL;

echo "Tenant B (4):\n";

$tenantB = $repository->findByPublicId(
    tenantId: 4,
    publicId: $documentPublicId
);

if ($tenantB === null) {
    echo "PASS - Tenant B cannot access Tenant A document.\n";
} else {
    echo "FAIL - SECURITY ISSUE: Tenant B accessed Tenant A document!\n";
    var_dump($tenantB);
}
