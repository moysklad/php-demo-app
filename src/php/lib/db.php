<?php

const DB_SCHEMA_PATTERN = '/^[a-z_][a-z0-9_]*$/';
const DB_CONNECT_TIMEOUT_SECONDS = 5;
// сколько ждать базу на старте: в docker compose PostgreSQL стартует одновременно с приложением
const DB_STARTUP_CONNECT_ATTEMPTS = 30;

// Одно соединение на HTTP-запрос: PDO создается при первом обращении к любому репозиторию и закрывается вместе со скриптом.
function databaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = cfg();
    $missing = array_keys(array_filter(
        ['APP_DB_HOST' => $cfg->dbHost, 'APP_DB_NAME' => $cfg->dbName, 'APP_DB_USER' => $cfg->dbUser],
        static fn(string $value): bool => $value === ''
    ));

    if ($missing !== []) {
        throw new RuntimeException('Missing required database config: ' . implode(', ', $missing));
    }

    if (!preg_match(DB_SCHEMA_PATTERN, $cfg->dbSchema)) {
        throw new RuntimeException('APP_DB_SCHEMA must be a lowercase identifier: letters, digits and underscores');
    }

    if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('pdo_pgsql extension is required');
    }

    $pdo = new PDO(
        sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;options='-c search_path=%s,public'",
            $cfg->dbHost,
            $cfg->dbPort,
            $cfg->dbName,
            $cfg->dbSchema
        ),
        $cfg->dbUser,
        $cfg->dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => DB_CONNECT_TIMEOUT_SECONDS,
        ]
    );

    return $pdo;
}

function waitForDatabase(): void
{
    for ($attempt = 1; $attempt < DB_STARTUP_CONNECT_ATTEMPTS; $attempt++) {
        try {
            databaseConnection();
            return;
        } catch (PDOException $exception) {
            log_message('WARN', "Database is not ready (attempt {$attempt}/" . DB_STARTUP_CONNECT_ATTEMPTS . '): ' . $exception->getMessage());
            sleep(1);
        }
    }

    databaseConnection();
}

/**
 * Создает схему решения и применяет SQL-файлы из db/migrations/ по порядку имен.
 * Под advisory lock, чтобы параллельно стартующие экземпляры не мешали друг другу.
 *
 * @return string[] примененные версии
 */
function applyMigrations(): array
{
    $pdo = databaseConnection();
    $schema = cfg()->dbSchema;
    $applied = [];

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:schema))');
        $lock->execute([':schema' => $schema]);
        $pdo->exec("CREATE SCHEMA IF NOT EXISTS \"{$schema}\"");
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT now())');
        $known = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $insert = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');

        foreach (glob(dirname(__DIR__, 3) . '/db/migrations/*.sql') ?: [] as $file) {
            $version = basename($file, '.sql');

            if (in_array($version, $known, true)) {
                continue;
            }

            $pdo->exec(file_get_contents($file));
            $insert->execute([':version' => $version]);
            $applied[] = $version;
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    if ($applied !== []) {
        log_message('INFO', "Applied database migrations in schema {$schema}: " . implode(', ', $applied));
    }

    return $applied;
}
