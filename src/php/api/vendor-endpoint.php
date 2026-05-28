<?php

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

require_once __DIR__ . '/../lib/lib.php';

require_once __DIR__ . '/button.php';

$method = (string)($_SERVER['REQUEST_METHOD'] ?? '');
$path = (string)($_SERVER['PATH_INFO'] ?? '');
$headers = apache_request_headers() ?: [];

log_message('DEBUG', "Received: method=$method, path=$path, headers=" . print_r($headers, true));

if (!authTokenIsValid($headers)) {
    http_response_code(401);
    exit(0);
}

$path = trim(str_ireplace('/api/moysklad/vendor/1.0/apps/', '', $path), '/');
$pp = explode('/', $path);

if (($pp[0] ?? '') === '' || ($pp[1] ?? '') === '') {
    http_response_code(404);
    exit('Invalid Vendor API path');
}

$appId = $pp[0];
$accountId = $pp[1];

log_message('DEBUG', "Extracted: appId=$appId, accountId=$accountId");

$app = AppInstance::load($appId, $accountId);

switch ($method) {
    case 'PUT':
        $requestBody = file_get_contents('php://input');

        log_message('DEBUG', "Request body: " . print_r($requestBody, true));

        $data = json_decode($requestBody);

        if (!is_object($data)) {
            http_response_code(400);
            exit('Invalid install request');
        }

        if (cfg()->appUid !== '' && ($data->appUid ?? '') !== cfg()->appUid) {
            http_response_code(400);
            exit('Invalid appUid');
        }

        $cause = (string)($data->cause ?? '');
        $hasRequiredSettings = trim($app->store ?? '') !== '';

        // cause=Install и cause=Resume содержат access token; cause=TariffChanged и Autoprolongation — нет
        if (!empty($data->access[0]->access_token)) {
            $app->accessToken = (string)$data->access[0]->access_token;
        }

        if ($cause === 'Resume') {
            $app->status = $hasRequiredSettings ? AppInstance::ACTIVATED : AppInstance::SETTINGS_REQUIRED;
        } elseif (in_array($cause, ['TariffChanged', 'Autoprolongation'], true)) {
            // Смена тарифа не требует обновления — tariffId в БД не хранится, статус уже Activated
        } elseif (!$app->getStatusName()) {
            $app->status = $hasRequiredSettings ? AppInstance::ACTIVATED : AppInstance::SETTINGS_REQUIRED;
        }

        $app->persist();

        replyStatus($appId, $accountId, $app->getStatusName());

        break;
    case 'POST':
        // Обработка нажатий на кастомные кнопки
        if (($pp[2] ?? '') === 'button') {
            $requestBody = file_get_contents('php://input');

            log_message('DEBUG', "Request body: " . print_r($requestBody, true));

            $data = json_decode($requestBody);

            if (!is_object($data)) {
                http_response_code(400);
                exit('Invalid button request');
            }

            header("Content-Type: application/json");

            if (!empty($data->objectId)) {
                echo json_encode(processDocumentButtonClick(
                    (string)($data->buttonName ?? ''),
                    (string)($data->extensionPoint ?? ''),
                    (string)$data->objectId,
                    $data->user ?? null
                ));
            } elseif (!empty($data->selected) && is_iterable($data->selected)) {
                echo json_encode(processListButtonClick(
                    (string)($data->buttonName ?? ''),
                    (string)($data->extensionPoint ?? ''),
                    $data->selected
                ));
            }

            log_message('INFO', "Button processed for appId=$appId on accountId=$accountId by user=" . print_r($data->user ?? null, true));
        }

        break;
    case 'GET':
        checkAppStatus($appId, $accountId, $app->getStatusName());
        replyStatus($appId, $accountId, $app->getStatusName());

        break;
    case 'DELETE':
        checkAppStatus($appId, $accountId, $app->getStatusName());

        $requestBody = file_get_contents('php://input');
        $data = json_decode($requestBody);
        $cause = is_object($data) ? ($data->cause ?? null) : null;

        switch ($cause) {
            case 'Uninstall':
                $app->delete();
                log_message('INFO', "App appId=$appId deleted on accountId=$accountId, cause=$cause");

                break;
            case 'Suspend':
                $app->suspend();
                log_message('INFO', "App appId=$appId suspended on accountId=$accountId, cause=$cause");

                break;
            default:
                log_message('WARN', "Unsupported delete cause for appId=$appId on accountId=$accountId, cause=$cause");
                http_response_code(400);

                break;
        }
}

function checkAppStatus(string $appId, string $accountId, ?string $status): void
{
    if (!$status) {
        log_message('INFO', "App appId=$appId not installed on accountId=$accountId");
        http_response_code(204);

        exit(0);
    }
}

function replyStatus(string $appId, string $accountId, ?string $status): void
{
    log_message('INFO', "App appId=$appId installed on accountId=$accountId. Status: " . $status);
    header("Content-Type: application/json");

    echo json_encode(['status' => $status]);
}

function authTokenIsValid(array $headers): bool
{
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (empty($auth)) {
        log_message('WARN', "Authorization header not set");
        return false;
    }

    $bearer = "Bearer ";

    if (!str_starts_with($auth, $bearer)) {
        log_message('WARN', "Invalid auth token: $auth");
        return false;
    }

    $jwtToken = substr($auth, strlen($bearer));
    $secretKey = cfg()->secretKey;

    if (empty($secretKey)) {
        log_message('ERROR', "Secret key is not set in config");

        return false;
    }

    try {
        $decoded = JWT::decode($jwtToken, new Key($secretKey, 'HS256'));

        if (empty($decoded->exp)) {
            log_message('WARN', "EXP is not set");
            return false;
        }

        if (empty($decoded->jti)) {
            log_message('WARN', "JTI is not set");
            return false;
        }

        $jti = (string)$decoded->jti;

        if (!jwtRepository()->register($jti)) {
            log_message('WARN', "JTI already used: $jti");
            return false;
        }

        return true;
    } catch (Exception $exception) {
        log_message('WARN', $exception->getMessage());

        return false;
    }
}
