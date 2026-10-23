<?php

require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/../lib/repo.php';

// Модуль «Действия в сценариях»: обработчик POST .../apps/{appId}/{accountId}/scenario и журнал
// срабатываний для окна чатов. Места подключения к общему коду помечены [feature:scenario],
// карта модуля — в README.md рядом.

// Действия, которые решение объявляет в блоке <scenario> дескриптора. name уходит в actionName
// запроса МоегоСклада, title видит администратор на странице настройки сценария.
const SCENARIO_ACTIONS = [
    'log-event' => 'Записать событие в журнал решения',
    'fetch-object' => 'Запросить объект через JSON API',
    'reject' => 'Отклонить с сообщением пользователю',
];

// Сколько последних событий хранится на аккаунт: журнал в окне чатов показывает их целиком.
const SCENARIO_EVENTS_LIMIT = 50;

// Код ошибки произвольный: МойСклад показывает пользователю errorMessage, code нужен только для своих логов.
const SCENARIO_REJECT_ERROR_CODE = 1001;

// Одно срабатывание действия сценария: что пришло от МоегоСклада и чем ответило решение.
class ScenarioEvent
{
    public ?int $id = null;
    public string $createdAt = '';
    public string $objectName = '';
    public string $message = '';
    // ok — ответили 200, rejected — 400 с сообщением пользователю, failed — 5xx, МойСклад повторит.
    public string $outcome = 'ok';

    public function __construct(
        public string $appId,
        public string $accountId,
        public string $actionName,
        public string $actionType,
        public string $objectType,
        public string $objectId,
    ) {
    }

    function persist(): void
    {
        scenarioEventRepository()->save($this);
    }
}

class ScenarioEventSqliteRepository extends SqliteRepository
{
    // Пишет событие и оставляет на аккаунте только последние SCENARIO_EVENTS_LIMIT записей.
    public function save(ScenarioEvent $event): void
    {
        $pdo = $this->connection();
        $event->createdAt = $event->createdAt !== '' ? $event->createdAt : gmdate('c');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO scenario_event (
                    application_id, account_id, action_name, action_type, object_type, object_id,
                    object_name, outcome, message, created_at
                ) VALUES (
                    :application_id, :account_id, :action_name, :action_type, :object_type, :object_id,
                    :object_name, :outcome, :message, :created_at
                )'
            );
            $stmt->execute([
                ':application_id' => $event->appId,
                ':account_id' => $event->accountId,
                ':action_name' => $event->actionName,
                ':action_type' => $event->actionType,
                ':object_type' => $event->objectType,
                ':object_id' => $event->objectId,
                ':object_name' => $event->objectName !== '' ? $event->objectName : null,
                ':outcome' => $event->outcome,
                ':message' => $event->message !== '' ? $event->message : null,
                ':created_at' => $event->createdAt,
            ]);
            $event->id = (int)$pdo->lastInsertId();

            $cleanup = $pdo->prepare(
                'DELETE FROM scenario_event
                WHERE application_id = :application_id AND account_id = :account_id AND id NOT IN (
                    SELECT id FROM scenario_event
                    WHERE application_id = :application_id AND account_id = :account_id
                    ORDER BY id DESC LIMIT :limit
                )'
            );
            $cleanup->bindValue(':application_id', $event->appId);
            $cleanup->bindValue(':account_id', $event->accountId);
            $cleanup->bindValue(':limit', SCENARIO_EVENTS_LIMIT, PDO::PARAM_INT);
            $cleanup->execute();
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /** @return ScenarioEvent[] новые события первыми */
    public function listRecent(string $appId, string $accountId, int $limit = SCENARIO_EVENTS_LIMIT): array
    {
        $stmt = $this->connection()->prepare(
            'SELECT id, application_id, account_id, action_name, action_type, object_type, object_id,
                object_name, outcome, message, created_at
            FROM scenario_event
            WHERE application_id = :application_id AND account_id = :account_id
            ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue(':application_id', $appId);
        $stmt->bindValue(':account_id', $accountId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn(array $row) => $this->fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    protected function initializeSchema(PDO $pdo): void
    {
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS scenario_event (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                application_id TEXT NOT NULL,
                account_id TEXT NOT NULL,
                action_name TEXT NOT NULL,
                action_type TEXT NOT NULL,
                object_type TEXT NOT NULL,
                object_id TEXT NOT NULL,
                object_name TEXT,
                outcome TEXT NOT NULL,
                message TEXT,
                created_at TEXT NOT NULL
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS scenario_event_account ON scenario_event (application_id, account_id, id)');
    }

    private function fromRow(array $row): ScenarioEvent
    {
        $event = new ScenarioEvent(
            (string)$row['application_id'],
            (string)$row['account_id'],
            (string)$row['action_name'],
            (string)$row['action_type'],
            (string)$row['object_type'],
            (string)$row['object_id'],
        );
        $event->id = (int)$row['id'];
        $event->objectName = (string)($row['object_name'] ?? '');
        $event->outcome = (string)$row['outcome'];
        $event->message = (string)($row['message'] ?? '');
        $event->createdAt = (string)$row['created_at'];

        return $event;
    }
}

$scenarioEventRepository = new ScenarioEventSqliteRepository();

function scenarioEventRepository(): ScenarioEventSqliteRepository
{
    return $GLOBALS['scenarioEventRepository'];
}

/**
 * Обрабатывает действие из сценария по правилам Vendor API: 200 — действие выполнено, 400 с объектом
 * error — отказ по вине пользователя (сообщение попадет в историю сценария), 5xx — МойСклад повторит
 * запрос. Ответить нужно быстрее 10 секунд, иначе МойСклад считает попытку неуспешной и тоже повторяет.
 *
 * @return array{status: int, body: ?array}
 */
function handleScenarioAction(string $appId, string $accountId, array $body): array
{
    $actionName = scenarioRequestString($body['actionName'] ?? null);
    $actionType = scenarioRequestString($body['actionType'] ?? null);
    $objectId = scenarioRequestString($body['objectId'] ?? null);
    $objectType = scenarioRequestString($body['objectType'] ?? null);

    if ($actionName === '' || $actionType === '' || $objectId === '' || $objectType === '') {
        return scenarioErrorResponse('В запросе нет actionName, actionType, objectId или objectType');
    }

    if (!array_key_exists($actionName, SCENARIO_ACTIONS)) {
        log_message('WARN', "Unknown scenario action $actionName for appId=$appId on accountId=$accountId");

        return scenarioErrorResponse("Решение не поддерживает действие «{$actionName}»");
    }

    $event = new ScenarioEvent($appId, $accountId, $actionName, $actionType, $objectType, $objectId);

    if ($actionName === 'reject') {
        $event->outcome = 'rejected';
        $event->message = "Решение отклонило действие для объекта $objectType $objectId";
        saveScenarioEvent($event);

        return scenarioErrorResponse($event->message, SCENARIO_REJECT_ERROR_CODE);
    }

    if ($actionName === 'fetch-object') {
        $response = fetchScenarioObject($objectType, $objectId);
        $object = $response !== null && $response['ok'] ? $response['body'] : null;

        if (!is_object($object) || !isset($object->name) || $object->name === '') {
            $event->outcome = 'failed';
            if ($response !== null && $response['status'] >= 400 && $response['status'] < 500 && $response['status'] !== 429) {
                $event->message = "JSON API не отдал объект $objectType $objectId: статус {$response['status']}, повтор не поможет";
                saveScenarioEvent($event);

                return scenarioErrorResponse($event->message);
            }
            $event->message = 'Не удалось получить объект через JSON API, МойСклад повторит действие';
            saveScenarioEvent($event);

            return ['status' => 502, 'body' => null];
        }

        $event->objectName = (string)$object->name;
        $event->message = "Получен объект $objectType «{$event->objectName}»";
        saveScenarioEvent($event);

        return ['status' => 200, 'body' => null];
    }

    $event->message = "Событие $actionType для объекта $objectType $objectId записано";
    saveScenarioEvent($event);

    return ['status' => 200, 'body' => null];
}

// objectType из сценария — имя сущности в МоемСкладе; в URL JSON API оно пишется строчными,
// кроме контрагента: он приходит как Company, а ресурс называется counterparty.
const SCENARIO_JSON_API_ENTITY_BY_OBJECT_TYPE = ['Company' => 'counterparty'];

function scenarioJsonApiEntity(string $objectType): string
{
    return SCENARIO_JSON_API_ENTITY_BY_OBJECT_TYPE[$objectType] ?? strtolower($objectType);
}

// Без access token (решение не установлено) JsonApi бросает RuntimeException — это тоже «не удалось».
function fetchScenarioObject(string $objectType, string $objectId): ?array
{
    try {
        return jsonApi()->getScenarioObject(scenarioJsonApiEntity($objectType), $objectId);
    } catch (RuntimeException $exception) {
        log_message('WARN', 'Cannot fetch scenario object: ' . $exception->getMessage());

        return null;
    }
}

function saveScenarioEvent(ScenarioEvent $event): void
{
    $event->persist();
    log_message('INFO', "Scenario action {$event->actionName} ({$event->actionType}) for {$event->objectType} "
        . "{$event->objectId} on accountId={$event->accountId}: {$event->outcome}");
}

/** @return array{status: int, body: array} */
function scenarioErrorResponse(string $message, ?int $code = null): array
{
    $error = ['errorMessage' => $message];

    if ($code !== null) {
        $error['code'] = $code;
    }

    return ['status' => 400, 'body' => ['error' => $error]];
}

function scenarioRequestString(mixed $value): string
{
    return is_string($value) ? trim($value) : '';
}

/**
 * Журнал событий и список действий из дескриптора для окна чатов (entry/iframe.inc.php и scenario/events.php).
 *
 * @return array{scenarioActions: array, scenarioEvents: array}
 */
function scenarioJournal(string $accountId): array
{
    $events = scenarioEventRepository()->listRecent(cfg()->appId, $accountId);

    return [
        'scenarioActions' => describeScenarioActions(),
        'scenarioEvents' => array_map('describeScenarioEvent', $events),
    ];
}

function describeScenarioActions(): array
{
    $actions = [];

    foreach (SCENARIO_ACTIONS as $name => $title) {
        $actions[] = ['name' => $name, 'title' => $title];
    }

    return $actions;
}

function describeScenarioEvent(ScenarioEvent $event): array
{
    return [
        'id' => $event->id,
        'createdAt' => $event->createdAt,
        'actionName' => $event->actionName,
        'actionTitle' => SCENARIO_ACTIONS[$event->actionName] ?? $event->actionName,
        'actionType' => $event->actionType,
        'objectType' => $event->objectType,
        'objectId' => $event->objectId,
        'objectName' => $event->objectName,
        'outcome' => $event->outcome,
        'message' => $event->message,
    ];
}
