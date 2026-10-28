<?php

declare(strict_types=1);

/*
 * End-to-end API test against a running server.
 *
 *   php -S 127.0.0.1:8080 -t public router.php &
 *   php tests/api_smoke_test.php [http://127.0.0.1:8080]
 *
 * Requires: php scripts/migrate.php && php scripts/seed.php && php scripts/seed_test.php
 * Uses only CLI curl-free PHP streams (no extra dependency).
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$password = 'TestPassword123!';

$failures = 0;
$passes = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures, $passes;

    if ($condition) {
        $passes++;
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
    }
}

/**
 * @param array<string, mixed>|null $json
 * @return array{status: int, body: array<string, mixed>|null, raw: string, headers: array<string>}
 */
function call(
    string $method,
    string $url,
    ?array $json = null,
    array &$jar = [],
    array $headers = [],
    ?string $rawBody = null,
    ?string $contentType = null,
): array {
    $headerLines = ['Accept: application/json'];

    if ($jar !== []) {
        $cookie = [];
        foreach ($jar as $k => $v) {
            $cookie[] = "{$k}={$v}";
        }
        $headerLines[] = 'Cookie: ' . implode('; ', $cookie);
    }

    $body = $rawBody;

    if ($json !== null) {
        $body = json_encode($json, JSON_THROW_ON_ERROR);
        $contentType = 'application/json';
    }

    if ($contentType !== null) {
        $headerLines[] = 'Content-Type: ' . $contentType;
    }

    foreach ($headers as $h) {
        $headerLines[] = $h;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);

    $raw = (string) @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    $status = 0;

    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
    }

    foreach ($responseHeaders as $line) {
        if (stripos($line, 'Set-Cookie:') === 0) {
            $pair = explode(';', trim(substr($line, 11)))[0];
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if ($value === '' || $value === 'deleted') {
                unset($jar[$name]);
            } else {
                $jar[$name] = $value;
            }
        }
    }

    $decoded = json_decode($raw, true);

    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : null,
        'raw' => $raw,
        'headers' => $responseHeaders,
    ];
}

/**
 * @return array{jar: array<string, string>, csrf: string}
 */
function login(string $base, string $username, string $password): array
{
    $jar = [];
    $r = call('POST', "{$base}/api/login", ['username' => $username, 'password' => $password], $jar);

    if ($r['status'] !== 200) {
        throw new RuntimeException("Login failed for {$username}: HTTP {$r['status']} {$r['raw']}");
    }

    return ['jar' => $jar, 'csrf' => (string) $r['body']['csrf_token']];
}

/* ------------------------------------------------------------------ */
echo "== Health & public\n";

$anon = [];
$r = call('GET', "$base/health", null, $anon);
check('health is healthy', $r['status'] === 200 && ($r['body']['status'] ?? '') === 'healthy');

$r = call('GET', "$base/api/clients", null, $anon);
check('clients requires authentication (401)', $r['status'] === 401);

$r = call('GET', "$base/api/does-not-exist", null, $anon);
check('unknown route returns 404 JSON', $r['status'] === 404 && ($r['body']['success'] ?? null) === false);

/* ------------------------------------------------------------------ */
echo "== Authentication\n";

$bad = [];
$r = call('POST', "$base/api/login", ['username' => 'manager_a', 'password' => 'wrong'], $bad);
check('wrong password returns 401', $r['status'] === 401);

$r = call('POST', "$base/api/login", ['username' => 'nobody-' . bin2hex(random_bytes(3)), 'password' => 'x'], $bad);
check('unknown user returns the same 401', $r['status'] === 401 && ($r['body']['message'] ?? '') === 'Invalid credentials.');

$manager = login($base, 'manager_a', $password);
$mJar = $manager['jar'];
$csrf = $manager['csrf'];
$csrfHeader = ["X-CSRF-Token: {$csrf}"];

$r = call('GET', "$base/api/me", null, $mJar);
check('/api/me works', $r['status'] === 200 && ($r['body']['user']['username'] ?? '') === 'manager_a');
check('/api/me hides internal ids', !isset($r['body']['user']['id']) && !isset($r['body']['user']['tenant_id']));
check('/api/me exposes permissions and tenant', in_array('clients.view', $r['body']['permissions'] ?? [], true) && isset($r['body']['tenant']['name']));

$r = call('POST', "$base/api/clients", ['full_name' => 'No CSRF'], $mJar);
check('write without CSRF token is rejected (403)', $r['status'] === 403);

$r = call('POST', "$base/api/clients", ['full_name' => 'Bad CSRF'], $mJar, ['X-CSRF-Token: ' . str_repeat('a', 64)]);
check('write with a wrong CSRF token is rejected (403)', $r['status'] === 403);

/* ------------------------------------------------------------------ */
echo "== Clients\n";

$unique = bin2hex(random_bytes(3));
$r = call('POST', "$base/api/clients", [
    'client_type' => 'person',
    'full_name' => "  Test Client {$unique}  ",
    'phone' => '+96891112222',
    'email' => "t{$unique}@example.com",
], $mJar, $csrfHeader);
check('create client (201)', $r['status'] === 201, $r['raw']);
$clientId = (string) ($r['body']['data']['public_id'] ?? '');
check('client name is trimmed', ($r['body']['data']['full_name'] ?? '') === "Test Client {$unique}");
check('client response has no internal id', !isset($r['body']['data']['id']) && !isset($r['body']['data']['tenant_id']));

$r = call('POST', "$base/api/clients", ['client_type' => 'person', 'full_name' => 'X', 'email' => 'not-an-email'], $mJar, $csrfHeader);
check('invalid email returns 422', $r['status'] === 422);

$r = call('POST', "$base/api/clients", ['client_type' => 'company', 'full_name' => 'Boss'], $mJar, $csrfHeader);
check('company without company name returns 422', $r['status'] === 422);

$r = call('PUT', "$base/api/clients/{$clientId}", ['phone' => null, 'notes' => 'VIP'], $mJar, $csrfHeader);
check('update client and clear phone', $r['status'] === 200 && $r['body']['data']['phone'] === null && $r['body']['data']['notes'] === 'VIP', $r['raw']);

$r = call('PUT', "$base/api/clients/{$clientId}", ['notes' => 'VIP'], $mJar, $csrfHeader);
check('idempotent update (no change) still succeeds', $r['status'] === 200 && ($r['body']['success'] ?? false) === true);

$r = call('GET', "$base/api/clients?q=" . urlencode("Test Client {$unique}"), null, $mJar);
check('search finds the client', $r['status'] === 200 && ($r['body']['meta']['total'] ?? 0) === 1);

$r = call('GET', "$base/api/clients?q=" . urlencode('%'), null, $mJar);
check('LIKE wildcard is escaped', $r['status'] === 200 && ($r['body']['meta']['total'] ?? -1) === 0);

/* ------------------------------------------------------------------ */
echo "== Services\n";

$r = call('GET', "$base/api/ministries", null, $mJar);
check('ministries list', $r['status'] === 200 && count($r['body']['data'] ?? []) >= 5);
$ministryId = (string) ($r['body']['data'][0]['public_id'] ?? '');

$r = call('POST', "$base/api/services", [
    'name_ar' => "خدمة اختبار {$unique}",
    'name_en' => "Test service {$unique}",
    'ministry_public_id' => $ministryId,
    'official_fee_baisa' => 1500,
    'office_fee_baisa' => 500,
    'target_sla_minutes' => 60,
], $mJar, $csrfHeader);
check('create service (201)', $r['status'] === 201, $r['raw']);
$serviceId = (string) ($r['body']['data']['public_id'] ?? '');
check('service exposes ministry public data', ($r['body']['data']['ministry']['public_id'] ?? '') === $ministryId);

$r = call('POST', "$base/api/services", ['name_ar' => 'x', 'official_fee_baisa' => -5], $mJar, $csrfHeader);
check('negative fee returns 422', $r['status'] === 422);

$r = call('POST', "$base/api/services", ['name_ar' => 'x', 'ministry_public_id' => 'nope'], $mJar, $csrfHeader);
check('unknown ministry returns 422', $r['status'] === 422);

$r = call('PUT', "$base/api/services/{$serviceId}", ['office_fee_baisa' => 750], $mJar, $csrfHeader);
check('update service', $r['status'] === 200 && ($r['body']['data']['office_fee_baisa'] ?? 0) === 750, $r['raw']);

/* ------------------------------------------------------------------ */
echo "== Transactions\n";

$r = call('POST', "$base/api/transactions", [
    'client_public_id' => $clientId,
    'service_public_id' => $serviceId,
    'title' => "Case {$unique}",
    'priority' => 'high',
    'due_at' => date('Y-m-d\TH:i', time() + 86400),
], $mJar, $csrfHeader);
check('create transaction (201)', $r['status'] === 201, $r['raw']);
$txId = (string) ($r['body']['data']['public_id'] ?? '');
check('transaction starts open with a reference', ($r['body']['data']['status'] ?? '') === 'open' && str_starts_with((string) ($r['body']['data']['reference'] ?? ''), 'SAN-'));
check('transaction embeds client and service', ($r['body']['data']['client']['public_id'] ?? '') === $clientId && ($r['body']['data']['service']['public_id'] ?? '') === $serviceId);

$r = call('POST', "$base/api/transactions", ['client_public_id' => 'missing', 'service_public_id' => $serviceId, 'title' => 'x'], $mJar, $csrfHeader);
check('unknown client returns 422 (not 500)', $r['status'] === 422, $r['raw']);

$r = call('POST', "$base/api/transactions/{$txId}/status", ['status' => 'completed'], $mJar, $csrfHeader);
check('illegal transition open -> completed returns 422', $r['status'] === 422, $r['raw']);

$r = call('POST', "$base/api/transactions/{$txId}/status", ['status' => 'in_progress', 'reason' => 'Started'], $mJar, $csrfHeader);
check('legal transition open -> in_progress', $r['status'] === 200 && ($r['body']['data']['status'] ?? '') === 'in_progress', $r['raw']);

$r = call('GET', "$base/api/transactions/{$txId}", null, $mJar);
check('detail shows history and allowed transitions', $r['status'] === 200 && count($r['body']['history'] ?? []) === 2 && in_array('completed', $r['body']['allowed_transitions'] ?? [], true), $r['raw']);

$r = call('GET', "$base/api/transactions?status=in_progress&q=" . urlencode("Case {$unique}"), null, $mJar);
check('filter transactions by status + search', $r['status'] === 200 && ($r['body']['meta']['total'] ?? 0) === 1);

$r = call('GET', "$base/api/transactions?overdue=1", null, $mJar);
check('overdue filter works', $r['status'] === 200 && ($r['body']['meta']['total'] ?? 0) >= 1);

$users = call('GET', "$base/api/users/assignable", null, $mJar);
check('assignable users list', $users['status'] === 200 && count($users['body']['data'] ?? []) >= 3);
$assignee = (string) ($users['body']['data'][0]['public_id'] ?? '');

$r = call('PUT', "$base/api/transactions/{$txId}", ['assigned_user_public_id' => $assignee, 'title' => "Case {$unique} v2"], $mJar, $csrfHeader);
check('manager can assign a transaction', $r['status'] === 200 && ($r['body']['data']['assignee']['public_id'] ?? '') === $assignee, $r['raw']);

/* ------------------------------------------------------------------ */
echo "== Documents\n";

$boundary = '----sanad' . bin2hex(random_bytes(6));
$pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
$multipart = "--{$boundary}\r\nContent-Disposition: form-data; name=\"document_type\"\r\n\r\npassport\r\n"
    . "--{$boundary}\r\nContent-Disposition: form-data; name=\"document\"; filename=\"passport.pdf\"\r\nContent-Type: application/pdf\r\n\r\n{$pdf}\r\n--{$boundary}--\r\n";

$r = call('POST', "$base/api/transactions/{$txId}/documents", null, $mJar, $csrfHeader, $multipart, "multipart/form-data; boundary={$boundary}");
check('upload PDF (201)', $r['status'] === 201, $r['raw']);
$docId = (string) ($r['body']['data']['public_id'] ?? '');
check('document response hides storage key', !isset($r['body']['data']['storage_key']));

$evil = "--{$boundary}\r\nContent-Disposition: form-data; name=\"document_type\"\r\n\r\nx\r\n"
    . "--{$boundary}\r\nContent-Disposition: form-data; name=\"document\"; filename=\"evil.pdf\"\r\nContent-Type: application/pdf\r\n\r\n<?php echo 1; ?>\r\n--{$boundary}--\r\n";
$r = call('POST', "$base/api/transactions/{$txId}/documents", null, $mJar, $csrfHeader, $evil, "multipart/form-data; boundary={$boundary}");
check('script disguised as PDF is rejected (422)', $r['status'] === 422, $r['raw']);

$r = call('GET', "$base/api/transactions/{$txId}/documents", null, $mJar);
check('list documents', $r['status'] === 200 && count($r['body']['data'] ?? []) === 1);

$r = call('GET', "$base/api/documents/{$docId}/download", null, $mJar);
check('download returns the file', $r['status'] === 200 && str_starts_with($r['raw'], '%PDF-'));
check('download is an attachment with nosniff', (bool) preg_grep('/^Content-Disposition: attachment/i', $r['headers']) && (bool) preg_grep('/^X-Content-Type-Options: nosniff/i', $r['headers']));

$r = call('POST', "$base/api/documents/{$docId}/status", ['status' => 'rejected'], $mJar, $csrfHeader);
check('reject without a reason returns 422', $r['status'] === 422);

$r = call('POST', "$base/api/documents/{$docId}/status", ['status' => 'verified'], $mJar, $csrfHeader);
check('verify document', $r['status'] === 200 && ($r['body']['data']['status'] ?? '') === 'verified', $r['raw']);

/* ------------------------------------------------------------------ */
echo "== Users & password\n";

$newUser = 'agent' . $unique;
$r = call('POST', "$base/api/users", ['username' => $newUser, 'full_name' => 'Agent Test', 'role' => 'customer_service', 'password' => 'short'], $mJar, $csrfHeader);
check('weak password returns 422', $r['status'] === 422);

$r = call('POST', "$base/api/users", ['username' => $newUser, 'full_name' => 'Agent Test', 'role' => 'super_admin', 'password' => 'GoodPassw0rd!'], $mJar, $csrfHeader);
check('a manager cannot create a super_admin (422)', $r['status'] === 422);

$r = call('POST', "$base/api/users", ['username' => $newUser, 'full_name' => 'Agent Test', 'role' => 'customer_service', 'password' => 'GoodPassw0rd!'], $mJar, $csrfHeader);
check('create user (201)', $r['status'] === 201, $r['raw']);
$newUserId = (string) ($r['body']['data']['public_id'] ?? '');
check('user response never contains a password hash', !str_contains($r['raw'], 'password'));

$r = call('POST', "$base/api/users", ['username' => $newUser, 'full_name' => 'Dup', 'role' => 'accountant', 'password' => 'GoodPassw0rd!'], $mJar, $csrfHeader);
check('duplicate username returns 422', $r['status'] === 422);

$agent = login($base, $newUser, 'GoodPassw0rd!');
$aJar = $agent['jar'];
$aCsrf = ["X-CSRF-Token: {$agent['csrf']}"];

$r = call('GET', "$base/api/users", null, $aJar);
check('customer_service cannot list users (403)', $r['status'] === 403);

$r = call('DELETE', "$base/api/clients/{$clientId}", null, $aJar, $aCsrf);
check('customer_service cannot delete clients (403)', $r['status'] === 403);

$r = call('PUT', "$base/api/transactions/{$txId}", ['assigned_user_public_id' => null], $aJar, $aCsrf);
check('customer_service cannot re-assign (403)', $r['status'] === 403, $r['raw']);

$r = call('POST', "$base/api/me/password", ['current_password' => 'nope', 'new_password' => 'AnotherPassw0rd!'], $aJar, $aCsrf);
check('password change requires the current password', $r['status'] === 422);

$r = call('POST', "$base/api/me/password", ['current_password' => 'GoodPassw0rd!', 'new_password' => 'AnotherPassw0rd!'], $aJar, $aCsrf);
check('password change succeeds', $r['status'] === 200, $r['raw']);

$r = call('PUT', "$base/api/users/{$newUserId}", ['full_name' => 'Agent Test', 'role' => 'customer_service', 'status' => 'inactive'], $mJar, $csrfHeader);
check('manager deactivates the user', $r['status'] === 200 && ($r['body']['data']['status'] ?? '') === 'inactive');

$r = call('GET', "$base/api/me", null, $aJar);
check('deactivated user loses access immediately (401)', $r['status'] === 401);

/* ------------------------------------------------------------------ */
echo "== Tenant isolation\n";

$other = login($base, 'manager_b', $password);
$bJar = $other['jar'];
$bCsrf = ["X-CSRF-Token: {$other['csrf']}"];

$r = call('GET', "$base/api/clients/{$clientId}", null, $bJar);
check('tenant B cannot read tenant A client (404)', $r['status'] === 404);

$r = call('GET', "$base/api/transactions/{$txId}", null, $bJar);
check('tenant B cannot read tenant A transaction (404)', $r['status'] === 404);

$r = call('GET', "$base/api/documents/{$docId}/download", null, $bJar);
check('tenant B cannot download tenant A document (404)', $r['status'] === 404);

$r = call('POST', "$base/api/transactions", ['client_public_id' => $clientId, 'service_public_id' => $serviceId, 'title' => 'cross'], $bJar, $bCsrf);
check('tenant B cannot create a transaction on tenant A data (422)', $r['status'] === 422);

$r = call('GET', "$base/api/clients", null, $bJar);
check('tenant B list does not include tenant A clients', $r['status'] === 200 && !str_contains($r['raw'], $clientId));

$platform = login($base, 'platform_admin', $password);
$r = call('GET', "$base/api/clients", null, $platform['jar']);
check('platform admin (no tenant) gets 403 on tenant data', $r['status'] === 403, $r['raw']);

/* ------------------------------------------------------------------ */
echo "== Logout\n";

$r = call('POST', "$base/api/logout", null, $mJar, $csrfHeader);
check('logout succeeds', $r['status'] === 200);

$r = call('GET', "$base/api/me", null, $mJar);
check('session is gone after logout (401)', $r['status'] === 401);

/* ------------------------------------------------------------------ */
echo "== Brute force protection\n";

$victim = 'throttle' . bin2hex(random_bytes(2));
$statuses = [];

for ($i = 0; $i < 7; $i++) {
    $j = [];
    $statuses[] = call('POST', "$base/api/login", ['username' => $victim, 'password' => 'bad'], $j)['status'];
}

check('login is throttled after repeated failures (429)', in_array(429, $statuses, true), implode(',', $statuses));

echo "\n{$passes} passed, {$failures} failed\n";

exit($failures === 0 ? 0 : 1);
