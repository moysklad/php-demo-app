<?php

// [feature:loyalty] программа лояльности: данные вкладки приходят из модуля src/php/loyalty.
require_once __DIR__ . '/../loyalty/loyalty.inc.php';

/**
 * Собирает данные основного iframe для активного контекста пользователя.
 *
 * @param array{accountId: string, isAdmin: bool, uid: string, fio: string, contextNonce: string} $context
 */
function buildIframePageData(array $context): array
{
    $accountId = (string)$context['accountId'];
    $isAdmin = (bool)$context['isAdmin'];

    $app = AppInstance::loadApp($accountId);

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

    return [
        'accountId' => $accountId,
        'uid' => (string)$context['uid'],
        'fio' => (string)$context['fio'],
        'isAdmin' => $isAdmin,
        'contextNonce' => (string)$context['contextNonce'],
        'appVersion' => appVersion(),
        'infoMessage' => $app->infoMessage ?? '',
        'store' => $app->store ?? '',
        'storesValues' => $storesValues,
        'status' => describeAppStatus($app),
        // [feature:loyalty] программа лояльности: на статус решения подключение не влияет.
        ...loyaltyIframePageData($accountId),
    ];
}
