<?php
if (!defined('IFRAME_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!isset($context) || !is_array($context)) {
    throw new LogicException('iframe.inc.php requires a user context array');
}

/** @var array{accountId: string, isAdmin: bool, uid: string, fio: string, contextNonce: string} $context */
$contextName = 'IFRAME';

$accountId = (string)$context['accountId'];
$isAdmin = (bool)$context['isAdmin'];
$uid = (string)$context['uid'];
$fio = (string)$context['fio'];
$contextNonce = (string)$context['contextNonce'];

$app = AppInstance::loadApp($accountId);

$infoMessage = $app->infoMessage;
$store = $app->store;
$isSettingsRequired = $app->status !== AppInstance::ACTIVATED;
$storesValues = [];

if (empty($app->accessToken)) {
    log_message('WARN', "App appId={$app->appId} on accountId=$accountId has no access token in local storage");
}

if ($isAdmin && !empty($app->accessToken)) {
    try {
        $stores = jsonApi()->stores();

        if (!empty($stores->rows)) {
            foreach ($stores->rows as $v) {
                $storesValues[] = $v->name;
            }
        }
    } catch (RuntimeException $e) {
        log_message('WARN', "Cannot fetch stores: " . $e->getMessage());
    }
}

require __DIR__ . '/iframe.html.php';
