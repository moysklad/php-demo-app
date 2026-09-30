<?php

require_once __DIR__ . '/../lib/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    replyStores(405, 'Метод не поддерживается');
}

$authContext = resolveBackendContextFromSession();
// Сохраняем продление TTL и отпускаем блокировку до HTTP-запроса и ожидания ретраев.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!$authContext) {
    replyStores(401, 'Ошибка авторизации: откройте iframe заново.');
}
if (empty($authContext['isAdmin'])) {
    replyStores(403, 'Недостаточно прав');
}

try {
    $app = AppInstance::loadApp($authContext['accountId']);
    if (empty($app->accessToken)) {
        replyStores(502, 'Не удалось получить список складов');
    }

    // Размер серии известен только браузеру: один вызов endpoint — один запрос складов.
    $result = jsonApi()->storesWithRetries();
    $success = $result['stores'] !== null;
    replyStores($success ? 200 : 502,
        $success ? 'Запрос выполнен' : 'Не удалось получить список складов', $result['retries']);
} catch (Throwable $error) {
    log_message('ERROR', 'Stores request failed: ' . $error->getMessage());
    replyStores(502, 'Не удалось получить список складов');
}

function replyStores(int $status, string $message, int $retries = 0): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['message' => $message, 'success' => $status === 200, 'retries' => $retries], JSON_UNESCAPED_UNICODE);
    exit;
}
