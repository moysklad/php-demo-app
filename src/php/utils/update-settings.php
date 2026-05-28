<?php

require_once __DIR__ . '/../lib/lib.php';

$authContext = resolveBackendContextFromSession();

if (!$authContext) {
    http_response_code(401);
    exit('Ошибка авторизации: откройте iframe заново.');
}

if (empty($authContext['isAdmin'])) {
    http_response_code(403);
    exit('Недостаточно прав');
}

$infoMessage = trim((string)($_POST['infoMessage'] ?? ''));
$store = trim((string)($_POST['store'] ?? ''));

log_message('INFO', "Update settings: $infoMessage, store: $store");

$accountId = $authContext['accountId'];

$app = AppInstance::loadApp($accountId);
$app->infoMessage = $infoMessage;
$app->store = $store;

$app->status = AppInstance::ACTIVATED;

// PUT идемпотентен, поэтому допустимо вызывать обновление статуса повторно.
vendorApi()->updateAppStatus(cfg()->appId, $accountId, $app->getStatusName());

$app->persist();

echo 'Настройки обновлены, перезагрузите решение';
