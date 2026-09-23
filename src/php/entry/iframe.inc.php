<?php

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

    return [
        'accountId' => $accountId,
        'isAdmin' => $isAdmin,
        'accessLevel' => $isAdmin ? 'администратор аккаунта' : 'простой пользователь',
        'uid' => (string)$context['uid'],
        'fio' => (string)$context['fio'],
        'contextNonce' => (string)$context['contextNonce'],
        'appVersion' => appVersion(),
        'hasAccessToken' => !empty($app->accessToken),
        'storesValues' => $storesValues,
        'status' => [
            'className' => $isSettingsRequired ? 'status-required' : 'status-ready',
            'title' => $isSettingsRequired ? 'ТРЕБУЕТСЯ НАСТРОЙКА' : 'РЕШЕНИЕ ГОТОВО К РАБОТЕ',
            'showDetails' => !$isSettingsRequired,
            'infoMessage' => $app->infoMessage ?? '',
            'store' => $app->store ?? '',
        ],
    ];
}
