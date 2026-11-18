<?php

require_once __DIR__ . '/repo.php';

class JwtPostgresRepository extends PostgresRepository
{
    // Вероятность запуска очистки
    // На production лучше установить <= 5, либо вообще запускать через crone
    private const DELETE_EXPIRED_CHANCE_PERCENT = 50;

    public function register(string $jti, int $expUnixSeconds): bool
    {
        $this->deleteExpiredMaybe();

        // Повтор jti упирается в первичный ключ: строка вставится только один раз.
        $stmt = $this->connection()->prepare(
            'INSERT INTO jwt (jti, expires_at) VALUES (:jti, :expires_at)
            ON CONFLICT (jti) DO NOTHING
            RETURNING jti'
        );

        $stmt->execute([
            ':jti' => $jti,
            ':expires_at' => $expUnixSeconds * 1000,
        ]);

        return $stmt->fetch() !== false;
    }

    public function deleteExpired(): void
    {
        $stmt = $this->connection()->prepare('DELETE FROM jwt WHERE expires_at <= :now');

        $stmt->execute([':now' => currentEpochMs()]);
    }

    private function deleteExpiredMaybe(): void
    {
        // Для оптимизации чтобы не выполнять очистку при каждой регистрации, делаем ее только иногда
        if (mt_rand(1, 100) > self::DELETE_EXPIRED_CHANCE_PERCENT) {
            return;
        }

        $this->deleteExpired();
    }
}
