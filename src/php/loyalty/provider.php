<?php

require_once __DIR__ . '/loyalty.inc.php';

// Заглушка провайдера Loyalty API. МойСклад вызывает методы относительно URL, переданного
// в PUT .../loyalty: {url}/counterparty, {url}/retaildemand/recalc и т.д. Метод приходит в PATH_INFO.
// Ошибки отдаются в формате Loyalty API: {"errors": [{"error", "code", "error_message"}]}.

$method = (string)($_SERVER['REQUEST_METHOD'] ?? '');
$path = '/' . trim((string)($_SERVER['PATH_INFO'] ?? ''), '/');

log_message('DEBUG', "Loyalty API request: $method $path");

try {
    $installation = LoyaltyInstallation::findByToken(loyaltyAuthToken());
} catch (Throwable $exception) {
    log_message('ERROR', 'Loyalty API authorization failed: ' . $exception->getMessage());
    replyLoyaltyError(500, 'Не удалось авторизовать запрос программы лояльности');
}

if ($installation === null) {
    replyLoyaltyError(401, 'Недействительный токен авторизации');
}

switch ("$method $path") {
    case 'POST /counterparty':
    case 'POST /retaildemand':
    case 'POST /retailsalesreturn':
        http_response_code(201);

        break;
    case 'GET /counterparty':
        // Внешний поиск обязателен только при externalSearch: true, иначе отвечаем как на нереализованный метод.
        if (!$installation->allowsExternalSearch()) {
            replyLoyaltyError(404, 'Внешний поиск покупателей не используется: externalSearch выключен');
        }

        replyLoyaltyJson(['rows' => findLoyaltyDemoCustomers((string)($_GET['search'] ?? ''))]);

        break;
    case 'POST /counterparty/detail':
        replyLoyaltyJson(['bonusProgram' => ['agentBonusBalance' => 0]]);

        break;
    case 'POST /retaildemand/recalc':
        $request = json_decode((string)file_get_contents('php://input'));

        if (!is_object($request)) {
            replyLoyaltyError(400, 'Некорректное тело запроса');
        }

        $positions = [];

        foreach (is_array($request->positions ?? null) ? $request->positions : [] as $position) {
            $position->discountPercent = 0;
            $position->discountedPrice = $position->price ?? 0;
            $positions[] = $position;
        }

        replyLoyaltyJson([
            'agent' => $request->agent ?? new stdClass(),
            'positions' => $positions,
            'bonusProgram' => [
                'transactionType' => ($request->bonusProgram->transactionType ?? '') === 'SPENDING' ? 'SPENDING' : 'EARNING',
                'agentBonusBalance' => 0,
                'bonusValueToSpend' => 0,
                'bonusValueToEarn' => 0,
                'agentBonusBalanceAfter' => 0,
                'paidByBonusPoints' => 0,
                'receiptExtraInfo' => '',
            ],
            'needVerification' => false,
        ]);

        break;
    default:
        replyLoyaltyError(404, "Метод $method $path не реализован");
}

function loyaltyAuthToken(): string
{
    foreach (apache_request_headers() ?: [] as $name => $value) {
        if (strtolower($name) === 'lognex-discount-api-auth-token') {
            return trim((string)$value);
        }
    }

    return '';
}

function replyLoyaltyJson(array $payload): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function replyLoyaltyError(int $status, string $message): void
{
    http_response_code($status);
    replyLoyaltyJson(['errors' => [['error' => $message, 'code' => 999, 'error_message' => $message]]]);
    exit;
}
