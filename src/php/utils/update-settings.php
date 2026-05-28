<?php

require_once __DIR__ . '/../lib/lib.php';

$authContext = resolveBackendContextFromSession();

if (!$authContext) {
    http_response_code(401);
    replyUpdateSettingsError('Ошибка авторизации: откройте iframe заново.');
}

if (empty($authContext['isAdmin'])) {
    http_response_code(403);
    replyUpdateSettingsError('Недостаточно прав');
}

$infoMessage = trim((string)($_POST['infoMessage'] ?? ''));
$store = trim((string)($_POST['store'] ?? ''));

log_message('INFO', "Update settings: $infoMessage, store: $store");

$accountId = $authContext['accountId'];

$app = AppInstance::loadApp($accountId);
$app->infoMessage = $infoMessage;
$app->store = $store;

$app->status = $store === '' ? AppInstance::SETTINGS_REQUIRED : AppInstance::ACTIVATED;

// PUT идемпотентен, поэтому допустимо вызывать обновление статуса повторно.
$statusUpdated = vendorApi()->updateAppStatus(cfg()->appId, $accountId, $app->getStatusName());

if (!$statusUpdated) {
    http_response_code(502);
    replyUpdateSettingsError('Не удалось обновить статус приложения во внешнем Vendor API');
}

$app->persist();

$isSettingsRequired = $app->status !== AppInstance::ACTIVATED;

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'message' => 'Настройки обновлены',
    'status' => [
        'className' => $isSettingsRequired ? 'status-required' : 'status-ready',
        'title' => $isSettingsRequired ? 'ТРЕБУЕТСЯ НАСТРОЙКА' : 'РЕШЕНИЕ ГОТОВО К РАБОТЕ',
        'showDetails' => !$isSettingsRequired,
        'infoMessage' => $app->infoMessage ?? '',
        'store' => $app->store ?? '',
    ],
], JSON_UNESCAPED_UNICODE);

function replyUpdateSettingsError(string $message): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
