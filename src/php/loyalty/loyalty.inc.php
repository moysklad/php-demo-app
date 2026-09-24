<?php

require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/repo.php';

// Модуль «Программа лояльности»: подключение Loyalty API к аккаунту и заглушка провайдера.
// Места подключения к общему коду помечены [feature:loyalty], карта модуля — в README.md рядом.

// Подключение программы лояльности на аккаунте. Настройки меняются в два шага: сначала решение
// запоминает ожидающий токен, потом передает его в МойСклад. Пока исход не подтвержден, провайдер
// принимает и действующий, и ожидающий токен — так неудачное переподключение не ломает кассу.
class LoyaltyInstallation
{
    public string $appId;
    public string $accountId;
    // Токен, который МойСклад принял через Vendor API. null — подключения еще не было.
    public ?string $providerToken = null;
    public bool $externalSearch = false;
    // Момент, когда Vendor API принял настройки. null означает, что МойСклад о подключении не знает.
    public ?string $connectedAt = null;
    // Настройки, отправленные в МойСклад, но еще не подтвержденные.
    public ?string $pendingProviderToken = null;
    public bool $pendingExternalSearch = false;

    public function __construct(string $appId, string $accountId)
    {
        $this->appId = $appId;
        $this->accountId = $accountId;
    }

    static function load(string $appId, string $accountId): ?LoyaltyInstallation
    {
        return loyaltyInstallationRepository()->load($appId, $accountId);
    }

    static function findByToken(string $token): ?LoyaltyInstallation
    {
        $token = trim($token);

        return $token === '' ? null : loyaltyInstallationRepository()->findByToken($token);
    }

    function isConnected(): bool
    {
        return $this->connectedAt !== null;
    }

    function acceptsToken(string $token): bool
    {
        foreach ([$this->providerToken, $this->pendingProviderToken] as $candidate) {
            if ($candidate !== null && hash_equals($candidate, $token)) {
                return true;
            }
        }

        return false;
    }

    function allowsExternalSearch(): bool
    {
        return $this->externalSearch || ($this->pendingProviderToken !== null && $this->pendingExternalSearch);
    }

    function beginUpdate(?string $providerToken, bool $externalSearch): void
    {
        $this->pendingProviderToken = $providerToken ?? $this->providerToken ?? bin2hex(random_bytes(32));
        $this->pendingExternalSearch = $externalSearch;
    }

    function confirmUpdate(): void
    {
        $this->providerToken = $this->pendingProviderToken;
        $this->externalSearch = $this->pendingExternalSearch;
        $this->connectedAt = gmdate('c');
        $this->discardUpdate();
    }

    function discardUpdate(): void
    {
        $this->pendingProviderToken = null;
        $this->pendingExternalSearch = false;
    }

    // МойСклад удаляет настройки лояльности вместе с решением, поэтому после повторной установки
    // их нужно передать заново. Токен при этом сохраняется.
    function markDisconnected(): void
    {
        $this->connectedAt = null;
        $this->discardUpdate();
    }

    function persist(): void
    {
        loyaltyInstallationRepository()->persist($this);
    }
}

class LoyaltyInstallationSqliteRepository extends SqliteRepository
{
    public function load(string $appId, string $accountId): ?LoyaltyInstallation
    {
        $stmt = $this->connection()->prepare(
            'SELECT application_id, account_id, provider_token, external_search, connected_at,
                pending_provider_token, pending_external_search
            FROM loyalty_installation
            WHERE application_id = :application_id AND account_id = :account_id'
        );

        $stmt->execute([
            ':application_id' => $appId,
            ':account_id' => $accountId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->fromRow($row);
    }

    // Токен хранится зашифрованным, поэтому искать по нему приходится перебором.
    // Для демо это приемлемо; в продакшене храните рядом хеш токена и ищите по нему.
    public function findByToken(string $token): ?LoyaltyInstallation
    {
        $stmt = $this->connection()->query(
            'SELECT application_id, account_id, provider_token, external_search, connected_at,
                pending_provider_token, pending_external_search
            FROM loyalty_installation'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $installation = $this->fromRow($row);

            if ($installation->acceptsToken($token)) {
                return $installation;
            }
        }

        return null;
    }

    public function persist(LoyaltyInstallation $installation): void
    {
        $stmt = $this->connection()->prepare(
            'INSERT INTO loyalty_installation (
                application_id,
                account_id,
                provider_token,
                external_search,
                connected_at,
                pending_provider_token,
                pending_external_search,
                created_at,
                updated_at
            ) VALUES (
                :application_id,
                :account_id,
                :provider_token,
                :external_search,
                :connected_at,
                :pending_provider_token,
                :pending_external_search,
                :created_at,
                :updated_at
            )
            ON CONFLICT(application_id, account_id) DO UPDATE SET
                provider_token = excluded.provider_token,
                external_search = excluded.external_search,
                connected_at = excluded.connected_at,
                pending_provider_token = excluded.pending_provider_token,
                pending_external_search = excluded.pending_external_search,
                updated_at = excluded.updated_at'
        );

        $timestamp = gmdate('c');

        $stmt->execute([
            ':application_id' => $installation->appId,
            ':account_id' => $installation->accountId,
            ':provider_token' => $this->encryptSecret($installation->providerToken),
            ':external_search' => $installation->externalSearch ? 1 : 0,
            ':connected_at' => $installation->connectedAt,
            ':pending_provider_token' => $this->encryptSecret($installation->pendingProviderToken),
            ':pending_external_search' => $installation->pendingExternalSearch ? 1 : 0,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }

    protected function initializeSchema(PDO $pdo): void
    {
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS loyalty_installation (
                application_id TEXT NOT NULL,
                account_id TEXT NOT NULL,
                provider_token TEXT, -- зашифрован APP_ENCRYPT_KEY; NULL: подключения еще не было
                external_search INTEGER NOT NULL DEFAULT 0,
                connected_at TEXT, -- NULL: МойСклад не знает о подключении
                pending_provider_token TEXT, -- зашифрован APP_ENCRYPT_KEY; NULL: нет неподтвержденных настроек
                pending_external_search INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                PRIMARY KEY (application_id, account_id)
            )'
        );
    }

    private function fromRow(array $row): LoyaltyInstallation
    {
        $installation = new LoyaltyInstallation((string)$row['application_id'], (string)$row['account_id']);
        $installation->providerToken = $this->decryptSecret($row['provider_token'], 'provider_token');
        $installation->externalSearch = (bool)$row['external_search'];
        $installation->connectedAt = $row['connected_at'] ?? null;
        $installation->pendingProviderToken = $this->decryptSecret($row['pending_provider_token'], 'pending_provider_token');
        $installation->pendingExternalSearch = (bool)$row['pending_external_search'];

        return $installation;
    }
}

$loyaltyInstallationRepository = new LoyaltyInstallationSqliteRepository();

function loyaltyInstallationRepository(): LoyaltyInstallationSqliteRepository
{
    return $GLOBALS['loyaltyInstallationRepository'];
}

/**
 * Состояние подключения для вкладки «Программа лояльности».
 * Подключение опционально: на статус решения (SettingsRequired/Activated) оно не влияет.
 *
 * @return array{state: string, badge: string, title: string, details: string, externalSearch: bool}
 */
function describeLoyaltyConnection(?LoyaltyInstallation $installation): array
{
    if ($installation === null || $installation->providerToken === null) {
        return [
            'state' => 'not-connected',
            'badge' => 'orange',
            'title' => 'Программа лояльности не подключена',
            'details' => 'Передайте адрес и токен вашего Loyalty API через Vendor API, чтобы МойСклад начал обращаться к программе лояльности.',
            'externalSearch' => false,
        ];
    }

    if (!$installation->isConnected()) {
        return [
            'state' => 'reconnect-required',
            'badge' => 'orange',
            'title' => 'Требуется повторное подключение',
            'details' => 'Решение переустанавливали: МойСклад удалил настройки лояльности вместе с решением. Токен сохранен, отправьте настройки заново.',
            'externalSearch' => $installation->externalSearch,
        ];
    }

    return [
        'state' => 'connected',
        'badge' => 'green',
        'title' => 'Программа лояльности подключена',
        'details' => $installation->externalSearch
            ? 'Внешний поиск покупателей включен: МойСклад ищет покупателей через ваш Loyalty API.'
            : 'Внешний поиск покупателей выключен: МойСклад ищет покупателей в своей базе.',
        'externalSearch' => $installation->externalSearch,
    ];
}

function defaultLoyaltyProviderUrl(): string
{
    return rtrim(cfg()->appBaseUrl, '/') . '/loyalty/provider.php';
}

/**
 * Данные вкладки для основного iframe (src/php/entry/iframe.inc.php).
 */
function loyaltyIframePageData(string $accountId): array
{
    return [
        'loyalty' => describeLoyaltyConnection(LoyaltyInstallation::load(cfg()->appId, $accountId)),
        'defaultLoyaltyProviderUrl' => defaultLoyaltyProviderUrl(),
    ];
}

// Жизненный цикл решения (вызывается из src/php/api/vendor-endpoint.php).
// МойСклад удаляет настройки лояльности при Uninstall, поэтому признак подключения сбрасывается
// и при удалении, и при повторной установке. Suspend настройки не удаляет — на него модуль не реагирует.

function loyaltyOnInstall(string $appId, string $accountId): void
{
    resetLoyaltyConnection($appId, $accountId);
}

function loyaltyOnUninstall(string $appId, string $accountId): void
{
    resetLoyaltyConnection($appId, $accountId);
}

function resetLoyaltyConnection(string $appId, string $accountId): void
{
    $installation = LoyaltyInstallation::load($appId, $accountId);

    if ($installation === null || (!$installation->isConnected() && $installation->pendingProviderToken === null)) {
        return;
    }

    $installation->markDisconnected();
    $installation->persist();
    log_message('INFO', "Loyalty connection reset for appId=$appId on accountId=$accountId");
}

/**
 * Передает настройки Loyalty API в МойСклад: PUT /apps/{appId}/{accountId}/loyalty.
 *
 * outcome: ok; rejected — МойСклад точно не принял настройки; unknown — ответа нет или 5xx, настройки могли примениться.
 *
 * @return array{outcome: string, code: ?int, message: string}
 */
function updateLoyaltySettings(string $appId, string $accountId, string $url, string $token, bool $externalSearch): array
{
    // Тело содержит токен провайдера, поэтому в лог его не пишем.
    $response = makeHttpRequestDetailed(
        'PUT',
        cfg()->moyskladVendorApiEndpointUrl . "/apps/$appId/$accountId/loyalty",
        buildJWT(),
        json_encode(['url' => $url, 'token' => $token, 'externalSearch' => $externalSearch]),
        false);

    if ($response['ok']) {
        return ['outcome' => 'ok', 'code' => null, 'message' => ''];
    }

    if ($response['status'] === 0 || $response['status'] >= 500) {
        return ['outcome' => 'unknown', 'code' => null, 'message' => 'Vendor API не ответил или ответил ошибкой сервера'];
    }

    $error = is_object($response['body']) && isset($response['body']->errors[0]) ? $response['body']->errors[0] : null;

    if (!is_object($error) || empty($error->error)) {
        return ['outcome' => 'rejected', 'code' => null, 'message' => "Vendor API ответил статусом {$response['status']}"];
    }

    $code = isset($error->code) && is_int($error->code) ? $error->code : null;
    $hint = loyaltyVendorApiErrorHint($code);

    return ['outcome' => 'rejected', 'code' => $code, 'message' => $hint ? "{$error->error}. $hint" : (string)$error->error];
}

function loyaltyVendorApiErrorHint(?int $code): ?string
{
    return match ($code) {
        2004 => 'Проверьте, что решение установлено на этом аккаунте и APP_ID совпадает с решением в кабинете вендора',
        2006 => 'Добавьте элемент <loyaltyApi/> в дескриптор решения в кабинете вендора',
        2007 => 'Дождитесь, пока решение завершит установку, и повторите попытку',
        default => null,
    };
}

// Демонстрационная база покупателей для внешнего поиска.
// ВНИМАНИЕ! Поля id и msId МойСклад разбирает как UUID, произвольные строки не пройдут.
// msId заполняется только для покупателей, которые уже заведены в МоемСкладе.
const LOYALTY_DEMO_CUSTOMERS = [
    [
        'id' => '7c3b1a52-2f4d-4f0a-9a6c-2c9f5f0b1d11',
        'name' => 'Иванов Иван',
        'discountCardNumber' => '1000000000001',
        'phone' => '+79000000001',
        'email' => 'ivanov@example.com',
        'legalFirstName' => 'Иван',
        'legalLastName' => 'Иванов',
        'sex' => 'MALE',
    ],
    [
        'id' => '8d4c2b63-3a5e-4b1b-8b7d-3daf6a1c2e22',
        'name' => 'Петрова Мария',
        'discountCardNumber' => '1000000000002',
        'phone' => '+79000000002',
        'email' => 'petrova@example.com',
        'legalFirstName' => 'Мария',
        'legalLastName' => 'Петрова',
        'sex' => 'FEMALE',
    ],
    [
        'id' => '9e5d3c74-4b6f-4c2c-9c8e-4ebf7b2d3f33',
        'name' => 'Сидоров Петр',
        'discountCardNumber' => '1000000000003',
        'phone' => '+79000000003',
        'email' => 'sidorov@example.com',
        'legalFirstName' => 'Петр',
        'legalLastName' => 'Сидоров',
        'sex' => 'MALE',
    ],
];

// Поиск по подстроке в имени, номере карты, телефоне или email; пустая строка возвращает всех.
function findLoyaltyDemoCustomers(string $search): array
{
    $query = mb_strtolower(trim($search));

    if ($query === '') {
        return LOYALTY_DEMO_CUSTOMERS;
    }

    return array_values(array_filter(LOYALTY_DEMO_CUSTOMERS, function (array $customer) use ($query) {
        foreach (['name', 'discountCardNumber', 'phone', 'email'] as $field) {
            if (str_contains(mb_strtolower($customer[$field]), $query)) {
                return true;
            }
        }

        return false;
    }));
}
