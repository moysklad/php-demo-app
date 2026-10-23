<?php

require_once __DIR__ . '/scenario.inc.php';

// Журнал действий из сценариев для окна чатов. Запрос из браузера авторизуется contextNonce,
// как и остальные backend-запросы iframe (utils/update-settings.php, utils/get-object.php).

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    replyScenarioEvents(405, ['message' => 'Метод не поддерживается']);
}

$authContext = resolveBackendContextFromSession();

if (!$authContext) {
    replyScenarioEvents(401, ['message' => 'Ошибка авторизации: откройте окно заново.']);
}

replyScenarioEvents(200, scenarioJournal($authContext['accountId']));

function replyScenarioEvents(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
