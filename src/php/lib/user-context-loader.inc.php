<?php

// получаем контекст пользователя по contextKey и заменяем его на contextNonce в PHP-сессии.
$contextKey = trim((string)($_GET['contextKey'] ?? ''));

if ($contextKey === '') {
    http_response_code(401);
    exit('Ошибка авторизации: параметр contextKey обязателен');
}

$employee = vendorApi()->context($contextKey);

if (!$employee || empty($employee->accountId) || empty($employee->uid)) {
    http_response_code(401);
    exit('Ошибка авторизации: не удалось получить контекст пользователя');
}

$context = saveActiveUserContextToSession([
    'uid' => $employee->uid,
    'fio' => $employee->shortFio ?? '',
    'accountId' => $employee->accountId,
    'isAdmin' => checkIsAdmin($employee),
]);

return $context;
