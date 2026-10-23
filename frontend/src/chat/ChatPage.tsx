import { BentoBlock } from "@moysklad/uikit/components/BentoBlock";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { StatusCard } from "../iframe/StatusCard";
import { UserInfo } from "../iframe/UserInfo";
// [feature:scenario] действия в сценариях: журнал живет в модуле frontend/src/scenario.
import { ScenarioJournal } from "../scenario/ScenarioJournal";
import type { ChatPageData } from "./page-data";

/**
 * Окно решения в Чатах. Отличия от основного iframe: ширина не больше 1096px, высоту хост не
 * подстраивает (скролл у страницы свой), сервисы хост-окна недоступны — только контекст пользователя.
 * Окно появляется после перехода решения в статус Activated.
 */
export function ChatPage({ data }: { data: ChatPageData }) {
  return (
    <main className="page chat-page">
      <BentoBlock as="section">
        <UserInfo data={data} />
        <StatusCard appVersion={data.appVersion} status={data.status} />
      </BentoBlock>
      <BentoBlock as="section">
        <VStack size="s8">
          <Text.H3>Окно чатов</Text.H3>
          <Text.Body>
            Это окно МойСклад открывает в разделе «Чаты» после активации решения. Оно получает контекст
            пользователя по тому же протоколу, что основной iframe и виджеты, а другие сервисы хост-окна
            (попапы, навигация, диалоги) здесь недоступны.
          </Text.Body>
          <Text.Body>
            Ширина окна не больше 1096px, высоту хост не меняет: вертикальный скролл остается у страницы решения.
          </Text.Body>
        </VStack>
      </BentoBlock>

      {/* [feature:scenario] действия в сценариях */}
      <ScenarioJournal contextNonce={data.contextNonce} actions={data.scenarioActions} initialEvents={data.scenarioEvents} />
    </main>
  );
}
