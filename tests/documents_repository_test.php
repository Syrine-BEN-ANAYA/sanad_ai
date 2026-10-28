<?php

declare(strict_types=1);

use SanadAi\Config\Database;
use SanadAi\Documents\DocumentRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/bootstrap.php';

$pdo = Database::connection();

$repository = new DocumentRepository($pdo);

echo "Documents Repository test\n";
echo "=========================\n";

$documents = $repository->listByTransaction(
    tenantId: 3,
    transactionId: 3
);

echo "Tenant 3 / Transaction 3 documents: "
    . count($documents)
    . PHP_EOL;

var_dump($documents);

echo PHP_EOL;
echo "Repository DB connection OK.\n";
