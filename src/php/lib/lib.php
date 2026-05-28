<?php

use \Firebase\JWT\JWT;

require_once __DIR__ . '/jwt.lib.php';

// Конфигурация

class AppConfig
{
    public string $appId = '';
    public string $appUid = '';
    public string $secretKey = '';
    public string $appBaseUrl = '';
    public string $databasePath = '';
    public string $encryptKey = '';

    public string $moyskladVendorApiEndpointUrl = 'https://apps-api.moysklad.ru/api/vendor/1.0';
    public string $moyskladJsonApiEndpointUrl = 'https://api.moysklad.ru/api/remap/1.2';

    public function __construct(array $cfg)
    {
        foreach ($cfg as $k => $v) {
            if (!property_exists($this, $k)) {
                continue;
            }

            $this->$k = ($v === null || $v === false) ? '' : (string)$v;
        }
    }
}

$cfg = new AppConfig(require(__DIR__ . '/../config.php'));

function dataDir(): string
{
    return __DIR__ . '/../data';
}

function appDatabasePath(): string
{
    $configuredPath = trim(cfg()->databasePath);

    return $configuredPath !== '' ? $configuredPath : dataDir() . '/app.sqlite';
}

function cfg(): AppConfig
{
    return $GLOBALS['cfg'];
}

function escHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeIsAdmin($rawIsAdmin): bool
{
    if (is_bool($rawIsAdmin)) {
        return $rawIsAdmin;
    }

    if (is_string($rawIsAdmin)) {
        return strtoupper(trim($rawIsAdmin)) === 'ALL';
    }

    return false;
}

function checkIsAdmin($employee): bool
{
    if (!is_object($employee) || !isset($employee->permissions) || !is_object($employee->permissions)) {
        return false;
    }

    if (!isset($employee->permissions->admin) || !is_object($employee->permissions->admin)) {
        return false;
    }

    return normalizeIsAdmin($employee->permissions->admin->view ?? null);
}

// Хранение пользовательского контекста в сессии.
// contextKey используется только для начальной загрузки entrypoint.
// Дальше backend-запросы авторизуются через contextNonce из активной PHP-сессии.

const USER_CONTEXT_SESSION_KEY = 'userContext';
const USER_CONTEXT_SESSION_TTL_SECONDS = 7200;

function ensureSessionStarted(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionOptions = [
        'gc_maxlifetime' => USER_CONTEXT_SESSION_TTL_SECONDS,
        'cookie_httponly' => true,
        'cookie_samesite' => 'None',
        'cookie_secure' => true,
    ];

    session_start($sessionOptions);
}

function saveActiveUserContextToSession(array $context): array
{
    ensureSessionStarted();

    $previous = normalizeActiveUserContext($_SESSION[USER_CONTEXT_SESSION_KEY] ?? null);
    $uid = trim((string)($context['uid'] ?? ''));
    $accountId = trim((string)($context['accountId'] ?? ''));
    $isAdmin = normalizeIsAdmin($context['isAdmin'] ?? false);

    if ($uid === '' || $accountId === '') {
        throw new InvalidArgumentException('User context requires uid and accountId');
    }

    $now = currentEpochMs();

    if ($previous
        && $previous['uid'] === $uid
        && $previous['accountId'] === $accountId
        && $previous['isAdmin'] === $isAdmin) {
        $contextNonce = $previous['contextNonce'];
        $createdAt = $previous['createdAt'];
    } else {
        $contextNonce = generateContextNonce();
        $createdAt = $now;
    }

    $activeContext = [
        'uid' => $uid,
        'fio' => (string)($context['fio'] ?? ''),
        'accountId' => $accountId,
        'isAdmin' => $isAdmin,
        'contextNonce' => $contextNonce,
        'createdAt' => $createdAt,
        'expiresAt' => $now + USER_CONTEXT_SESSION_TTL_SECONDS * 1000,
    ];

    $_SESSION[USER_CONTEXT_SESSION_KEY] = $activeContext;

    return $activeContext;
}

function loadActiveUserContextFromSession(): ?array
{
    ensureSessionStarted();

    $context = normalizeActiveUserContext($_SESSION[USER_CONTEXT_SESSION_KEY] ?? null);

    if ($context === null) {
        unset($_SESSION[USER_CONTEXT_SESSION_KEY]);
    }

    return $context;
}

function refreshActiveUserContextInSession(array $context): void
{
    ensureSessionStarted();

    $context['expiresAt'] = currentEpochMs() + USER_CONTEXT_SESSION_TTL_SECONDS * 1000;
    $_SESSION[USER_CONTEXT_SESSION_KEY] = $context;
}

function getContextNonceFromRequest(): ?string
{
    $contextNonce = requestBodyValue('contextNonce');

    if ($contextNonce === null) {
        return null;
    }

    $contextNonce = trim($contextNonce);

    return $contextNonce === '' ? null : $contextNonce;
}

function resolveBackendContextFromSession(): ?array
{
    $contextNonce = getContextNonceFromRequest();

    if ($contextNonce === null) {
        return null;
    }

    $context = loadActiveUserContextFromSession();

    if (!is_array($context) || $context['contextNonce'] !== $contextNonce) {
        return null;
    }

    refreshActiveUserContextInSession($context);

    $accountId = trim((string)($context['accountId'] ?? ''));
    $uid = trim((string)($context['uid'] ?? ''));

    if ($accountId === '' || $uid === '') {
        return null;
    }

    return [
        'accountId' => $accountId,
        'uid' => $uid,
        'isAdmin' => normalizeIsAdmin($context['isAdmin'] ?? false),
    ];
}

function requestBodyValue(string $name): ?string
{
    if (array_key_exists($name, $_POST)) {
        $value = scalarRequestValue($_POST[$name]);

        if ($value !== null) {
            return $value;
        }
    }

    $jsonBody = requestJsonBody();

    if (array_key_exists($name, $jsonBody)) {
        return scalarRequestValue($jsonBody[$name]);
    }

    return null;
}

function requestJsonBody(): array
{
    // php://input читаем и декодируем один раз на запрос: contextNonce и objectId могут понадобиться разным helper-ам.
    static $jsonBody = null;

    if ($jsonBody !== null) {
        return $jsonBody;
    }

    $jsonBody = [];
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');

    if (!isJsonContentType($contentType)) {
        return $jsonBody;
    }

    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return $jsonBody;
    }

    $decoded = json_decode((string)$rawBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message('WARN', 'Failed to decode JSON request body: ' . json_last_error_msg());

        return $jsonBody;
    }

    if (!is_array($decoded) || array_is_list($decoded)) {
        log_message('WARN', 'JSON request body must be an object');

        return $jsonBody;
    }

    $jsonBody = $decoded;

    return $jsonBody;
}

function isJsonContentType(string $contentType): bool
{
    $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

    return $mediaType === 'application/json';
}

function scalarRequestValue(mixed $value): ?string
{
    if (is_array($value) || is_object($value) || $value === null) {
        return null;
    }

    return trim((string)$value);
}

function normalizeActiveUserContext(mixed $value): ?array
{
    if (!is_array($value)) {
        return null;
    }

    $accountId = trim((string)($value['accountId'] ?? ''));
    $uid = trim((string)($value['uid'] ?? ''));
    $contextNonce = trim((string)($value['contextNonce'] ?? ''));

    if ($accountId === '' || $uid === '' || $contextNonce === '') {
        return null;
    }

    $now = currentEpochMs();
    $createdAt = is_int($value['createdAt'] ?? null) ? $value['createdAt'] : $now;
    $expiresAt = is_int($value['expiresAt'] ?? null) ? $value['expiresAt'] : $createdAt + USER_CONTEXT_SESSION_TTL_SECONDS * 1000;

    if ($expiresAt <= $now) {
        return null;
    }

    return [
        'uid' => $uid,
        'fio' => (string)($value['fio'] ?? ''),
        'accountId' => $accountId,
        'isAdmin' => normalizeIsAdmin($value['isAdmin'] ?? false),
        'contextNonce' => $contextNonce,
        'createdAt' => $createdAt,
        'expiresAt' => $expiresAt,
    ];
}

function generateContextNonce(): string
{
    return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
}

function currentEpochMs(): int
{
    return (int)floor(microtime(true) * 1000);
}

// Vendor API 1.0

class VendorApi
{

    function context(string $contextKey): mixed
    {
        return $this->request('POST', '/context/' . $contextKey);
    }

    function updateAppStatus(string $appId, string $accountId, string $status): mixed
    {
        return $this->request('PUT',
            "/apps/$appId/$accountId/status",
            "{\"status\": \"$status\"}");
    }

    private function request(string $method, string $path, mixed $body = null): mixed
    {
        return makeHttpRequest(
            $method,
            cfg()->moyskladVendorApiEndpointUrl . $path,
            buildJWT(),
            $body);
    }
}

function makeHttpRequest(string $method, string $url, string $bearerToken, mixed $data = null): mixed
{
    $curl = curl_init($url);

    $headers = ['Authorization: Bearer ' . $bearerToken, 'Accept-Encoding: gzip'];

    if ($data) {
        $headers[] = 'Content-type: application/json';
    }

    log_message('DEBUG', "Request: $method $url" . print_r($headers, true) . print_r($data, true));

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_ENCODING => '',
        CURLOPT_HEADER => true
    ];

    if ($method !== 'GET' && $data !== null) {
        $options[CURLOPT_POSTFIELDS] = is_array($data)
            ? http_build_query($data)
            : $data;
    }

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    $info = curl_getinfo($curl);

    curl_close($curl);

    if ($error) {
        log_message('ERROR', "Response error: $error");

        return null;
    }

    $statusCode = (int)($info['http_code'] ?? 0);
    $headerSize = (int)($info['header_size'] ?? 0);
    $body = substr((string)$response, $headerSize);

    log_message('DEBUG', "Response: $method $url\n$response");

    if ($statusCode >= 400) {
        log_message('WARN', "HTTP $statusCode for $method $url");

        return null;
    }

    if ($body === '') {
        return $statusCode >= 200 && $statusCode < 300;
    }

    $decoded = json_decode($body);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message('WARN', "Failed to decode JSON for $method $url: " . json_last_error_msg());

        return null;
    }

    return $decoded;
}

$vendorApi = new VendorApi();

function vendorApi(): VendorApi
{
    return $GLOBALS['vendorApi'];
}

function buildJWT(): string
{
    $token = [
        'sub' => cfg()->appUid,
        'iat' => time(),
        'exp' => time() + 300,
        'jti' => bin2hex(random_bytes(32)),
    ];

    return JWT::encode($token, cfg()->secretKey);
}

// JSON API 1.2

class JsonApi
{

    private string $accessToken;

    function __construct(string $accessToken)
    {
        if (empty($accessToken)) {
            throw new RuntimeException('JsonApi requires a valid access token. Reinstall the application.');
        }

        $this->accessToken = $accessToken;
    }

    function stores(): mixed
    {
        return makeHttpRequest(
            'GET',
            cfg()->moyskladJsonApiEndpointUrl . '/entity/store',
            $this->accessToken);
    }

    function getObject(string $entity, string $objectId): mixed
    {
        return makeHttpRequest(
            'GET',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/$entity/$objectId",
            $this->accessToken);
    }

}

function jsonApi(): JsonApi
{
    if (empty($GLOBALS['jsonApi'])) {
        $GLOBALS['jsonApi'] = new JsonApi(AppInstance::get()->accessToken ?? '');
    }

    return $GLOBALS['jsonApi'];
}

// Логирование

const LOG_LEVELS = [
    'DEBUG' => 1,
    'INFO' => 2,
    'WARN' => 3,
    'ERROR' => 4
];

function log_message(string $level, string $message): void
{
    if (LOG_LEVELS[$level] >= LOG_LEVELS[LOG_LEVEL]) {
        $message = redactSensitiveLogMessage($message);
        $log_entry = sprintf(
            "[%s][%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message
        );

        // Пишем логи в stderr для Docker.
        file_put_contents('php://stderr', $log_entry, FILE_APPEND);
    }
}

function redactSensitiveLogMessage(string $message): string
{
    $redacted = preg_replace('~(?i)((?:contextKey|contextNonce)=)([^&\s]+)~', '$1<redacted>', $message);
    if ($redacted !== null) {
        $message = $redacted;
    }

    $redacted = preg_replace('~(?i)(Authorization:\s*Bearer\s+)[^\s]+~', '$1<redacted>', $message);
    if ($redacted !== null) {
        $message = $redacted;
    }

    $redacted = preg_replace('~(/context/)[^/?#\s]+~', '$1<redacted>', $message);
    if ($redacted !== null) {
        $message = $redacted;
    }

    return $message;
}

// Состояние AppInstance

$currentAppInstance = null;

class AppInstance
{

    const UNKNOWN = 0;
    const SETTINGS_REQUIRED = 1;
    const SUSPENDED = 2;
    const ACTIVATED = 100;

    public string $appId;
    public string $accountId;
    public ?string $infoMessage = null;
    public ?string $store = null;

    public ?string $accessToken = null;

    public int $status = AppInstance::UNKNOWN;

    static function get(): AppInstance
    {
        $app = $GLOBALS['currentAppInstance'];

        if (!$app) {
            throw new InvalidArgumentException("There is no current app instance context");
        }

        return $app;
    }

    public function __construct(string $appId, string $accountId)
    {
        $this->appId = $appId;
        $this->accountId = $accountId;
    }

    function getStatusName(): ?string
    {
        return match ($this->status) {
            self::SETTINGS_REQUIRED => 'SettingsRequired',
            self::ACTIVATED => 'Activated',
            default => null,
        };
    }

    function persist(): void
    {
        appInstanceRepository()->persist($this);
    }

    function delete(): void
    {
        appInstanceRepository()->delete($this->appId, $this->accountId);
    }

    // Деактивирует решение, сохраняя настройки. Использовать при получении DELETE от Vendor API.
    function suspend(): void
    {
        appInstanceRepository()->deactivate($this->appId, $this->accountId);
    }

    static function loadApp(string $accountId): AppInstance
    {
        return self::load(cfg()->appId, $accountId);
    }

    static function load(string $appId, string $accountId): AppInstance
    {
        $app = appInstanceRepository()->load($appId, $accountId);

        $GLOBALS['currentAppInstance'] = $app;

        return $app;
    }
}

require_once __DIR__ . '/app-repo.php';
require_once __DIR__ . '/jwt-repo.php';

$appInstanceRepository = new AppInstanceSqliteRepository();
$jwtRepository = new JwtSqliteRepository();

function appInstanceRepository(): AppInstanceSqliteRepository
{
    return $GLOBALS['appInstanceRepository'];
}

function jwtRepository(): JwtSqliteRepository
{
    return $GLOBALS['jwtRepository'];
}
