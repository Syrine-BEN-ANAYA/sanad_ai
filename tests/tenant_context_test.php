<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Tenancy\TenantContext;

$context = new TenantContext();

echo "Initial state: ";

if (!$context->has()) {
    echo "OK" . PHP_EOL;
} else {
    echo "ERROR" . PHP_EOL;
    exit(1);
}

$context->set(1);

echo "Tenant ID: ";

if ($context->get() === 1) {
    echo "OK" . PHP_EOL;
} else {
    echo "ERROR" . PHP_EOL;
    exit(1);
}

echo "Own tenant 1: ";

if ($context->owns(1)) {
    echo "OK" . PHP_EOL;
} else {
    echo "ERROR" . PHP_EOL;
    exit(1);
}

echo "Reject tenant 2: ";

if (!$context->owns(2)) {
    echo "OK" . PHP_EOL;
} else {
    echo "ERROR" . PHP_EOL;
    exit(1);
}

$context->clear();

echo "Clear context: ";

if (!$context->has()) {
    echo "OK" . PHP_EOL;
} else {
    echo "ERROR" . PHP_EOL;
    exit(1);
}

echo "TenantContext test PASSED." . PHP_EOL;