<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use SanadAi\Auth\LoginThrottle;
use SanadAi\Http\TooManyRequestsException;
use SanadAi\Support\LocalStore;

$directory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'sanad-local-store-'
    . bin2hex(random_bytes(8));
$store = new LocalStore($directory);
$tests = [];

$tests['Value can be stored and retrieved'] =
    $store->set('local-store-test', 'stored value', 120)
    && $store->get('local-store-test') === 'stored value';
$tests['Stored value has an active TTL'] =
    $store->ttl('local-store-test') > 0;
$tests['Value can be deleted'] =
    $store->delete('local-store-test') === 1
    && $store->exists('local-store-test') === 0;
$tests['Counter increments atomically and keeps its expiry'] =
    $store->increment('counter-test', 120) === 1
    && $store->increment('counter-test', 900) === 2
    && $store->ttl('counter-test') <= 120;

$throttle = new LoginThrottle(
    $store,
    maxPerAccount: 2,
    maxPerIp: 5,
    windowSeconds: 120
);
$throttle->assertAllowed('192.0.2.10', 'test-user');
$throttle->recordFailure('192.0.2.10', 'test-user');
$throttle->recordFailure('192.0.2.10', 'test-user');

try {
    $throttle->assertAllowed('192.0.2.10', 'test-user');
    $tests['Account lockout is enforced'] = false;
} catch (TooManyRequestsException) {
    $tests['Account lockout is enforced'] = true;
}

$throttle->recordSuccess('192.0.2.10', 'test-user');
$throttle->assertAllowed('192.0.2.10', 'test-user');
$tests['Successful login clears the account counter'] = true;

$store->set('expiry-test', 'temporary', 1);
sleep(2);
$tests['Expired values are removed'] =
    $store->get('expiry-test') === false
    && $store->ttl('expiry-test') === -2;

foreach ($tests as $name => $passed) {
    echo $name . ': ' . ($passed ? 'OK' : 'FAILED') . PHP_EOL;
}

$entries = scandir($directory);

if ($entries !== false) {
    foreach ($entries as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            unlink($directory . DIRECTORY_SEPARATOR . $entry);
        }
    }
}

rmdir($directory);

if (in_array(false, $tests, true)) {
    throw new RuntimeException('Local store test FAILED.');
}

echo PHP_EOL . "Local store test PASSED." . PHP_EOL;
