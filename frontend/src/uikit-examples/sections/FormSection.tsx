import { type FormEvent, useState } from "react";
import { Banner } from "@moysklad/uikit/components/Banner";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { Checkbox } from "@moysklad/uikit/components/Checkbox";
import { Datepicker } from "@moysklad/uikit/components/Datepicker";
import { FieldLabel } from "@moysklad/uikit/components/FieldLabel";
import { HStack } from "@moysklad/uikit/components/HStack";
import { Input } from "@moysklad/uikit/components/Input";
import { Multiselect } from "@moysklad/uikit/components/Multiselect";
import { Quantity } from "@moysklad/uikit/components/Quantity";
import { Radiobutton } from "@moysklad/uikit/components/Radiobutton";
import { SearchInput } from "@moysklad/uikit/components/SearchInput";
import { SegmentButton } from "@moysklad/uikit/components/SegmentButton";
import { Select, type ISelectOption } from "@moysklad/uikit/components/Select";
import { Text } from "@moysklad/uikit/components/Text";
import { Textfield } from "@moysklad/uikit/components/Textfield";
import { Toggle } from "@moysklad/uikit/components/Toggle";
import { VStack } from "@moysklad/uikit/components/VStack";
import { Section } from "../Section";

const SNIPPET = `
import { Input } from "@moysklad/uikit/components/Input";
import { Select, type ISelectOption } from "@moysklad/uikit/components/Select";
import { Checkbox } from "@moysklad/uikit/components/Checkbox";
import { Banner } from "@moysklad/uikit/components/Banner";

const options: ISelectOption<string>[] = stores.map((name) => ({ label: name, value: name }));

<Input name="apiKey" label="Ключ API" required error={!apiKey} info="Из личного кабинета сервиса" value={apiKey} onChange={(e) => setApiKey(e.target.value)} />
<Select<string> label="Склад" options={options} value={options.find((o) => o.value === store)} onChange={(o) => setStore(String(o.value))} fullWidth />
<Checkbox name="sync" label="Синхронизировать остатки" checked={sync} onChange={(e) => setSync((e.target as HTMLInputElement).checked)} />
<Button type="submit" variant={ButtonVariants.PRIMARY}>Сохранить</Button>
{result && <Banner type={result.ok ? "info" : "warning"} title={result.text} />}
`;

const STORES: ISelectOption<string>[] = ["Основной склад", "Розница", "Возвраты"].map((name) => ({ label: name, value: name }));
const CHANNELS = [
  { value: "site", label: "Сайт" },
  { value: "marketplace", label: "Маркетплейс" },
  { value: "retail", label: "Розница" },
  { value: "wholesale", label: "Опт" }
];

/** Типичная форма настроек интеграции: поля, выбор, переключатели, дата, валидация и результат на странице. */
export function FormSection() {
  const [apiKey, setApiKey] = useState("");
  const [store, setStore] = useState<string>("Основной склад");
  const [channels, setChannels] = useState<string[]>(["site"]);
  const [comment, setComment] = useState("");
  const [sync, setSync] = useState(true);
  const [isEnabled, setEnabled] = useState(true);
  const [batchSize, setBatchSize] = useState(50);
  const [mode, setMode] = useState("auto");
  const [period, setPeriod] = useState<string | number>("day");
  const [startDate, setStartDate] = useState<Date | null>(new Date());
  const [submitted, setSubmitted] = useState(false);
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(null);
  const [search, setSearch] = useState("");

  const apiKeyError = submitted && apiKey.trim().length < 8;

  function submit(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    setSubmitted(true);

    if (apiKey.trim().length < 8) {
      setResult({ ok: false, text: "Ключ API должен быть не короче 8 символов" });
      return;
    }

    setResult({ ok: true, text: "Настройки сохранены" });
  }

  return (
    <Section
      title="Форма"
      description="Поля ввода, выбор из списка, флажки и дата. Ошибка подсвечивает поле (error), пояснение — через info, результат сохранения — Banner на странице."
      file="FormSection.tsx"
      snippet={SNIPPET}
    >
      <form onSubmit={submit}>
        <VStack size="s12">
          <Input
            name="apiKey"
            label="Ключ API"
            required
            error={apiKeyError}
            info={apiKeyError ? "Не короче 8 символов" : "Скопируйте из личного кабинета сервиса"}
            placeholder="sk_live_…"
            value={apiKey}
            onChange={(e) => setApiKey(e.target.value)}
          />
          <Select<string>
            label="Склад"
            options={STORES}
            value={STORES.find((option) => option.value === store)}
            onChange={(option) => setStore(String(option.value))}
            fullWidth
          />
          {/* У поля поиска в дропдауне мультиселекта захардкожен autoFocus: при открытии браузер
              доскролливает страницу МоегоСклада к дропдауну. На короткой странице это не мешает,
              на длинной — заметный прыжок, поэтому держите страницы с мультиселектом компактными. */}
          <Multiselect label="Каналы продаж" items={CHANNELS} values={channels} onChange={setChannels} placeholder="Выберите каналы" />
          <VStack size="s4">
            <FieldLabel label="Размер пачки выгрузки" />
            {/* Quantity растягивается на контейнер, поэтому ширину фиксируем оберткой. */}
            <div style={{ width: 120 }}>
              <Quantity name="batchSize" value={batchSize} min={1} max={500} step={10} onChange={(_e, value) => setBatchSize(Number(value) || 1)} />
            </div>
          </VStack>
          <Datepicker
            label="Начало синхронизации"
            lang="ru-RU"
            localeFormat="ru-RU"
            selectedDate={startDate}
            onDateChanged={(date) => setStartDate(date)}
          />
          <VStack size="s4">
            <FieldLabel label="Период выгрузки" />
            {/* Обертка не дает VStack растянуть группу: сегмент-кнопка занимает ширину по содержимому. */}
            <div>
              <SegmentButton.Group value={period} onChange={setPeriod} aria-label="Период выгрузки">
                <SegmentButton value="hour">Час</SegmentButton>
                <SegmentButton value="day">День</SegmentButton>
                <SegmentButton value="week">Неделя</SegmentButton>
              </SegmentButton.Group>
            </div>
          </VStack>
          <VStack size="s4">
            <FieldLabel label="Режим" />
            <Radiobutton name="mode" value="auto" label="Автоматически" checked={mode === "auto"} onChange={() => setMode("auto")} />
            <Radiobutton name="mode" value="manual" label="По кнопке" checked={mode === "manual"} onChange={() => setMode("manual")} />
          </VStack>
          <Checkbox
            name="sync"
            label="Синхронизировать остатки"
            info="Остатки будут обновляться по расписанию"
            checked={sync}
            onChange={(e) => setSync((e.target as HTMLInputElement).checked)}
          />
          <Toggle
            name="enabled"
            label="Интеграция включена"
            checked={isEnabled}
            onChange={(e) => setEnabled(e.target.checked)}
          />
          <Textfield name="comment" label="Комментарий" value={comment} onChange={(e) => setComment(e.target.value)} />
          <SearchInput placeholder="Поиск по товарам (Enter)" fullWidth onSearch={setSearch} />
          {search && <Text.Caption>Ищем «{search}»</Text.Caption>}
          <HStack size="s16">
            <Button type="submit" variant={ButtonVariants.PRIMARY}>
              Сохранить
            </Button>
            <Button
              type="button"
              variant={ButtonVariants.FRAMELESS}
              onClick={() => {
                setSubmitted(false);
                setResult(null);
              }}
            >
              Сбросить ошибки
            </Button>
          </HStack>
          {result && <Banner type={result.ok ? "info" : "warning"} title={result.text} />}
        </VStack>
      </form>
    </Section>
  );
}
