<?php

// Самодостаточные интеграционные тесты: curl, SQLite и отдельные PHP-процессы.
$directory = sys_get_temp_dir() . '/php-demo-tests-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
putenv('APP_DB_PATH=' . $directory . '/app.sqlite');
putenv('APP_ENCRYPT_KEY=' . str_repeat('0', 64));
putenv('APP_ID=test-app');
putenv('APP_UID=test-app.moysklad');
putenv('APP_SECRET_KEY=' . str_repeat('s', 64));
putenv('TEST_QUEUE=' . $directory . '/queue.json');
ini_set('session.save_path', $directory);
require __DIR__ . '/../src/php/lib/lib.php';
// [feature:scenario] действия в сценариях
require __DIR__ . '/../src/php/scenario/scenario.inc.php';

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

function endpoint(string $method, ?array $context = null, string $path = '/utils/stores.php'): array
{
    global $appUrl;
    $curl = curl_init($appUrl . $path);
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

// Запрос МоегоСклада к Vendor API решения под service JWT; без $authorized — без заголовка Authorization.
function vendorRequest(string $method, string $path, ?array $body = null, bool $authorized = true): array
{
    global $appUrl;
    $curl = curl_init($appUrl . '/api/vendor-endpoint.php/api/moysklad/vendor/1.0/apps/' . $path);
    $headers = ['Content-Type: application/json'];
    if ($authorized) {
        $headers[] = 'Authorization: Bearer ' . buildJWT();
    }
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body === null ? '' : json_encode($body)]);
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ['status' => $status, 'raw' => $raw, 'body' => $raw === '' ? null : json_decode($raw, true)];
}

function scenarioRequest(string $actionName, array $overrides = []): array
{
    return ['actionName' => $actionName, 'actionType' => 'ADD', 'objectId' => '624b7f4d-9c1b-11ef-0a83-18f5000001ec',
        'objectType' => 'CustomerOrder', ...$overrides];
}

function chatJournal(array $context): array
{
    $result = endpoint('POST', $context, '/scenario/events.php');
    same($result['status'], 200);
    return $result['body'];
}

// JSON-запрос из браузера решения (user-context, connect.php); $sid — cookie PHP-сессии, в ответе — её же id из Set-Cookie.
function jsonRequest(string $path, array $body, ?string $sid = null): array
{
    global $appUrl;
    $curl = curl_init($appUrl . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_COOKIE => $sid === null ? '' : 'PHPSESSID=' . $sid]);
    $raw = (string)curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);
    preg_match('/PHPSESSID=([^;]+)/', substr($raw, 0, $headerSize), $cookie);
    return ['status' => $status, 'body' => json_decode(substr($raw, $headerSize), true), 'sid' => $cookie[1] ?? $sid];
}

function providerRequest(string $method, string $path, string $token): int
{
    global $appUrl;
    $curl = curl_init($appUrl . '/loyalty/provider.php' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Lognex-Discount-API-Auth-Token: ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => '{}']);
    curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return $status;
}

function zeusUser(string $role = 'admin'): string
{
    return json_encode(['accountId' => 'account', 'userId' => 'user-id', 'userUid' => 'user', 'role' => $role]);
}

function page(string $path): array
{
    global $appUrl;
    $curl = curl_init($appUrl . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 10]);
    $raw = (string)curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);
    preg_match('/PHPSESSID=([^;]+)/', substr($raw, 0, $headerSize), $cookie);
    return ['status' => $status, 'body' => substr($raw, $headerSize), 'sid' => $cookie[1] ?? null];
}

try {
    $apiUrl = startServer([__DIR__ . '/mock-api.php']);
    // Один mock отвечает и за JSON API, и за Vendor API: ответы берутся из очереди по порядку.
    putenv('MOYSKLAD_JSON_API_URL=' . $apiUrl);
    putenv('MOYSKLAD_VENDOR_API_URL=' . $apiUrl);
    cfg()->moyskladJsonApiEndpointUrl = $apiUrl;
    cfg()->moyskladVendorApiEndpointUrl = $apiUrl;
    $appUrl = startServer(['-t', __DIR__ . '/../src/php']);
    $app = new AppInstance(cfg()->appId, 'account');
    $app->accessToken = 'test-token';
    $app->persist();

    $tests = [
        // Жизненный цикл решения через Vendor endpoint: настройки переживают Suspend и Uninstall.
        'testVendorLifecycleKeepsSettings' => function (): void {
            $path = 'test-app/lifecycle';
            $install = ['appUid' => 'test-app.moysklad', 'cause' => 'Install', 'access' => [['access_token' => 'token-1']]];
            same(vendorRequest('PUT', $path, $install, false)['status'], 401);
            same(vendorRequest('PUT', $path, $install)['body'], ['status' => 'SettingsRequired']);
            $app = AppInstance::load('test-app', 'lifecycle');
            same($app->accessToken, 'token-1');
            $app->store = 'Основной склад';
            $app->status = AppInstance::ACTIVATED;
            $app->persist();

            same(vendorRequest('DELETE', $path, ['cause' => 'Suspend'])['status'], 200);
            check(empty(AppInstance::load('test-app', 'lifecycle')->accessToken), 'Access token kept after Suspend');
            // Повторный DELETE для уже приостановленного решения — 204, как требует Vendor API.
            same(vendorRequest('DELETE', $path, ['cause' => 'Suspend'])['status'], 204);
            same(vendorRequest('PUT', $path, ['appUid' => 'test-app.moysklad', 'cause' => 'Resume', 'access' => [['access_token' => 'token-2']]])['body'], ['status' => 'Activated']);
            same(AppInstance::load('test-app', 'lifecycle')->accessToken, 'token-2');

            same(vendorRequest('DELETE', $path, ['cause' => 'Uninstall'])['status'], 200);
            same(vendorRequest('DELETE', $path, ['cause' => 'Pause'])['status'], 204);
            same(vendorRequest('PUT', $path, $install)['body'], ['status' => 'Activated']);
            same(AppInstance::load('test-app', 'lifecycle')->store, 'Основной склад');
            same(vendorRequest('DELETE', $path, ['cause' => 'Pause'])['status'], 400);
        },
        // Обмен одноразового токена: контекст и pageData в ответе, токен в сессию не попадает, ошибки Zeus без тела.
        'testUserContextExchange' => function (): void {
            same(jsonRequest('/entry/user-context.php', [])['status'], 400);
            queueResponses([['status' => 200, 'body' => zeusUser()], ['status' => 200, 'body' => '{"rows":[{"name":"Основной склад"}]}']]);
            $result = jsonRequest('/entry/user-context.php', ['token' => 'opaque-token', 'page' => 'iframe']);
            same($result['status'], 200);
            same($result['body']['user']['isAdmin'], true);
            same($result['body']['pageData']['contextNonce'], $result['body']['contextNonce']);
            same($result['body']['pageData']['storesValues'], ['Основной склад']);
            same($result['body']['pageData']['status']['badge'], 'orange');
            check(!str_contains(json_encode($result['body']), 'opaque-token'), 'Token leaked to response');
            same(count(calls()), 2);
            // Тот же пользователь при повторном обмене получает прежний contextNonce.
            queueResponses([['status' => 200, 'body' => zeusUser()]]);
            same(jsonRequest('/entry/user-context.php', ['token' => 'opaque-token-2'], $result['sid'])['body']['contextNonce'], $result['body']['contextNonce']);
            // 401 от Zeus — битый service JWT решения, клиенту отдается как 502; 404 с кодом пробрасывается как есть.
            queueResponses([['status' => 401, 'body' => '{"errors":[{"code":1056}]}']]);
            same(jsonRequest('/entry/user-context.php', ['token' => 'opaque-token'])['status'], 502);
            queueResponses([['status' => 404, 'body' => '{"errors":[{"code":3007}]}']]);
            $notFound = jsonRequest('/entry/user-context.php', ['token' => 'opaque-token']);
            same($notFound['status'], 404);
            same($notFound['body']['code'], '3007');
        },
        // [feature:loyalty] подключение Loyalty API: ожидающий токен до подтверждения, провайдер принимает действующий.
        'testLoyaltyConnectAndProvider' => function (): void {
            [$sid, $nonce] = sessionContext(true, 'loyalty');
            queueResponses([['status' => 200]]);
            $providerUrl = 'http://localhost/loyalty/provider.php';
            $connected = jsonRequest('/loyalty/connect.php', ['contextNonce' => $nonce, 'providerUrl' => $providerUrl, 'providerToken' => 'token-1', 'externalSearch' => false], $sid);
            same($connected['status'], 200);
            same($connected['body']['loyalty']['state'], 'connected');
            same(providerRequest('GET', '/counterparty?search=' . rawurlencode('Иван'), 'token-1'), 404);
            same(providerRequest('POST', '/counterparty/detail', 'wrong'), 401);

            // Явный отказ Vendor API: ожидающий токен удаляется, действующее подключение остается.
            queueResponses([['status' => 400, 'body' => '{"errors":[{"error":"Нет loyaltyApi","code":2006}]}']]);
            $rejected = jsonRequest('/loyalty/connect.php', ['contextNonce' => $nonce, 'providerUrl' => $providerUrl, 'providerToken' => 'token-2'], $sid);
            same($rejected['status'], 502);
            check(str_contains($rejected['body']['message'], '2006'), 'No Vendor API error code in message');
            same(providerRequest('POST', '/counterparty/detail', 'token-1'), 200);
            same(providerRequest('POST', '/counterparty/detail', 'token-2'), 401);

            // Исход неизвестен (5xx без повтора): провайдер принимает оба токена, пока подключение не подтверждено.
            queueResponses([['status' => 500]]);
            same(jsonRequest('/loyalty/connect.php', ['contextNonce' => $nonce, 'providerUrl' => $providerUrl, 'providerToken' => 'token-2', 'externalSearch' => true], $sid)['status'], 502);
            same(providerRequest('GET', '/counterparty?search=' . rawurlencode('Иван'), 'token-2'), 200);
            same(providerRequest('POST', '/counterparty/detail', 'token-1'), 200);
            same(providerRequest('POST', '/counterparty/detail', 'token-2'), 200);

            // Другой токен до разрешения исхода не принимается; отказ повтора не теряет токен с неизвестным исходом.
            same(jsonRequest('/loyalty/connect.php', ['contextNonce' => $nonce, 'providerUrl' => $providerUrl, 'providerToken' => 'token-3'], $sid)['status'], 409);
            same(providerRequest('POST', '/counterparty/detail', 'token-3'), 401);
            queueResponses([['status' => 400, 'body' => '{"errors":[{"error":"Решение еще устанавливается","code":2007}]}']]);
            same(jsonRequest('/loyalty/connect.php', ['contextNonce' => $nonce, 'providerUrl' => $providerUrl, 'externalSearch' => false], $sid)['status'], 502);
            same(providerRequest('GET', '/counterparty?search=' . rawurlencode('Иван'), 'token-2'), 200);
            queueResponses([['status' => 200]]);
            same(jsonRequest('/loyalty/connect.php', ['contextNonce' => $nonce, 'providerUrl' => $providerUrl], $sid)['status'], 200);
            same(providerRequest('POST', '/counterparty/detail', 'token-1'), 401);
            same(providerRequest('POST', '/counterparty/detail', 'token-2'), 200);

            [$userSid, $userNonce] = sessionContext(false, 'loyalty');
            same(jsonRequest('/loyalty/connect.php', ['contextNonce' => $userNonce, 'providerUrl' => $providerUrl, 'providerToken' => 'token-3'], $userSid)['status'], 403);
        },
        // HTTP-клиент: ретраи по X-Lognex-Retry-After и общая очередь запросов JSON API в SQLite.
        'testRetryAfterAndCount' => function () use ($apiUrl): void {
            queueResponses([['status' => 429, 'headers' => ['x-LoGnEx-ReTrY-AfTeR' => '40']], ['status' => 200]]);
            $result = makeHttpRequestDetailed('GET', $apiUrl, 'retry-token', rateLimited: true);
            same($result['status'], 200);
            same($result['retries'], 1);
            same($result['body']->rows, []);
            same(count(calls()), 2);
            check(calls()[1]['at'] - calls()[0]['at'] >= 0.035, 'Retry ignored milliseconds header');

            // Запрос с бюджетом не ждёт слот, который откроется позже дедлайна: он не отправляется вовсе.
            queueResponses([['status' => 429, 'headers' => ['X-Lognex-Retry-After' => '11000']]]);
            same(makeHttpRequestDetailed('POST', $apiUrl, 'retry-token', [], rateLimited: true)['status'], 429);
            same(makeHttpRequestDetailed('GET', $apiUrl, 'retry-token', rateLimited: true, budgetMs: 5000)['status'], 0);
            same(count(calls()), 1);
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
            foreach (['-1', '1.5', '20abc', '', '99999999999999999999999999'] as $value) {
                same(nonNegativeIntegerHeader(['header' => $value], 'header'), null);
            }
            same(nonNegativeIntegerHeader(['header' => '30'], 'header'), 30);
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
        // Gate живет в SQLite: его видят все PHP workers, чужой токен и API не ждут, простой сбрасывает очередь.
        'testGateIsSharedBetweenWorkersPerTokenAndApi' => function (): void {
            $gate = new LognexRateLimitGate('api', 'token');
            $gate->observe(429, ['x-lognex-retry-after' => '1000']);
            check((new LognexRateLimitGate('api', 'token'))->reserveDelay() > 800, 'Gate not shared');
            same((new LognexRateLimitGate('api', 'other'))->reserveDelay(), 0);
            same((new LognexRateLimitGate('other-api', 'token'))->reserveDelay(), 0);
            $pdo = new PDO('sqlite:' . appDatabasePath());
            $pdo->exec('UPDATE http_rate_limit SET last_used_at = 0, not_before = 0, spacing_ms = 1000');
            same($gate->reserveDelay(), 0);

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
        // [feature:scenario] действия в сценариях и окно чатов
        'testScenarioRequiresVendorAuth' => function (): void {
            same(vendorRequest('POST', 'test-app/account/scenario', scenarioRequest('log-event'), false)['status'], 401);
            same(vendorRequest('POST', 'test-app/account/unknown', [])['status'], 404);
        },
        'testScenarioLogEventIsRecordedForChatWindow' => function (): void {
            $result = vendorRequest('POST', 'test-app/scenario-log/scenario', scenarioRequest('log-event', ['actionType' => 'MODIFY']));
            same($result['status'], 200);
            same($result['raw'], '');
            $journal = chatJournal(sessionContext(false, 'scenario-log'));
            same(array_column($journal['scenarioActions'], 'name'), ['log-event', 'fetch-object', 'reject']);
            same(count($journal['scenarioEvents']), 1);
            $event = $journal['scenarioEvents'][0];
            same($event['actionName'], 'log-event');
            same($event['actionTitle'], 'Записать событие в журнал решения');
            same($event['actionType'], 'MODIFY');
            same($event['objectType'], 'CustomerOrder');
            same($event['outcome'], 'ok');
            check($event['createdAt'] !== '', 'createdAt is empty');
        },
        'testScenarioRejectReturnsErrorForUser' => function (): void {
            $result = vendorRequest('POST', 'test-app/scenario-reject/scenario', scenarioRequest('reject'));
            same($result['status'], 400);
            same($result['body']['error']['code'], 1001);
            check(str_contains($result['body']['error']['errorMessage'], 'CustomerOrder 624b7f4d-9c1b-11ef-0a83-18f5000001ec'), 'No object in message');
            $event = chatJournal(sessionContext(false, 'scenario-reject'))['scenarioEvents'][0];
            same($event['outcome'], 'rejected');
            same($event['message'], $result['body']['error']['errorMessage']);
        },
        'testScenarioFetchObjectStoresObjectName' => function (): void {
            queueResponses([['status' => 200, 'body' => '{"id":"624b7f4d","name":"00042"}']]);
            $result = vendorRequest('POST', 'test-app/account/scenario', scenarioRequest('fetch-object'));
            same($result['status'], 200);
            check(str_ends_with(calls()[0]['url'], '/entity/customerorder/624b7f4d-9c1b-11ef-0a83-18f5000001ec'), 'Wrong JSON API url: ' . calls()[0]['url']);
            $event = chatJournal(sessionContext(false))['scenarioEvents'][0];
            same($event['outcome'], 'ok');
            same($event['objectName'], '00042');
        },
        'testScenarioFetchObjectFailureAsksForRetryOnlyWhenItCanHelp' => function (): void {
            queueResponses([['status' => 429, 'headers' => ['x-lognex-retry-after' => '40'], 'body' => '{"errors":[]}']]);
            same(vendorRequest('POST', 'test-app/account/scenario', scenarioRequest('fetch-object'))['status'], 502);
            same(count(calls()), 1);
            queueResponses([['status' => 404, 'body' => '{"errors":[]}']]);
            $notFound = vendorRequest('POST', 'test-app/account/scenario', scenarioRequest('fetch-object', ['objectType' => 'Company']));
            same($notFound['status'], 400);
            check(str_contains($notFound['body']['error']['errorMessage'], '404'), 'No JSON API status in message');
            check(str_ends_with(calls()[0]['url'], '/entity/counterparty/624b7f4d-9c1b-11ef-0a83-18f5000001ec'), 'Wrong JSON API url: ' . calls()[0]['url']);
            same(array_slice(array_column(chatJournal(sessionContext(false))['scenarioEvents'], 'outcome'), 0, 2), ['failed', 'failed']);
            // Без access token (решение не установлено) объект тоже не получить.
            same(vendorRequest('POST', 'test-app/scenario-missing/scenario', scenarioRequest('fetch-object'))['status'], 502);
        },
        'testScenarioRejectsUnknownActionAndIncompleteBody' => function (): void {
            $unknown = vendorRequest('POST', 'test-app/scenario-bad/scenario', scenarioRequest('explode'));
            same($unknown['status'], 400);
            check(str_contains($unknown['body']['error']['errorMessage'], 'explode'), 'No action name in message');
            same(vendorRequest('POST', 'test-app/scenario-bad/scenario', ['actionName' => 'log-event'])['status'], 400);
            same(chatJournal(sessionContext(false, 'scenario-bad'))['scenarioEvents'], []);
        },
        'testScenarioJournalKeepsLatestEventsFirst' => function (): void {
            for ($i = 0; $i < 3; $i++) {
                same(vendorRequest('POST', 'test-app/scenario-order/scenario', scenarioRequest('log-event', ['objectId' => "object-$i"]))['status'], 200);
            }
            $events = chatJournal(sessionContext(false, 'scenario-order'))['scenarioEvents'];
            same(array_column($events, 'objectId'), ['object-2', 'object-1', 'object-0']);
        },
        'testScenarioJournalRequiresContextNonce' => function (): void {
            same(endpoint('GET', null, '/scenario/events.php')['status'], 405);
            same(endpoint('POST', null, '/scenario/events.php')['status'], 401);
            [$sid, $nonce] = sessionContext(false);
            same(endpoint('POST', [$sid, 'stale'], '/scenario/events.php')['status'], 401);
        },
        'testChatWindowIsServedWithoutUserContext' => function (): void {
            $result = page('/entry/iframe-chat.php');
            same($result['status'], 200);
            check(str_contains($result['body'], '../assets/entry/chat.js'), 'Chat bundle is not referenced');
            check(!str_contains($result['body'], 'account'), 'Chat shell leaks account data');
        },
        // Мобильное окно: contextKey из URL меняется на PHP-сессию, без ключа или с протухшим ключом — 401 с пояснением.
        'testMobileWindowExchangesContextKey' => function (): void {
            $employee = ['uid' => 'admin@demo', 'shortFio' => 'Иванов И.', 'accountId' => 'account',
                'permissions' => ['admin' => ['view' => 'ALL']]];
            queueResponses([['status' => 200, 'body' => json_encode($employee)]]);
            $result = page('/entry/iframe-mobile.php?contextKey=context-key-1');
            same($result['status'], 200);
            same(array_map(fn(array $call): string => $call['method'] . ' ' . $call['url'], calls()), ['POST /context/context-key-1']);
            check(!str_contains($result['body'], 'context-key-1'), 'contextKey leaked to page');
            check(str_contains($result['body'], 'admin@demo (Иванов И.)'), 'No user in page');
            check(str_contains($result['body'], 'администратор аккаунта'), 'Admin permission ignored');
            check(str_contains($result['body'], '../assets/mobile/iframe-mobile.js'), 'Mobile script is not referenced');
            check(preg_match('/name="contextNonce" value="([^"]+)"/', $result['body'], $nonce) === 1, 'No contextNonce in page');
            same(endpoint('POST', [$result['sid'], $nonce[1]], '/scenario/events.php')['status'], 200);

            queueResponses([['status' => 404, 'body' => '{"errors":[{"code":3007}]}']]);
            $expired = page('/entry/iframe-mobile.php?contextKey=expired-key');
            same($expired['status'], 401);
            check(str_contains($expired['body'], 'откройте его заново из мобильного приложения'), 'No expired key hint');
            check(!str_contains($expired['body'], 'contextNonce'), 'contextNonce on error page');

            queueResponses([['status' => 200]]);
            $missing = page('/entry/iframe-mobile.php');
            same($missing['status'], 401);
            check(str_contains($missing['body'], 'нет параметра contextKey'), 'No missing key hint');
            same(calls(), []);
        },
        'testDescriptorContainsChatWindowAndScenarioActions' => function (): void {
            $descriptor = page('/utils/generate-descriptor.php')['body'];
            check(str_contains($descriptor, '<iframe type="main" sourceUrl="/entry/iframe.php" useContextKey="false">'), 'No main iframe');
            check(str_contains($descriptor, '<iframe type="chat" sourceUrl="/entry/iframe-chat.php" useContextKey="false">'), 'No chat iframe');
            check(str_contains($descriptor, '<iframe type="mobile" sourceUrl="/entry/iframe-mobile.php"/>'), 'No mobile iframe');
            same(substr_count($descriptor, '<user-context/>'), 4);
            check(str_contains($descriptor, '<action name="log-event" title="Записать событие в журнал решения"/>'), 'No log-event action');
            check(str_contains($descriptor, '<action name="reject" title="Отклонить с сообщением пользователю"/>'), 'No reject action');
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
