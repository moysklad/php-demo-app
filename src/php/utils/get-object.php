<?php
require_once __DIR__ . '/../lib/lib.php';

$entitiesMap = [
    'customerorder' => 'Заказ покупателя',
    'invoiceout' => 'Счет покупателю',
];

$authContext = resolveBackendContextFromSession();

if (!$authContext) {
    http_response_code(401);
    exit('Ошибка авторизации: откройте iframe/виджет заново.');
}

$entity = trim((string)($_GET['entity'] ?? ''));
$objectId = requestBodyValue('objectId') ?? trim((string)($_GET['objectId'] ?? ''));

if (!isset($entitiesMap[$entity])) {
    http_response_code(400);
    exit('Неподдерживаемая сущность');
}

if ($objectId === '') {
    http_response_code(400);
    exit('objectId обязателен');
}

$accountId = $authContext['accountId'];

$app = AppInstance::loadApp($accountId);

$object = jsonApi()->getObject($entity, $objectId);

if (!$object || empty($object->name)) {
    http_response_code(502);
    exit('Не удалось получить объект');
}

echo $entitiesMap[$entity] . ' ' . $object->name;
