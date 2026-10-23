import { type ReactElement, useEffect, useState } from "react";
import { Banner } from "@moysklad/uikit/components/Banner";
import { Spinner, SpinnerSize } from "@moysklad/uikit/components/Spinner";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { sdk } from "../ui/sdk";

type UserContextResponse<TPageData> = {
  pageData?: TPageData;
  message?: string;
  code?: string;
};

type ContextBootstrapProps<TPageData> = {
  /** Какую страницу собирает backend в pageData: "iframe" — основной iframe, "chat" — окно чатов. */
  page: "iframe" | "chat";
  render: (pageData: TPageData) => ReactElement;
};

/**
 * Окна решения на протоколе user-context: браузер запрашивает одноразовый токен через SDK,
 * backend обменивает его на контекст пользователя и возвращает данные страницы.
 */
export function ContextBootstrap<TPageData>({ page, render }: ContextBootstrapProps<TPageData>) {
  const [pageData, setPageData] = useState<TPageData | null>(null);
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
      body: JSON.stringify({ token, page }),
      credentials: "same-origin"
    });
    token = null;

    try {
      const response = await fetch(request);
      const payload = (await response.json().catch(() => null)) as UserContextResponse<TPageData> | null;

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
    return render(pageData);
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
