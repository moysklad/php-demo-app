<?php

require_once __DIR__ . '/repo.php';

// PHP workers не делят память: очередь одного API и токена хранится в общей SQLite.
// Транзакция нужна только для резервирования слота; ожидание и HTTP идут без блокировки БД.
class LognexRateLimitGate extends SqliteRepository
{
    private const IDLE_MS = 10 * 60 * 1000;
    private string $key;

    public function __construct(string $apiUrl, string $bearerToken)
    {
        $this->key = hash('sha256', $apiUrl . "\0" . $bearerToken);
    }

    public function reserveDelay(int $minimumDelayMs = 0): int
    {
        return $this->updateState(function (array &$state, int $now) use ($minimumDelayMs): int {
            $startAt = max($now + $minimumDelayMs, $state['not_before']);
            $state['not_before'] = $startAt + $state['spacing_ms'];
            return (int)ceil($startAt - $now);
        });
    }

    public function observe(int $status, array $headers): void
    {
        $this->updateState(function (array &$state, int $now) use ($status, $headers): int {
            $limit = nonNegativeIntegerHeader($headers, 'x-ratelimit-limit');
            $interval = nonNegativeIntegerHeader($headers, 'x-lognex-retry-timeinterval');
            if ($limit !== null && $limit > 0 && $interval !== null && $interval > 0) {
                $state['spacing_ms'] = $interval / $limit;
            }

            $retryAfter = nonNegativeIntegerHeader($headers, 'x-lognex-retry-after');
            if ($status === 429 && $retryAfter !== null) {
                $state['not_before'] = max($state['not_before'], $now + $retryAfter);
            }
            return 0;
        });
    }

    private function updateState(callable $update): int
    {
        $pdo = $this->connection();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $now = currentEpochMs();
            $prune = $pdo->prepare('DELETE FROM http_rate_limit WHERE last_used_at <= ? AND not_before <= ?');
            $prune->execute([$now - self::IDLE_MS, $now]);
            $select = $pdo->prepare('SELECT not_before, spacing_ms FROM http_rate_limit WHERE gate_key = ?');
            $select->execute([$this->key]);
            $state = $select->fetch() ?: ['not_before' => 0, 'spacing_ms' => 0];
            $result = $update($state, $now);
            $save = $pdo->prepare('INSERT INTO http_rate_limit (gate_key, not_before, spacing_ms, last_used_at)
                VALUES (?, ?, ?, ?) ON CONFLICT(gate_key) DO UPDATE SET
                not_before = excluded.not_before, spacing_ms = excluded.spacing_ms, last_used_at = excluded.last_used_at');
            $save->execute([$this->key, $state['not_before'], $state['spacing_ms'], $now]);
            $pdo->exec('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    }

    protected function initializeSchema(PDO $pdo): void
    {
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('CREATE TABLE IF NOT EXISTS http_rate_limit (
            gate_key TEXT PRIMARY KEY,
            not_before REAL NOT NULL,
            spacing_ms REAL NOT NULL,
            last_used_at INTEGER NOT NULL
        )');
    }
}
