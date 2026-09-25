<?php

abstract class SqliteRepository
{
    private ?PDO $pdo = null;

    protected function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (!class_exists('PDO')) {
            $message = 'PDO extension is required';
            log_message('ERROR', $message);
            throw new RuntimeException($message);
        }

        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $message = 'pdo_sqlite extension is required';
            log_message('ERROR', $message);
            throw new RuntimeException($message);
        }

        $databasePath = appDatabasePath();
        $directory = dirname($databasePath);

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            $message = 'Failed to create SQLite directory: ' . $directory;
            log_message('ERROR', $message);
            throw new RuntimeException($message);
        }

        try {
            $pdo = new PDO('sqlite:' . $databasePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA busy_timeout=5000');
            $this->initializeSchema($pdo);
            $this->pdo = $pdo;
        } catch (Throwable $exception) {
            $message = 'Failed to initialize: ' . $exception->getMessage();
            log_message('ERROR', $message);
            throw new RuntimeException($message, 0, $exception);
        }

        return $this->pdo;
    }

    abstract protected function initializeSchema(PDO $pdo): void;

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
            $message = "Failed to decrypt $field: wrong key or corrupted data. Reinstall the application.";
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
