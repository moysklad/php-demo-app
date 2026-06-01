<?php
/*
 * Логирование ошибок для отладки. Отключите перед публикацией решения!
 */
ini_set('log_errors', '1');
ini_set('display_errors', '1');
error_reporting(E_ALL);

const LOG_LEVEL = 'DEBUG';
// const LOG_LEVEL = 'INFO';

/**
 * Параметры читаются из переменных окружения.
 * Имена и значения по умолчанию — в .env.example в корне репозитория.
 */
return [
    'appId' => getenv('APP_ID'),
    'appUid' => getenv('APP_UID'),
    'appBaseUrl' => getenv('APP_BASE_URL'),
    'secretKey' => getenv('APP_SECRET_KEY'),
    'databasePath' => getenv('APP_DB_PATH') ?: '',
    'encryptKey' => getenv('APP_ENCRYPT_KEY'),
    'moyskladVendorApiEndpointUrl' => getenv('MOYSKLAD_VENDOR_API_URL')
        ?: 'https://apps-api.moysklad.ru/api/vendor/1.0',
    'moyskladJsonApiEndpointUrl' => getenv('MOYSKLAD_JSON_API_URL')
        ?: 'https://api.moysklad.ru/api/remap/1.2',
];
