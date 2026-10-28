<?php

declare(strict_types=1);

use SanadAi\Documents\DocumentValidator;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$validator = new DocumentValidator();

echo "Document Validator test\n";
echo "=======================\n";

$testDir = dirname(__DIR__) . '/storage/cache/document-validator-test';

if (!is_dir($testDir)) {
    mkdir($testDir, 0700, true);
}

/*
 * Test 1 — valid PDF
 */
$pdfPath = $testDir . '/test.pdf';

file_put_contents(
    $pdfPath,
    "%PDF-1.4\n% Sanad AI test\n"
);

$result = $validator->validate(
    filePath: $pdfPath,
    originalName: 'test.pdf'
);

echo "PASS - Valid PDF accepted.\n";
echo "Detected MIME: " . $result['mime_type'] . PHP_EOL;


/*
 * Test 2 — fake extension
 */
$fakeExtensionPath = $testDir . '/fake.pdf';

file_put_contents(
    $fakeExtensionPath,
    "This is plain text, not a PDF."
);

try {
    $validator->validate(
        filePath: $fakeExtensionPath,
        originalName: 'document.pdf'
    );

    throw new RuntimeException(
        'FAIL - Fake PDF was accepted.'
    );
} catch (RuntimeException $exception) {
    echo "PASS - Fake PDF rejected.\n";
}


/*
 * Test 3 — valid PNG
 */
$pngPath = $testDir . '/test.png';

/*
 * Minimal valid 1x1 PNG.
 */
$pngBase64 =
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB'
    . 'CAQAAAC1HAwCAAAAC0lEQVR42mNk'
    . 'YAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

file_put_contents(
    $pngPath,
    base64_decode($pngBase64, true)
);

$result = $validator->validate(
    filePath: $pngPath,
    originalName: 'test.png'
);

echo "PASS - Valid PNG accepted.\n";
echo "Detected MIME: " . $result['mime_type'] . PHP_EOL;


/*
 * Test 4 — extension mismatch
 */
$extensionMismatchPath = $testDir . '/test.png';

try {
    $validator->validate(
        filePath: $extensionMismatchPath,
        originalName: 'test.pdf'
    );

    throw new RuntimeException(
        'FAIL - Extension mismatch was accepted.'
    );
} catch (RuntimeException $exception) {
    echo "PASS - Extension mismatch rejected.\n";
}


/*
 * Test 5 — missing file
 */
try {
    $validator->validate(
        filePath: $testDir . '/does-not-exist.pdf',
        originalName: 'missing.pdf'
    );

    throw new RuntimeException(
        'FAIL - Missing file was accepted.'
    );
} catch (RuntimeException $exception) {
    echo "PASS - Missing file rejected.\n";
}


/*
 * Cleanup
 */
foreach ([
    $pdfPath,
    $fakeExtensionPath,
    $pngPath,
] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

if (is_dir($testDir)) {
    rmdir($testDir);
}

echo PHP_EOL;
echo "Document Validator test completed successfully.\n";
