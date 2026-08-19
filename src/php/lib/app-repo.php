<?php

require_once __DIR__ . '/repo.php';

class AppInstanceSqliteRepository extends SqliteRepository
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
        $app->accessToken = isset($row['access_token']) ? $this->decryptToken($row['access_token']) : null;
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
                access_token = excluded.access_token,
                info_message = excluded.info_message,
                store = excluded.store,
                updated_at = excluded.updated_at'
        );

        $timestamp = gmdate('c');

        $stmt->execute([
            ':account_id' => (string)$app->accountId,
            ':application_id' => (string)$app->appId,
            ':status' => (int)$app->status,
            ':access_token' => $this->encryptToken($this->normalizeNullableString($app->accessToken)),
            ':info_message' => $this->normalizeNullableString($app->infoMessage),
            ':store' => $this->normalizeNullableString($app->store),
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
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

    protected function initializeSchema(PDO $pdo): void
    {
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS account_application (
                account_id TEXT NOT NULL,
                application_id TEXT NOT NULL,
                status INTEGER, -- 0=UNKNOWN, 1=SETTINGS_REQUIRED, 2=SUSPENDED, 4=UNINSTALLED, 100=ACTIVATED
                access_token TEXT,
                info_message TEXT,
                store TEXT,
                created_at TEXT NOT NULL, -- ISO 8601, SQLite не имеет встроенного типа дата/время
                updated_at TEXT NOT NULL,
                PRIMARY KEY (account_id, application_id)
            )'
        );
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function encryptToken(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        $key = $this->encryptionKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($token, $nonce, $key);

        return base64_encode($nonce . $encrypted);
    }

    private function decryptToken(?string $encrypted): ?string
    {
        if ($encrypted === null) {
            return null;
        }

        $data = base64_decode($encrypted, true);
        $nonceSize = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        if ($data === false || strlen($data) <= $nonceSize) {
            $message = 'Corrupted access_token in storage. Reinstall the application.';
            log_message('ERROR', $message);
            throw new RuntimeException($message);
        }

        $key = $this->encryptionKey();
        $nonce = substr($data, 0, $nonceSize);
        $ciphertext = substr($data, $nonceSize);
        $result = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($result === false) {
            $message = 'Failed to decrypt access_token: wrong key or corrupted data. Reinstall the application.';
            log_message('ERROR', $message);
            throw new RuntimeException($message);
        }

        return $result;
    }

    private function encryptionKey(): string
    {
        $hexKey = cfg()->encryptKey;
        $expectedLen = SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2; // 64 hex chars

        if (strlen($hexKey) !== $expectedLen || !ctype_xdigit($hexKey)) {
            $message = "APP_ENCRYPT_KEY must be {$expectedLen} hex chars. Generate: bin2hex(sodium_crypto_secretbox_keygen())";
            log_message('ERROR', $message);
            throw new RuntimeException($message);
        }

        return hex2bin($hexKey);
    }
}
