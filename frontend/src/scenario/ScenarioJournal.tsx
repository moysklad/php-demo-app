import { useMemo, useState } from "react";
import { createColumnHelper, getCoreRowModel, useReactTable } from "@tanstack/react-table";
import { Badge, type BadgeVariant } from "@moysklad/uikit/components/Badge";
import { Banner } from "@moysklad/uikit/components/Banner";
import { BentoBlock } from "@moysklad/uikit/components/BentoBlock";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { HStack } from "@moysklad/uikit/components/HStack";
import { Link } from "@moysklad/uikit/components/Link";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { type DefaultHeaderMeta, PlainTextHeader, Table, TextCell } from "@moysklad/uikit/data-grid";
import type { ScenarioAction, ScenarioEvent, ScenarioJournalData } from "./types";

const JOURNAL_URL = "../scenario/events.php";
const SCENARIOS_URL = "https://online.moysklad.ru/app/#scripttemplate";

const OUTCOME_BADGE: Record<ScenarioEvent["outcome"], { variant: BadgeVariant; label: string }> = {
  ok: { variant: "green", label: "Выполнено" },
  rejected: { variant: "orange", label: "Отклонено" },
  failed: { variant: "red", label: "Ошибка" }
};

/** Table требует у строки необязательный onRowClick — см. WithRowClickLink в @moysklad/uikit/data-grid. */
type JournalRow = ScenarioEvent & { onRowClick?: () => void };

const helper = createColumnHelper<JournalRow>();

function textColumn(id: string, displayName: string, size: number, value: (event: ScenarioEvent) => string) {
  return helper.display({
    id,
    header: PlainTextHeader,
    meta: { displayName } as DefaultHeaderMeta,
    size,
    cell: ({ row }) => <TextCell label={value(row.original)} isArchived={false} isDisabled={false} />
  });
}

const COLUMNS = [
  textColumn("createdAt", "Когда", 150, (event) => formatMoment(event.createdAt)),
  textColumn("action", "Действие", 260, (event) => event.actionTitle),
  textColumn("actionType", "Событие", 150, (event) => event.actionType),
  textColumn("object", "Объект", 260, (event) => (event.objectName ? `${event.objectType} ${event.objectName}` : `${event.objectType} ${event.objectId}`)),
  helper.display({
    id: "outcome",
    header: PlainTextHeader,
    meta: { displayName: "Ответ решения" } as DefaultHeaderMeta,
    size: 140,
    cell: ({ row }) => {
      const badge = OUTCOME_BADGE[row.original.outcome];
      return <Badge variant={badge.variant} label={badge.label} />;
    }
  })
];

type ScenarioJournalProps = {
  contextNonce: string;
  actions: ScenarioAction[];
  initialEvents: ScenarioEvent[];
};

/**
 * Журнал действий из сценариев. МойСклад вызывает обработчик решения без участия пользователя,
 * поэтому результат видно только здесь: окно чатов — единственная страница решения, которая
 * доступна после активации всем пользователям аккаунта.
 */
export function ScenarioJournal({ contextNonce, actions, initialEvents }: ScenarioJournalProps) {
  const [events, setEvents] = useState(initialEvents);
  const [isLoading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState<ScenarioEvent | null>(null);
  const data = useMemo<JournalRow[]>(() => events, [events]);
  const table = useReactTable({ data, columns: COLUMNS, getCoreRowModel: getCoreRowModel() });

  async function refresh(): Promise<void> {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(JOURNAL_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ contextNonce }),
        credentials: "same-origin"
      });
      const payload = (await response.json().catch(() => null)) as Partial<ScenarioJournalData> | null;

      if (!response.ok || !payload?.scenarioEvents) {
        setError(`Не удалось обновить журнал: HTTP ${response.status}`);
        return;
      }

      setEvents(payload.scenarioEvents);
      setSelected(null);
    } catch {
      setError("Не удалось обновить журнал: сервер решения недоступен");
    } finally {
      setLoading(false);
    }
  }

  return (
    <BentoBlock as="section" containerClassName="page__wide">
      <VStack size="s12">
        <Text.H3>Действия в сценариях</Text.H3>
        <Text.Body>
          Администратор аккаунта добавляет действия решения в{" "}
          <Link href={SCENARIOS_URL} target="_blank" rel="noreferrer">
            сценарии МоегоСклада
          </Link>
          : при событии с документом МойСклад вызывает обработчик решения и передает имя действия, тип события и
          объект. Решение отвечает 200, когда действие выполнено, 400 с сообщением, когда отказывает по вине
          пользователя, и 5xx, если нужно повторить позже.
        </Text.Body>
        <VStack size="s4">
          <Text.BodyStrong>Действия из дескриптора решения</Text.BodyStrong>
          <HStack size="s8" style={{ flexWrap: "wrap" }}>
            {actions.map((action) => (
              <Badge key={action.name} variant="grey" label={`${action.title} (${action.name})`} />
            ))}
          </HStack>
        </VStack>
        <HStack size="s8" style={{ alignItems: "center" }}>
          <Button variant={ButtonVariants.SECONDARY} onClick={() => void refresh()} isLoading={isLoading}>
            Обновить журнал
          </Button>
          <Text.Caption>Хранятся последние 50 срабатываний на аккаунт</Text.Caption>
        </HStack>
        {error && <Banner type="warning" title={error} />}
        {events.length === 0 ? (
          <Text.Body>
            Срабатываний еще не было. Настройте сценарий с действием решения и измените подходящий документ.
          </Text.Body>
        ) : (
          <div style={{ overflowX: "auto" }}>
            <Table<JournalRow>
              table={table}
              isLoading={isLoading}
              fullWidth
              fullHeight={false}
              onRowClick={(row) => setSelected(row.original)}
            />
          </div>
        )}
        {selected && (
          <Banner
            type={selected.outcome === "ok" ? "info" : "warning"}
            title={`${selected.actionTitle}: ${OUTCOME_BADGE[selected.outcome].label.toLowerCase()}`}
            subtitle={selected.message}
          />
        )}
      </VStack>
    </BentoBlock>
  );
}

function formatMoment(iso: string): string {
  const moment = new Date(iso);
  return Number.isNaN(moment.getTime()) ? iso : moment.toLocaleString("ru-RU", { dateStyle: "short", timeStyle: "medium" });
}
