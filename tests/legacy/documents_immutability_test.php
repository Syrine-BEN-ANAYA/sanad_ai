<?php

declare(strict_types=1);

use SanadAi\Config\Database;
use SanadAi\Documents\DocumentManager;
use SanadAi\Documents\DocumentRepository;
use SanadAi\Transactions\TransactionRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/bootstrap.php';

$pdo = Database::connection();

$documentRepository = new DocumentRepository($pdo);
$transactionRepository = new TransactionRepository($pdo);

$manager = new DocumentManager(
    pdo: $pdo,
    repository: $documentRepository,
    transactionRepository: $transactionRepository
);

$documentPublicId = 'f0272d54-f705-41fa-affd-0d40fa1f5f98';

echo "Documents immutability test\n";
echo "===========================\n";

try {
    $manager->changeStatus(
        tenantId: 3,
        publicId: $documentPublicId,
        status: 'rejected',
        rejectionReason: 'Test modification after verification.',
        verifiedByUserId: null
    );

    echo "FAIL - A verified document was modified.\n";
    exit(1);
} catch (RuntimeException $exception) {
    echo "Expected exception received:\n";
    echo $exception->getMessage() . PHP_EOL;
}

$document = $manager->find(
    tenantId: 3,
    publicId: $documentPublicId
);

if ($document === null) {
    throw new RuntimeException(
        'FAIL - Document disappeared unexpectedly.'
    );
}

echo PHP_EOL;
echo "Current status: " . $document['status'] . PHP_EOL;

if ($document['status'] !== 'verified') {
    throw new RuntimeException(
        'FAIL - Verified document status was changed.'
    );
}
