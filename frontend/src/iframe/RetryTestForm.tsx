import { type FormEvent, useState } from "react";
import { Banner } from "@moysklad/uikit/components/Banner";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { Input } from "@moysklad/uikit/components/Input";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";

const STORES_URL = "../utils/stores.php";
const MIN_REQUEST_COUNT = 1;
const MAX_REQUEST_COUNT = 1000;
const DEFAULT_REQUEST_COUNT = 50;
// Короткий интервал создаёт нагрузку на лимиты JSON API, но не отправляет все запросы
// строго одновременно, поэтому в логе проще проследить работу очереди и ретраев.
const STAGGER_MS = 5;

type StoresResponse = { success?: boolean; retries?: number };

type RunCounters = { completed: number; successful: number; failed: number; retries: number };

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

function describeProgress(counters: RunCounters, total: number): string {
  return `Выполнено: ${counters.completed} из ${total}. Успешно: ${counters.successful}, ошибок: ${counters.failed}, ретраев: ${counters.retries}.`;
}

/**
 * Проверка обработки X-Lognex-Retry-After: серия запросов списка складов заведомо упирается
 * в лимиты JSON API (частота и число параллельных запросов), а решение показывает,
 * сколько раз пришлось подождать и повторить запрос. Размер серии знает только клиент:
 * сервер обрабатывает каждый POST utils/stores.php как одиночный запрос.
 */
export function RetryTestForm({ contextNonce }: { contextNonce: string }) {
  const [requestCount, setRequestCount] = useState(String(DEFAULT_REQUEST_COUNT));
  const [isRunning, setRunning] = useState(false);
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if (isRunning) {
      return;
    }

    const total = Number(requestCount);

    if (!Number.isInteger(total) || total < MIN_REQUEST_COUNT || total > MAX_REQUEST_COUNT) {
      setResult({ ok: false, text: `Количество запросов должно быть от ${MIN_REQUEST_COUNT} до ${MAX_REQUEST_COUNT}` });
      return;
    }

    setRunning(true);

    const counters: RunCounters = { completed: 0, successful: 0, failed: 0, retries: 0 };
    setResult({ ok: true, text: describeProgress(counters, total) });

    try {
      await Promise.all(
        Array.from({ length: total }, async (_unused, index) => {
          if (index > 0) {
            await delay(index * STAGGER_MS);
          }

          try {
            const response = await fetch(STORES_URL, {
              method: "POST",
              credentials: "same-origin",
              body: new URLSearchParams({ contextNonce })
            });
            const contentType = response.headers.get("content-type") || "";
            const payload: StoresResponse | null = contentType.includes("application/json")
              ? await response.json()
              : null;

            if (typeof payload?.retries === "number") {
              counters.retries += payload.retries;
            }

            if (response.ok && payload?.success) {
              counters.successful += 1;
            } else {
              counters.failed += 1;
            }
          } catch {
            counters.failed += 1;
          } finally {
            counters.completed += 1;
            setResult({ ok: counters.failed === 0, text: describeProgress(counters, total) });
          }
        })
      );
    } catch {
      setResult({ ok: false, text: "Не удалось выполнить проверку" });
    } finally {
      setRunning(false);
    }
  }

  return (
    <form onSubmit={submit}>
      <VStack size="s12">
        <Text.H3>Проверка ретраев</Text.H3>
        <Text.Body>
          Выполнится выбранное количество запросов списка складов и покажет срабатывания ретраев по заголовку
          X-Lognex-Retry-After.
        </Text.Body>
        <Input
          name="requestCount"
          type="number"
          min={MIN_REQUEST_COUNT}
          max={MAX_REQUEST_COUNT}
          label="Количество запросов"
          value={requestCount}
          onChange={(event) => setRequestCount(event.target.value)}
        />
        <div>
          <Button type="submit" variant={ButtonVariants.PRIMARY} isLoading={isRunning}>
            Запустить проверку
          </Button>
        </div>
        {result && <Banner type={result.ok ? "info" : "warning"} title={result.text} onHide={() => setResult(null)} />}
      </VStack>
    </form>
  );
}
