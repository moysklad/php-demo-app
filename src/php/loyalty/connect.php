<?php

require_once __DIR__ . '/loyalty.inc.php';

// Backend вкладки «Программа лояльности»: принимает настройки из формы и передает их в МойСклад
// через Vendor API. Авторизация та же, что у utils/update-settings.php: сессия и contextNonce.

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    replyConnectLoyalty(405, ['message' => 'Метод не поддерживается']);
}

$authContext = resolveBackendContextFromSession();

if (!$authContext) {
    replyConnectLoyalty(401, ['message' => 'Ошибка авторизации: откройте iframe заново.']);
}

if (empty($authContext['isAdmin'])) {
    replyConnectLoyalty(403, ['message' => 'Недостаточно прав']);
}

$body = requestJsonBody();
$providerUrl = parseLoyaltyProviderUrl($body['providerUrl'] ?? null);

if ($providerUrl === null) {
    replyConnectLoyalty(400, ['message' => 'Укажите корректный HTTP(S) URL провайдера Loyalty API']);
}

$appId = cfg()->appId;
$accountId = $authContext['accountId'];
$externalSearch = in_array($body['externalSearch'] ?? false, [true, 'true', 'on', '1', 1], true);
$providerToken = is_string($body['providerToken'] ?? null) ? trim($body['providerToken']) : '';

// Новый токен сначала сохраняется как ожидающий: провайдер принимает его вместе с действующим, пока
// МойСклад не подтвердит настройки. Так решение не получит 401, если МойСклад уже переключился
// на новый токен, и не сломает работающее подключение, если переключения не было.
$installation = LoyaltyInstallation::load($appId, $accountId) ?? new LoyaltyInstallation($appId, $accountId);
$installation->beginUpdate($providerToken !== '' ? $providerToken : null, $externalSearch);
$installation->persist();

$result = updateLoyaltySettings($appId, $accountId, $providerUrl, (string)$installation->pendingProviderToken, $externalSearch);

if ($result['outcome'] === 'unknown') {
    log_message('WARN', "Loyalty settings outcome is unknown for accountId=$accountId: {$result['message']}");
    replyConnectLoyalty(502, ['message' => "Не удалось подтвердить, что МойСклад принял настройки Loyalty API. {$result['message']}. Повторите подключение: до этого решение принимает и прежний, и новый токен."]);
}

if ($result['outcome'] === 'rejected') {
    $installation->discardUpdate();
    $installation->persist();
    $code = $result['code'] !== null ? "Ошибка {$result['code']}: " : '';
    replyConnectLoyalty(502, ['message' => "Не удалось передать настройки Loyalty API. $code{$result['message']}"]);
}

$installation->confirmUpdate();
$installation->persist();

log_message('INFO', "Loyalty settings sent to MoySklad for accountId=$accountId, externalSearch=" . ($externalSearch ? 'true' : 'false'));

replyConnectLoyalty(200, [
    'message' => 'Настройки переданы в МойСклад через Vendor API',
    'loyalty' => describeLoyaltyConnection($installation),
]);

function parseLoyaltyProviderUrl(mixed $value): ?string
{
    $raw = is_string($value) && trim($value) !== '' ? trim($value) : defaultLoyaltyProviderUrl();
    $parts = parse_url($raw);

    if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
        return null;
    }

    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return null;
    }

    return rtrim($raw, '/');
}

function replyConnectLoyalty(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
