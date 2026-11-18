<?php

require_once __DIR__ . '/repo.php';

class AppInstancePostgresRepository extends PostgresRepository
{
    public function load(string $appId, string $accountId): AppInstance
    {
        $stmt = $this->connection()->prepare(
            'SELECT status, access_token, info_message, store
            FROM account_application
            WHERE application_id = :application_id AND account_id = :account_id'
        );

        $stmt->execute([
            ':application_id' => $appId,
            ':account_id' => $accountId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $app = new AppInstance($appId, $accountId);

        if ($row === false) {
            return $app;
        }

        $app->status = isset($row['status']) ? (int)$row['status'] : AppInstance::UNKNOWN;
        $app->accessToken = isset($row['access_token']) ? $this->decryptSecret($row['access_token'], 'access_token') : null;
        $app->infoMessage = $row['info_message'] ?? null;
        $app->store = $row['store'] ?? null;

        return $app;
    }

    public function persist(AppInstance $app): void
    {
        $stmt = $this->connection()->prepare(
            'INSERT INTO account_application (
                account_id,
                application_id,
                status,
                access_token,
                info_message,
                store,
                created_at,
                updated_at
            ) VALUES (
                :account_id,
                :application_id,
                :status,
                :access_token,
                :info_message,
                :store,
                :created_at,
                :updated_at
            )
            ON CONFLICT(account_id, application_id) DO UPDATE SET
                status = excluded.status,
                access_token = CASE WHEN CAST(:keep_access_token AS integer) = 1
                    THEN account_application.access_token ELSE excluded.access_token END,
                info_message = excluded.info_message,
                store = excluded.store,
                updated_at = excluded.updated_at'
        );

        $timestamp = gmdate('c');
        $accessToken = $this->normalizeNullableString($app->accessToken);

        $stmt->execute([
            ':account_id' => (string)$app->accountId,
            ':application_id' => (string)$app->appId,
            ':status' => (int)$app->status,
            ':access_token' => $this->encryptSecret($accessToken),
            ':keep_access_token' => $accessToken === null && $this->hasUnreadableAccessToken($app) ? 1 : 0,
            ':info_message' => $this->normalizeNullableString($app->infoMessage),
            ':store' => $this->normalizeNullableString($app->store),
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }

    // Токен, зашифрованный другим APP_ENCRYPT_KEY, читается как отсутствующий. Сохранение без нового токена
    // не должно затирать его шифротекст: ключ могли подменить по ошибке, после возврата токен снова прочитается.
    private function hasUnreadableAccessToken(AppInstance $app): bool
    {
        $stmt = $this->connection()->prepare(
            'SELECT access_token FROM account_application WHERE application_id = :application_id AND account_id = :account_id'
        );
        $stmt->execute([':application_id' => (string)$app->appId, ':account_id' => (string)$app->accountId]);
        $stored = $stmt->fetchColumn();

        return is_string($stored) && $this->decryptSecret($stored, 'access_token') === null;
    }

    public function delete(string $appId, string $accountId): void
    {
        $stmt = $this->connection()->prepare(
            'DELETE FROM account_application
            WHERE application_id = :application_id AND account_id = :account_id'
        );

        $stmt->execute([
            ':application_id' => $appId,
            ':account_id' => $accountId,
        ]);
    }

    // Деактивирует установку, сохраняя пользовательские настройки: приостановка ($status = SUSPENDED)
    // и удаление с аккаунта ($status = UNINSTALLED) отличаются только итоговым статусом установки.
    public function deactivate(string $appId, string $accountId, int $status = AppInstance::SUSPENDED): void
    {
        $stmt = $this->connection()->prepare(
            'UPDATE account_application
            SET status = :status,
                access_token = NULL, -- при переустановке придёт новый токен через Vendor API
                updated_at = :updated_at
            WHERE application_id = :application_id AND account_id = :account_id'
        );

        $stmt->execute([
            ':status' => $status,
            ':application_id' => $appId,
            ':account_id' => $accountId,
            ':updated_at' => gmdate('c'),
        ]);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }
}
