<?php

declare(strict_types=1);

/*
 * Runs the whole test suite:
 *   php tests/run.php
 *
 * 1. Fast unit scripts (tests/*_test.php except the HTTP smoke test)
 * 2. The HTTP end-to-end test, against a temporary PHP built-in server
 *
 * Needs a migrated + demo-seeded database:
 *   php scripts/migrate.php && php scripts/seed.php --demo
 */

$root = dirname(__DIR__);
$failed = [];

foreach (glob(__DIR__ . '/*_test.php') ?: [] as $file) {
    if (basename($file) === 'api_smoke_test.php') {
        continue;
    }

    exec(PHP_BINARY . ' ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    $output = [];

    echo ($code === 0 ? 'PASS  ' : 'FAIL  ') . basename($file) . PHP_EOL;

    if ($code !== 0) {
        $failed[] = basename($file);
    }
}

$port = random_int(20000, 40000);
$nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public', $root . '/router.php'],
    [1 => ['file', $nullDevice, 'w'], 2 => ['file', $nullDevice, 'w']],
    $pipes,
    $root
);

if (!is_resource($server)) {
    echo 'FAIL  unable to start HTTP test server' . PHP_EOL;
    $failed[] = 'api_smoke_test.php';
} else {
    usleep(700000);

    passthru(
        PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/api_smoke_test.php') . " http://127.0.0.1:{$port}",
        $code
    );

    proc_terminate($server);

    if ($code !== 0) {
        $failed[] = 'api_smoke_test.php';
    }
}

echo PHP_EOL . ($failed === [] ? 'ALL TESTS PASSED' : 'FAILED: ' . implode(', ', $failed)) . PHP_EOL;

exit($failed === [] ? 0 : 1);
