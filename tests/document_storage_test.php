<?php

declare(strict_types=1);

use SanadAi\Documents\DocumentStorage;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$storage = new DocumentStorage(
    dirname(__DIR__)
);

echo "Document Storage test\n";
echo "=====================\n";

$storageKey = $storage->generateStorageKey(
    tenantId: 3,
    transactionId: 3,
    originalName: 'test-document.pdf'
);

echo "Generated storage key:\n";
echo $storageKey . PHP_EOL;

$contents = "%PDF-TEST-SANAD-AI%";

$path = $storage->store(
    storageKey: $storageKey,
    contents: $contents
);

echo "File stored at:\n";
echo $path . PHP_EOL;

if (!$storage->exists($storageKey)) {
    throw new RuntimeException(
        'FAIL - Stored file does not exist.'
    );
}

echo "PASS - File exists.\n";

$storedContents = file_get_contents($path);

if ($storedContents !== $contents) {
    throw new RuntimeException(
        'FAIL - Stored file contents are incorrect.'
    );
}

echo "PASS - File contents are correct.\n";

try {
    $storage->absolutePath(
        '../outside-storage/test.pdf'
    );

    throw new RuntimeException(
        'FAIL - Path traversal was not blocked.'
    );
} catch (RuntimeException $exception) {
    echo "PASS - Path traversal blocked.\n";
}

if (!$storage->delete($storageKey)) {
    throw new RuntimeException(
        'FAIL - Stored file could not be deleted.'
    );
}

if ($storage->exists($storageKey)) {
    throw new RuntimeException(
        'FAIL - File still exists after deletion.'
    );
}

echo "PASS - File deletion works.\n";
echo PHP_EOL;
echo "Document Storage test completed successfully.\n";