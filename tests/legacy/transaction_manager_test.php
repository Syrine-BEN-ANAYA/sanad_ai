<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use SanadAi\Clients\ClientRepository;
use SanadAi\Services\ServiceRepository;
use SanadAi\Transactions\TransactionManager;
use SanadAi\Transactions\TransactionRepository;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
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
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$transactionRepository = new TransactionRepository($pdo);
$clientRepository = new ClientRepository($pdo);
$serviceRepository = new ServiceRepository($pdo);

$manager = new TransactionManager(
    $transactionRepository,
    $clientRepository,
    $serviceRepository,
    $pdo
);

echo "=== Transaction Manager Test ===\n\n";

$tenantA = 3;
$tenantB = 4;

$clientA = null;
$serviceA = null;
$transaction = null;

try {
    /*
     * Find existing Tenant A client.
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM clients
        WHERE tenant_id = :tenant_id
          AND status = 'active'
        ORDER BY id ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':tenant_id' => $tenantA,
    ]);

    $clientA = $stmt->fetchColumn();

    if ($clientA === false) {
        throw new RuntimeException(
            'No active Tenant A client available for test.'
        );
    }

    /*
     * Find existing Tenant A service.
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM services
        WHERE tenant_id = :tenant_id
          AND status = 'active'
        ORDER BY id ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':tenant_id' => $tenantA,
    ]);

    $serviceA = $stmt->fetchColumn();

    if ($serviceA === false) {
        throw new RuntimeException(
            'No active Tenant A service available for test.'
        );
    }

    $clientA = (int) $clientA;
    $serviceA = (int) $serviceA;

    echo "1. Creating valid transaction...\n";

    $transaction = $manager->create(
        tenantId: $tenantA,
        clientId: $clientA,
        serviceId: $serviceA,
        title: 'Test transaction',
        description: 'Transaction Manager automated test',
        source: 'dashboard',
        priority: 'normal',
    );

    if (
        $transaction->tenantId !== $tenantA
        || $transaction->clientId !== $clientA
        || $transaction->serviceId !== $serviceA
        || $transaction->status !== 'open'
        || $transaction->reference === ''
        || $transaction->publicId === ''
    ) {
        throw new RuntimeException(
            'Created transaction data is invalid.'
        );
    }

    echo "PASS: Transaction created.\n\n";

    echo "2. Checking initial status history...\n";

    $history = $transactionRepository->findStatusHistory(
        $tenantA,
        $transaction->publicId
    );

    if (count($history) !== 1) {
        throw new RuntimeException(
            'Expected exactly one initial history record.'
        );
    }

    if (
        $history[0]['from_status'] !== null
        || $history[0]['to_status'] !== 'open'
    ) {
        throw new RuntimeException(
            'Initial status history is invalid.'
        );
    }

    echo "PASS: Initial history recorded.\n\n";

    echo "3. Testing valid transition open -> waiting_documents...\n";

    $transaction = $manager->changeStatus(
        tenantId: $tenantA,
        publicId: $transaction->publicId,
        newStatus: 'waiting_documents',
        reason: 'Documents required.',
    );

    if ($transaction->status !== 'waiting_documents') {
        throw new RuntimeException(
            'Valid status transition failed.'
        );
    }

    echo "PASS: Valid transition accepted.\n\n";

    echo "4. Testing invalid transition waiting_documents -> completed...\n";

    try {
        $manager->changeStatus(
            tenantId: $tenantA,
            publicId: $transaction->publicId,
            newStatus: 'completed',
            reason: 'Invalid test transition.',
        );

        throw new RuntimeException(
            'Invalid transition was incorrectly accepted.'
        );
    } catch (InvalidArgumentException $exception) {
        echo "PASS: Invalid transition rejected.\n\n";
    }

    echo "5. Testing valid transition waiting_documents -> in_progress...\n";

    $transaction = $manager->changeStatus(
        tenantId: $tenantA,
        publicId: $transaction->publicId,
        newStatus: 'in_progress',
        reason: 'Documents received.',
    );

    if ($transaction->status !== 'in_progress') {
        throw new RuntimeException(
            'Second valid transition failed.'
        );
    }

    echo "PASS: Second valid transition accepted.\n\n";

    echo "6. Testing cross-tenant transaction access...\n";

    $crossTenant = $transactionRepository->findByPublicId(
        $tenantB,
        $transaction->publicId
    );

    if ($crossTenant !== null) {
        throw new RuntimeException(
            'Cross-tenant transaction access was possible.'
        );
    }

    echo "PASS: Cross-tenant transaction access rejected.\n\n";

    echo "7. Testing cross-tenant client rejection...\n";

    $stmt = $pdo->prepare("
        SELECT id
        FROM clients
        WHERE tenant_id = :tenant_id
          AND status = 'active'
        ORDER BY id ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':tenant_id' => $tenantB,
    ]);

    $clientB = $stmt->fetchColumn();

    if ($clientB !== false) {
        try {
            $manager->create(
                tenantId: $tenantA,
                clientId: (int) $clientB,
                serviceId: $serviceA,
                title: 'Cross tenant test',
            );

            throw new RuntimeException(
                'Cross-tenant client was incorrectly accepted.'
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'Client not found.') {
                throw $exception;
            }

            echo "PASS: Cross-tenant client rejected.\n\n";
        }
    } else {
        echo "SKIP: No Tenant B client available.\n\n";
    }

    echo "8. Testing transition to completed...\n";

    $transaction = $manager->changeStatus(
        tenantId: $tenantA,
        publicId: $transaction->publicId,
        newStatus: 'completed',
        reason: 'Transaction completed.',
    );

    if (
        $transaction->status !== 'completed'
        || $transaction->completedAt === null
    ) {
        throw new RuntimeException(
            'Completion transition failed.'
        );
    }

    echo "PASS: Transaction completed correctly.\n\n";

    echo "9. Testing modification of completed transaction...\n";

    try {
        $manager->update(
            tenantId: $tenantA,
            publicId: $transaction->publicId,
            clientId: $clientA,
            serviceId: $serviceA,
            title: 'Should fail',
            description: null,
            assignedUserId: null,
            source: 'dashboard',
            priority: 'normal',
            dueAt: null,
        );

        throw new RuntimeException(
            'Completed transaction was incorrectly editable.'
        );
    } catch (InvalidArgumentException $exception) {
        echo "PASS: Completed transaction cannot be edited.\n\n";
    }

    echo "10. Checking final history...\n";

    $history = $transactionRepository->findStatusHistory(
        $tenantA,
        $transaction->publicId
    );

    if (count($history) !== 4) {
        throw new RuntimeException(
            'Unexpected number of history records.'
        );
    }

    $expectedTransitions = [
        [null, 'open'],
        ['open', 'waiting_documents'],
        ['waiting_documents', 'in_progress'],
    ];

    /*
     * The completed transition is also expected.
     */
    $expectedTransitions[] = [
        'in_progress',
        'completed',
    ];

    foreach ($expectedTransitions as $index => $expected) {
        if (
            $history[$index]['from_status'] !== $expected[0]
            || $history[$index]['to_status'] !== $expected[1]
        ) {
            throw new RuntimeException(
                'Status history sequence is invalid.'
            );
        }
    }

    echo "PASS: Status history sequence is correct.\n\n";

} finally {
    /*
     * Cleanup test transaction and its history.
     */
    if ($transaction !== null) {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                DELETE FROM transaction_status_history
                WHERE transaction_id = :transaction_id
                  AND tenant_id = :tenant_id
            ");

            $stmt->execute([
                ':transaction_id' => $transaction->id,
                ':tenant_id' => $tenantA,
            ]);

            $stmt = $pdo->prepare("
                DELETE FROM transactions
                WHERE id = :id
                  AND tenant_id = :tenant_id
            ");

            $stmt->execute([
                ':id' => $transaction->id,
                ':tenant_id' => $tenantA,
            ]);

            $pdo->commit();

            echo "\nPASS: Test transaction cleaned.\n";
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}

echo "\n=== ALL TRANSACTION MANAGER TESTS PASSED ===\n";