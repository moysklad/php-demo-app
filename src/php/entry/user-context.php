<?php

require_once __DIR__ . '/../lib/lib.php';
require_once __DIR__ . '/iframe.inc.php';

// Обмен одноразового токена из requestUserContextToken() на контекст пользователя.
// Токен принимается только в JSON body, в сессии и в ответе не сохраняется.
header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    replyUserContextError('Метод не поддерживается');
}

$body = requestJsonBody();
$token = trim((string)($body['token'] ?? ''));

if ($token === '') {
    http_response_code(400);
    replyUserContextError('token обязателен и передается только в JSON body');
}

$result = vendorApi()->exchangeUserContext($token);
unset($token);

if (!$result['ok'] || $result['user'] === null) {
    // 401 от Zeus означает битый service JWT решения, а не ошибку пользователя.
    http_response_code($result['status'] === 401 ? 502 : $result['status']);
    replyUserContextError('Не удалось получить контекст пользователя', $result['code']);
}

$user = $result['user'];
$isAdmin = roleToIsAdmin($user['role']);

$context = saveActiveUserContextToSession([
    'uid' => $user['userUid'],
    'fio' => '',
    'accountId' => $user['accountId'],
    'isAdmin' => $isAdmin,
]);

$response = [
    'user' => [
        'accountId' => $user['accountId'],
        'userId' => $user['userId'],
        'userUid' => $user['userUid'],
        'role' => $user['role'],
        'isAdmin' => $isAdmin,
    ],
    'contextNonce' => $context['contextNonce'],
];

if (($body['page'] ?? null) === 'iframe') {
    $response['pageData'] = buildIframePageData($context);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

function replyUserContextError(string $message, ?string $code = null): void
{
    $payload = ['message' => $message];

    if ($code !== null) {
        $payload['code'] = $code;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
