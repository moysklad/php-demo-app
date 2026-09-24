import { useState } from "react";
import { Badge, badgeColors } from "@moysklad/uikit/components/Badge";
import { Banner } from "@moysklad/uikit/components/Banner";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { Chip } from "@moysklad/uikit/components/Chip";
import { Counter, CounterVariant } from "@moysklad/uikit/components/Counter";
import { EmptyState } from "@moysklad/uikit/components/EmptyState";
import { HStack } from "@moysklad/uikit/components/HStack";
import { Informer, InformerVariant } from "@moysklad/uikit/components/Informer";
import { Skeleton } from "@moysklad/uikit/components/Skeleton";
import { Spinner, SpinnerSize } from "@moysklad/uikit/components/Spinner";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { Section } from "../Section";

const SNIPPET = `
import { Banner } from "@moysklad/uikit/components/Banner";
import { Badge } from "@moysklad/uikit/components/Badge";
import { Skeleton } from "@moysklad/uikit/components/Skeleton";

<Banner type="warning" title="Токен истекает" subtitle="Обновите ключ API до 1 сентября" />
<Badge variant="green" label="Подключено" />
{isLoading ? <Skeleton width={240} height={16} /> : <Text.Body>{value}</Text.Body>}
`;

const EMPTY_IMAGE =
  "data:image/svg+xml;utf8," +
  encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96"><rect x="16" y="24" width="64" height="48" rx="8" fill="#E9EDF2"/><rect x="28" y="38" width="40" height="6" rx="3" fill="#C4CCD6"/><rect x="28" y="52" width="24" height="6" rx="3" fill="#C4CCD6"/></svg>'
  );

/** Обратная связь: баннеры, статусы, загрузка и пустое состояние — все на странице, без всплывающих элементов. */
export function FeedbackSection() {
  const [isBannerVisible, setBannerVisible] = useState(true);
  const [isLoading, setLoading] = useState(false);
  const [selectedChips, setSelectedChips] = useState<string[]>(["Новые"]);

  function simulateLoading(): void {
    setLoading(true);
    window.setTimeout(() => setLoading(false), 2000);
  }

  function toggleChip(label: string, selected: boolean): void {
    setSelectedChips((current) => (selected ? [...current, label] : current.filter((item) => item !== label)));
  }

  return (
    <Section
      title="Обратная связь"
      description="Banner — результат действия или важное сообщение на странице, Informer — неинтерактивное пояснение, Badge — статус, Skeleton/Spinner — загрузка, EmptyState — когда данных нет. Всплывающих уведомлений (Snackbar) в iframe избегайте — см. секцию «Попапы»."
      file="FeedbackSection.tsx"
      snippet={SNIPPET}
    >
      <VStack size="s16">
        <VStack size="s8">
          <Text.H3>Banner</Text.H3>
          <Banner type="info" title="Синхронизация раз в час" subtitle="Изменения в МоемСкладе появятся в сервисе в течение часа." />
          {isBannerVisible ? (
            <Banner type="warning" title="Токен истекает" subtitle="Обновите ключ API до 1 сентября, иначе выгрузка остановится." onHide={() => setBannerVisible(false)} />
          ) : (
            <Button variant={ButtonVariants.FRAMELESS} onClick={() => setBannerVisible(true)}>
              Вернуть баннер с предупреждением
            </Button>
          )}
        </VStack>

        <VStack size="s8">
          <Text.H3>Informer</Text.H3>
          {/* В отличие от Banner — неинтерактивный: без кнопок, ссылок и закрытия. Для состояния или ограничения. */}
          <Informer title="Выгрузка приостановлена" description="Сервис не отвечает, повторим через 10 минут." variant={InformerVariant.red} />
          <Informer description="Заказы за сегодня уже выгружены." variant={InformerVariant.green} />
        </VStack>

        <VStack size="s8">
          <Text.H3>Badge, Counter, Chip</Text.H3>
          <HStack size="s8" style={{ flexWrap: "wrap" }}>
            {Object.values(badgeColors).map((color) => (
              <Badge key={color} variant={color} label={color} />
            ))}
          </HStack>
          <HStack size="s8" style={{ alignItems: "center" }}>
            <Text.Body>Ошибок выгрузки:</Text.Body>
            <Counter value={3} variant={CounterVariant.FILLED} />
            <Text.Body>Товаров:</Text.Body>
            <Counter value={1280} max={999} variant={CounterVariant.PLAIN} />
          </HStack>
          <HStack size="s8" style={{ flexWrap: "wrap" }}>
            {["Новые", "В работе", "Отгружены"].map((label) => (
              <Chip key={label} label={label} selected={selectedChips.includes(label)} onSelectedChange={(selected) => toggleChip(label, selected)} />
            ))}
          </HStack>
        </VStack>

        <VStack size="s12">
          <Text.H3>Загрузка</Text.H3>
          <HStack size="s12" style={{ alignItems: "center" }}>
            <Spinner size={SpinnerSize.S} />
            <Spinner size={SpinnerSize.M} />
            <Spinner size={SpinnerSize.L}>Загружаем заказы…</Spinner>
          </HStack>
          <div>
            <Button variant={ButtonVariants.ADDITIONAL} onClick={simulateLoading} disabled={isLoading}>
              Имитировать загрузку карточки
            </Button>
          </div>
          <VStack size="s4">
            {isLoading ? (
              <>
                <Skeleton width={200} height={20} />
                <Skeleton width={320} height={16} />
                <Skeleton width={260} height={16} />
              </>
            ) : (
              <>
                <Text.BodyStrong>Заказ №00123</Text.BodyStrong>
                <Text.Body>Покупатель: ООО «Ромашка»</Text.Body>
                <Text.Body>Сумма: 12 480 ₽</Text.Body>
              </>
            )}
          </VStack>
        </VStack>

        <VStack size="s8">
          <Text.H3>EmptyState</Text.H3>
          {/* В тесных местах (виджет, колонка) используйте size="s" — большой вариант съедает всю высоту. */}
          <EmptyState
            size="s"
            imageSlot={<EmptyState.Image source={EMPTY_IMAGE} alt="" />}
            title="Заказов пока нет"
            description="Как только в сервисе появятся заказы, они отобразятся здесь."
            actionsSlot={
              <EmptyState.Actions
                centralButtonSlot={
                  <EmptyState.Button variant={ButtonVariants.PRIMARY} onClick={simulateLoading}>
                    Загрузить заказы
                  </EmptyState.Button>
                }
              />
            }
          />
        </VStack>
      </VStack>
    </Section>
  );
}
