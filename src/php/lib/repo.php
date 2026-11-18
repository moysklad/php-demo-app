<?php

require_once __DIR__ . '/db.php';

// Таблицы создают миграции из db/migrations при старте (src/php/utils/migrate.php), репозитории только читают и пишут.
abstract class PostgresRepository
{
    protected function connection(): PDO
    {
        return databaseConnection();
    }

    protected function encryptSecret(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = $this->encryptionKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($value, $nonce, $key);

        return base64_encode($nonce . $encrypted);
    }

    protected function decryptSecret(?string $encrypted, string $field): ?string
    {
        if ($encrypted === null) {
            return null;
        }

        $data = base64_decode($encrypted, true);
        $nonceSize = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        if ($data === false || strlen($data) <= $nonceSize) {
            $message = "Corrupted $field in storage. Reinstall the application.";
            log_message('ERROR', $message);
            throw new RuntimeException($message);
        }

        $key = $this->encryptionKey();
        $nonce = substr($data, 0, $nonceSize);
        $ciphertext = substr($data, $nonceSize);
        $result = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($result === false) {
            // После смены APP_ENCRYPT_KEY старые данные не расшифровать. Считаем их отсутствующими,
            // чтобы решение можно было удалить и установить заново, а не получать 500 на каждом запросе.
            log_message('WARN', "Failed to decrypt $field: wrong APP_ENCRYPT_KEY or corrupted data, treating it as missing. Reinstall the application.");

            return null;
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
