<?php

// Самодостаточные интеграционные тесты: curl, SQLite и отдельные PHP-процессы.
$directory = sys_get_temp_dir() . '/php-demo-tests-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
putenv('APP_DB_PATH=' . $directory . '/app.sqlite');
putenv('APP_ENCRYPT_KEY=' . str_repeat('0', 64));
putenv('APP_ID=test-app');
putenv('TEST_QUEUE=' . $directory . '/queue.json');
ini_set('session.save_path', $directory);
require __DIR__ . '/../src/php/lib/lib.php';

$servers = [];
$results = [];
$failed = false;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function same(mixed $actual, mixed $expected): void
{
    check($actual === $expected, 'Expected ' . json_encode($expected) . ', got ' . json_encode($actual));
}

function queueResponses(array $responses): void
{
    file_put_contents(getenv('TEST_QUEUE'), json_encode(['responses' => $responses, 'calls' => []]), LOCK_EX);
}

function calls(): array
{
    $file = fopen(getenv('TEST_QUEUE'), 'r');
    flock($file, LOCK_SH);
    $state = json_decode(stream_get_contents($file), true);
    flock($file, LOCK_UN);
    fclose($file);
    return $state['calls'];
}

function startServer(array $arguments): string
{
    global $servers, $directory;
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $command = array_merge([PHP_BINARY, '-d', 'session.save_path=' . $directory, '-S', $address], $arguments);
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'],
        2 => ['file', $directory . '/server.log', 'a']], $pipes);
    check(is_resource($process), 'Cannot start PHP server');
    fclose($pipes[0]);
    $servers[] = $process;
    for ($i = 0; $i < 100; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.02);
        if ($connection) {
            fclose($connection);
            return 'http://' . $address;
        }
        usleep(10000);
    }
    throw new RuntimeException('PHP server did not start');
}

function startWorker(array $arguments): array
{
    global $directory;
    $process = proc_open(array_merge([PHP_BINARY, '-d', 'session.save_path=' . $directory,
        __DIR__ . '/worker.php'], $arguments), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'],
        2 => ['file', $directory . '/worker.log', 'a']], $pipes);
    check(is_resource($process), 'Cannot start worker');
    fclose($pipes[0]);
    return [$process, $pipes[1]];
}

function finishWorker(array $worker): array
{
    [$process, $output] = $worker;
    $result = stream_get_contents($output);
    fclose($output);
    same(proc_close($process), 0);
    return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
}

function sessionContext(bool $admin, string $account = 'account'): array
{
    session_id(bin2hex(random_bytes(12)));
    $context = saveActiveUserContextToSession(['uid' => 'user', 'accountId' => $account, 'isAdmin' => $admin]);
    $sid = session_id();
    session_write_close();
    return [$sid, $context['contextNonce']];
}

function endpoint(string $method, ?array $context = null): array
{
    global $appUrl;
    $curl = curl_init($appUrl . '/utils/stores.php');
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 10];
    if ($context) {
        $options[CURLOPT_COOKIE] = 'PHPSESSID=' . $context[0];
        $options[CURLOPT_POSTFIELDS] = http_build_query(['contextNonce' => $context[1]]);
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}

try {
    $apiUrl = startServer([__DIR__ . '/mock-api.php']);
    putenv('MOYSKLAD_JSON_API_URL=' . $apiUrl);
    cfg()->moyskladJsonApiEndpointUrl = $apiUrl;
    $appUrl = startServer(['-t', __DIR__ . '/../src/php']);
    $app = new AppInstance(cfg()->appId, 'account');
    $app->accessToken = 'test-token';
    $app->persist();

    $tests = [
        'testRetryAfterAndCount' => function () use ($apiUrl): void {
            queueResponses([['status' => 429, 'headers' => ['x-LoGnEx-ReTrY-AfTeR' => '40']], ['status' => 200]]);
            $result = makeHttpRequestDetailed('GET', $apiUrl, 'retry-token', rateLimited: true);
            same($result['status'], 200);
            same($result['retries'], 1);
            same($result['body']->rows, []);
            same(count(calls()), 2);
            check(calls()[1]['at'] - calls()[0]['at'] >= 0.035, 'Retry ignored milliseconds header');
        },
        'testExhaustedRetries' => function () use ($apiUrl): void {
            queueResponses([['status' => 429, 'headers' => ['X-Lognex-Retry-After' => '0']]]);
            $result = makeHttpRequestDetailed('GET', $apiUrl, 'exhausted-token', rateLimited: true);
            same($result['status'], 429);
            same($result['retries'], 10);
            same(count(calls()), 11);
        },
        'testNoRetryForPostOrPermanentErrors' => function () use ($apiUrl): void {
            foreach ([['POST', 429], ['POST', 503], ['GET', 401], ['GET', 403], ['GET', 500]] as [$method, $status]) {
                queueResponses([['status' => $status, 'headers' => ['X-Lognex-Retry-After' => '0']]]);
                $result = makeHttpRequestDetailed($method, $apiUrl, 'token');
                same($result['retries'], 0);
                same(count(calls()), 1);
            }
        },
        'testFallbackAndNon429Counter' => function () use ($apiUrl): void {
            foreach ([429, 503] as $status) {
                queueResponses([['status' => $status, 'headers' => ['X-Lognex-Retry-After' => 'bad']], ['status' => 200]]);
                $result = makeHttpRequestDetailed('GET', $apiUrl, 'fallback-token');
                same($result['status'], 200);
                same($result['retries'], $status === 429 ? 1 : 0);
                check(calls()[1]['at'] - calls()[0]['at'] >= 0.24, 'Missing fallback delay');
            }
        },
        'testPacingFromSuccessfulResponse' => function () use ($apiUrl): void {
            queueResponses([['status' => 200, 'headers' => [
                'X-RateLimit-Limit' => '2', 'X-Lognex-Retry-TimeInterval' => '160']]]);
            for ($i = 0; $i < 3; $i++) {
                $result = makeHttpRequestDetailed('GET', $apiUrl, 'success-pacing-token', rateLimited: true);
                same($result['retries'], 0);
            }
            check(calls()[2]['at'] - calls()[1]['at'] >= 0.065, 'Success headers did not set request spacing');
        },
        'testInvalidHeaders' => function (): void {
            foreach (['-1', '1.5', '20abc', '', '99999999999999999999999999'] as $value) {
                same(nonNegativeIntegerHeader(['header' => $value], 'header'), null);
            }
            same(nonNegativeIntegerHeader(['header' => '0'], 'header'), 0);
            same(nonNegativeIntegerHeader(['header' => '30'], 'header'), 30);
        },
        'testGateIsolationAndIdleCleanup' => function (): void {
            $gate = new LognexRateLimitGate('api', 'token');
            $gate->observe(429, ['x-lognex-retry-after' => '1000']);
            check((new LognexRateLimitGate('api', 'token'))->reserveDelay() > 800, 'Gate not shared');
            same((new LognexRateLimitGate('api', 'other'))->reserveDelay(), 0);
            same((new LognexRateLimitGate('other-api', 'token'))->reserveDelay(), 0);
            $pdo = new PDO('sqlite:' . appDatabasePath());
            $pdo->exec('UPDATE http_rate_limit SET last_used_at = 0, not_before = 0, spacing_ms = 1000');
            same($gate->reserveDelay(), 0);
            same($gate->reserveDelay(), 0);
        },
        'testParallelWorkersReserveDistinctSlots' => function (): void {
            $gate = new LognexRateLimitGate('parallel-api', 'parallel-token');
            $gate->observe(200, ['x-ratelimit-limit' => '2', 'x-lognex-retry-timeinterval' => '400']);
            $workers = [];
            for ($i = 0; $i < 5; $i++) {
                $workers[] = startWorker(['reserve']);
            }
            $slots = array_map(static fn($worker) => finishWorker($worker)['slot'], $workers);
            sort($slots);
            for ($i = 1; $i < count($slots); $i++) {
                check($slots[$i] - $slots[$i - 1] >= 180, 'Workers reserved the same slot');
            }
        },
        'testSessionRefreshInterval' => function (): void {
            [$sid, $nonce] = sessionContext(true);
            session_id($sid);
            ensureSessionStarted();
            $initial = $_SESSION[USER_CONTEXT_SESSION_KEY];
            refreshActiveUserContextInSession($initial);
            same($_SESSION[USER_CONTEXT_SESSION_KEY], $initial);
            $initial['expiresAt'] -= USER_CONTEXT_SESSION_REFRESH_INTERVAL_MS;
            refreshActiveUserContextInSession($initial);
            check($_SESSION[USER_CONTEXT_SESSION_KEY]['expiresAt'] > $initial['expiresAt'], 'TTL not refreshed');
            session_write_close();
        },
        'testEndpointAuthorization' => function (): void {
            queueResponses([['status' => 200]]);
            same(endpoint('GET')['status'], 405);
            same(endpoint('POST')['status'], 401);
            same(endpoint('POST', sessionContext(false))['status'], 403);
            [$sid, $nonce] = sessionContext(true);
            same(endpoint('POST', [$sid, 'wrong-nonce'])['status'], 401);
            same(endpoint('POST', sessionContext(true, 'missing-installation'))['status'], 502);
            same(count(calls()), 0);
        },
        'testEndpointSuccessAndErrorCounters' => function (): void {
            $context = sessionContext(true);
            queueResponses([['status' => 429, 'headers' => ['X-Lognex-Retry-After' => '0']], ['status' => 200]]);
            $result = endpoint('POST', $context);
            same($result['status'], 200);
            same($result['body']['success'], true);
            same($result['body']['retries'], 1);
            same(count(calls()), 2);
            queueResponses([['status' => 429, 'headers' => ['X-Lognex-Retry-After' => '0']]]);
            $result = endpoint('POST', $context);
            same($result['status'], 502);
            same($result['body']['success'], false);
            same($result['body']['retries'], 10);
            queueResponses([['status' => 200, 'body' => 'invalid json']]);
            same(endpoint('POST', $context)['status'], 502);
        },
        'testSessionUnlockedWhileWaitingForRetry' => function () use ($appUrl): void {
            [$sid, $nonce] = sessionContext(true);
            queueResponses([['status' => 429, 'headers' => ['X-Lognex-Retry-After' => '700']], ['status' => 200]]);
            $worker = startWorker(['request', $appUrl . '/utils/stores.php', $sid, $nonce]);
            try {
                for ($i = 0; $i < 200 && count(calls()) === 0; $i++) {
                    usleep(5000);
                }
                check(count(calls()) > 0, 'Endpoint did not reach API');
                $probe = finishWorker(startWorker(['session', $sid]));
                check($probe['elapsed'] < 0.3, 'Session lock held during retry');
            } finally {
                $result = finishWorker($worker);
            }
            same($result['status'], 200);
        },
    ];

    foreach ($tests as $name => $test) {
        try {
            $test();
            $results[] = 'PASS ' . $name;
        } catch (Throwable $error) {
            $failed = true;
            $results[] = 'FAIL ' . $name . ': ' . $error->getMessage();
        }
    }
} finally {
    foreach ($servers as $server) {
        proc_terminate($server);
        proc_close($server);
    }
    if (!$failed) {
        foreach (glob($directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
echo implode("\n", $results) . "\n";
if ($failed) {
    fwrite(STDERR, 'Test logs: ' . $directory . "\n");
    exit(1);
}
