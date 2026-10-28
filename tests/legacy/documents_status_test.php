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

echo "Documents status test\n";
echo "=====================\n";

$document = $manager->changeStatus(
    tenantId: 3,
    publicId: $documentPublicId,
    status: 'verified',
    rejectionReason: null,
    verifiedByUserId: 2
);

echo "Status: " . $document['status'] . PHP_EOL;
echo "Verified by: " . $document['verified_by_user_id'] . PHP_EOL;
echo "Verified at: " . $document['verified_at'] . PHP_EOL;

if ($document['status'] !== 'verified') {
    throw new RuntimeException(
        'FAIL - Document was not verified.'
    );
}

if ((int) $document['verified_by_user_id'] !== 2) {
    throw new RuntimeException(
        'FAIL - Wrong verifier was recorded.'
    );
}

if (
    empty($document['verified_at'])
) {
    throw new RuntimeException(
        'FAIL - verified_at was not recorded.'
    );
}

echo "PASS - Document verification works.\n";
