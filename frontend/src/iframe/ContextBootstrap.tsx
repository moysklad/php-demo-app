import { useEffect, useState } from "react";
import { Banner } from "@moysklad/uikit/components/Banner";
import { Spinner, SpinnerSize } from "@moysklad/uikit/components/Spinner";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { sdk } from "../ui/sdk";
import type { IframePageData } from "./page-data";
import { IframePage } from "./IframePage";

type UserContextResponse = {
  pageData?: IframePageData;
  message?: string;
  code?: string;
};

/** Основной iframe: браузер запрашивает одноразовый токен через SDK и поднимает сессию на backend. */
export function ContextBootstrap() {
  const [pageData, setPageData] = useState<IframePageData | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void initializeUserContext();
  }, []);

  async function initializeUserContext(): Promise<void> {
    let token: string | null = null;

    try {
      token = await sdk.requestUserContextToken();
    } catch (cause) {
      const details = cause instanceof Error ? cause.message : String(cause);
      setError(`Не удалось запросить контекст пользователя у хоста: ${details}`);
      return;
    }

    const request = new Request("user-context.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token, page: "iframe" }),
      credentials: "same-origin"
    });
    token = null;

    try {
      const response = await fetch(request);
      const payload = (await response.json().catch(() => null)) as UserContextResponse | null;

      if (!response.ok || !payload?.pageData) {
        const code = payload?.code ? ` (код ${payload.code})` : "";
        setError(`Не удалось получить контекст пользователя: HTTP ${response.status}${code}.`);
        return;
      }

      setPageData(payload.pageData);
    } catch {
      setError("Не удалось отправить контекст на сервер приложения.");
    }
  }

  if (pageData) {
    return <IframePage data={pageData} />;
  }

  if (error) {
    return (
      <main className="page">
        <Banner type="warning" title="Контекст пользователя" subtitle={error} />
      </main>
    );
  }

  return (
    <main className="page">
      <VStack size="s8">
        <Spinner size={SpinnerSize.M} />
        <Text.Body>Получаем контекст пользователя…</Text.Body>
      </VStack>
    </main>
  );
}
