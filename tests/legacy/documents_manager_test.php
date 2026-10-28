<?php

declare(strict_types=1);

use SanadAi\Config\Database;
use SanadAi\Documents\DocumentManager;
use SanadAi\Documents\DocumentRepository;
use SanadAi\Documents\DocumentStorage;
use SanadAi\Documents\DocumentValidator;
use SanadAi\Transactions\TransactionRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Database::connection();

$documentRepository = new DocumentRepository($pdo);
$transactionRepository = new TransactionRepository($pdo);
$validator = new DocumentValidator();
$storage = new DocumentStorage(dirname(__DIR__));

$manager = new DocumentManager(
    pdo: $pdo,
    repository: $documentRepository,
    transactionRepository: $transactionRepository,
    validator: $validator,
    storage: $storage
);

echo "Document Manager test\n";
echo "=====================\n";

$tenantId = 3;
$transactionPublicId = '2c471355-5263-4a4e-8978-69879deeca47';

$sourceDir = dirname(__DIR__) . '/storage/cache/document-manager-test';

if (!is_dir($sourceDir)) {
    mkdir($sourceDir, 0700, true);
}

$sourcePath = $sourceDir . '/test-cr.pdf';

file_put_contents(
    $sourcePath,
    "%PDF-1.4\n% Sanad AI document manager test\n"
);

try {
    $document = $manager->upload(
        tenantId: $tenantId,
        transactionPublicId: $transactionPublicId,
        documentType: 'commercial_registration',
        sourcePath: $sourcePath,
        originalName: 'test-cr.pdf',
        uploadedByUserId: 2
    );

    echo "PASS - Document uploaded successfully.\n";
    echo "Public ID: " . $document['public_id'] . PHP_EOL;
    echo "Transaction ID: " . $document['transaction_id'] . PHP_EOL;
    echo "Tenant ID: " . $document['tenant_id'] . PHP_EOL;
    echo "Type: " . $document['document_type'] . PHP_EOL;
    echo "Status: " . $document['status'] . PHP_EOL;
    echo "Original name: " . $document['original_name'] . PHP_EOL;
    echo "MIME type: " . $document['mime_type'] . PHP_EOL;
    echo "Size: " . $document['size_bytes'] . PHP_EOL;
    echo "Storage key: " . $document['storage_key'] . PHP_EOL;

    if ($document['status'] !== 'received') {
        throw new RuntimeException(
            'FAIL - Uploaded document must start with received status.'
        );
    }

    echo "PASS - Initial status is received.\n";

    $storageKey = $document['storage_key'];

    if (!$storage->exists($storageKey)) {
        throw new RuntimeException(
            'FAIL - Uploaded file does not exist in storage.'
        );
    }

    echo "PASS - Uploaded file exists in secure storage.\n";

    $storedPath = $storage->absolutePath($storageKey);

    $storedContents = file_get_contents($storedPath);

    if ($storedContents !== "%PDF-1.4\n% Sanad AI document manager test\n") {
        throw new RuntimeException(
            'FAIL - Stored file contents are incorrect.'
        );
    }

    echo "PASS - Stored file contents are correct.\n";

    /*
     * Verify the document.
     */
    $verified = $manager->changeStatus(
        tenantId: $tenantId,
        publicId: $document['public_id'],
        status: 'verified',
        verifiedByUserId: 2
    );

    if ($verified['status'] !== 'verified') {
        throw new RuntimeException(
            'FAIL - Document verification failed.'
        );
    }

    echo "PASS - Document verification works.\n";

    /*
     * Verified documents must be immutable.
     */
    try {
        $manager->changeStatus(
            tenantId: $tenantId,
            publicId: $document['public_id'],
            status: 'received'
        );

        throw new RuntimeException(
            'FAIL - Verified document was modified.'
        );
    } catch (RuntimeException $exception) {
        if (
            $exception->getMessage()
            !== 'A verified document cannot be modified.'
        ) {
            throw $exception;
        }

        echo "PASS - Verified document is immutable.\n";
    }

    /*
     * Cleanup the physical file and database record.
     *
     * The existing repository supports deletion, and this test
     * removes the test document after successful validation.
     */
    $deleted = $documentRepository->delete(
        tenantId: $tenantId,
        publicId: $document['public_id']
    );

    if (!$deleted) {
        throw new RuntimeException(
            'FAIL - Test document database record could not be deleted.'
        );
    }

    $storage->delete($storageKey);

    echo "PASS - Test document cleaned up.\n";
} finally {
    if (is_file($sourcePath)) {
        unlink($sourcePath);
    }

    if (is_dir($sourceDir)) {
        rmdir($sourceDir);
    }
}

echo PHP_EOL;
echo "Document Manager test completed successfully.\n";
