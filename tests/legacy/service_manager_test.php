<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use SanadAi\Services\MinistryRepository;
use SanadAi\Services\ServiceManager;
use SanadAi\Services\ServiceRepository;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'],
        $_ENV['DB_PORT'],
        $_ENV['DB_NAME']
    ),
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$serviceRepository = new ServiceRepository($pdo);
$ministryRepository = new MinistryRepository($pdo);

$manager = new ServiceManager(
    $serviceRepository,
    $ministryRepository
);

$tenantId = 3;

echo "=== Service Manager Test ===\n\n";

echo "1. Creating valid service...\n";

$service = $manager->create(
    tenantId: $tenantId,
    ministryId: 2,
    nameAr: 'خدمة تجريبية',
    nameEn: 'Test Service',
    descriptionAr: 'وصف تجريبي',
    descriptionEn: 'Test description',
    officialFeeBaisa: 500,
    officeFeeBaisa: 250,
    targetSlaMinutes: 1440,
);

if (
    $service->tenantId === $tenantId
    && $service->ministryId === 2
    && $service->officialFeeBaisa === 500
    && $service->officeFeeBaisa === 250
    && $service->status === 'active'
) {
    echo "PASS: Valid service created.\n";
} else {
    throw new RuntimeException(
        'Valid service test failed.'
    );
}

echo "\n2. Rejecting empty Arabic name...\n";

try {
    $manager->create(
        tenantId: $tenantId,
        ministryId: 2,
        nameAr: '',
        nameEn: 'Test Service',
        descriptionAr: null,
        descriptionEn: null,
        officialFeeBaisa: 500,
        officeFeeBaisa: 250,
        targetSlaMinutes: null,
    );

    throw new RuntimeException(
        'Empty Arabic name was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Empty Arabic name rejected.\n";
}

echo "\n3. Rejecting negative official fee...\n";

try {
    $manager->create(
        tenantId: $tenantId,
        ministryId: 2,
        nameAr: 'خدمة تجريبية',
        nameEn: null,
        descriptionAr: null,
        descriptionEn: null,
        officialFeeBaisa: -1,
        officeFeeBaisa: 250,
        targetSlaMinutes: null,
    );

    throw new RuntimeException(
        'Negative official fee was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Negative official fee rejected.\n";
}

echo "\n4. Rejecting negative office fee...\n";

try {
    $manager->create(
        tenantId: $tenantId,
        ministryId: 2,
        nameAr: 'خدمة تجريبية',
        nameEn: null,
        descriptionAr: null,
        descriptionEn: null,
        officialFeeBaisa: 500,
        officeFeeBaisa: -1,
        targetSlaMinutes: null,
    );

    throw new RuntimeException(
        'Negative office fee was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Negative office fee rejected.\n";
}

echo "\n5. Rejecting inactive/non-existing ministry...\n";

try {
    $manager->create(
        tenantId: $tenantId,
        ministryId: 999999,
        nameAr: 'خدمة تجريبية',
        nameEn: null,
        descriptionAr: null,
        descriptionEn: null,
        officialFeeBaisa: 500,
        officeFeeBaisa: 250,
        targetSlaMinutes: null,
    );

    throw new RuntimeException(
        'Invalid ministry was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Invalid ministry rejected.\n";
}

echo "\n6. Rejecting negative SLA...\n";

try {
    $manager->create(
        tenantId: $tenantId,
        ministryId: 2,
        nameAr: 'خدمة تجريبية',
        nameEn: null,
        descriptionAr: null,
        descriptionEn: null,
        officialFeeBaisa: 500,
        officeFeeBaisa: 250,
        targetSlaMinutes: -10,
    );

    throw new RuntimeException(
        'Negative SLA was accepted.'
    );
} catch (InvalidArgumentException $e) {
    echo "PASS: Negative SLA rejected.\n";
}

echo "\n7. Cleaning test data...\n";

$stmt = $pdo->prepare("
    DELETE FROM services
    WHERE tenant_id = :tenant_id
      AND name_ar = :name_ar
");

$stmt->execute([
    'tenant_id' => $tenantId,
    'name_ar' => 'خدمة تجريبية',
]);

echo "PASS: Test data cleaned.\n";

echo "\n=== ALL SERVICE MANAGER TESTS PASSED ===\n";