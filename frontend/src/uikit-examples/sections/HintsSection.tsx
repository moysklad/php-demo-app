import { useRef, useState } from "react";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { Dropdown } from "@moysklad/uikit/components/Dropdown";
import { Help } from "@moysklad/uikit/components/Help";
import { Hint, HintVariant } from "@moysklad/uikit/components/Hint";
import { HStack } from "@moysklad/uikit/components/HStack";
import { Text } from "@moysklad/uikit/components/Text";
import { Tooltip, Placement } from "@moysklad/uikit/components/Tooltip";
import { VStack } from "@moysklad/uikit/components/VStack";
import {
  DateRangeFilter,
  FiltersContainer,
  FilterType,
  InputFilter,
  MultiselectFilter,
  RangeFilter,
  RangeFilterType,
  SelectFilter,
  type Filter
} from "@moysklad/uikit/data-grid";
import { AlertWarningTriangle20Icon, Down20Icon } from "@moysklad/uikit/icon";
import { Section } from "../Section";

const SNIPPET = `
import { Help } from "@moysklad/uikit/components/Help";
import { Hint, HintVariant } from "@moysklad/uikit/components/Hint";
import { Tooltip, Placement } from "@moysklad/uikit/components/Tooltip";
import { FiltersContainer, FilterType, InputFilter, type Filter } from "@moysklad/uikit/data-grid";

<Help popup="Ключ API можно получить в личном кабинете сервиса." />
<Hint overlay="Действие необратимо" variant={HintVariant.Alert}><Text.Body>Удалить</Text.Body></Hint>
{/* Tooltip открывается только в управляемом режиме: visible в state, trigger hover его переключает. */}
<Tooltip overlay="Подсказка" trigger={["hover"]} visible={isVisible} onVisibleChange={setVisible} placement={Placement.BOTTOM}>
  <Text.Body>Наведите</Text.Body>
</Tooltip>

const FILTERS: (() => Filter)[] = [() => ({
  id: "name", label: "Название", type: FilterType.INPUT,
  render: ({ value, onChange }) => <InputFilter name="name" label="Название" value={value ?? ""} onChange={onChange} />
})];
<FiltersContainer open={areFiltersOpen} filters={FILTERS} searchButtonText="Найти" onSearch={apply} />
`;

const ACTIONS = ["Выгрузить заказ", "Обновить остатки", "Отвязать"];

const STORES = ["Основной склад", "Розница", "Возвраты"].map((name) => ({ label: name, value: name }));
const CHANNELS = [
  { value: "site", label: "Сайт" },
  { value: "marketplace", label: "Маркетплейс" },
  { value: "retail", label: "Розница" }
];

/* Фабрики фильтров для FiltersContainer: контейнер сам хранит значения и отдает их
   в render; каждый вариант фильтра — свой компонент из data-grid. */
const FILTERS: (() => Filter)[] = [
  () => ({
    id: "name",
    label: "Название",
    type: FilterType.INPUT,
    render: ({ value, onChange }) => <InputFilter name="name" label="Название" value={value ?? ""} onChange={onChange} />
  }),
  () => ({
    id: "store",
    label: "Склад",
    type: FilterType.SELECT,
    render: ({ value, onChange }) => <SelectFilter name="store" label="Склад" options={STORES} value={value} onChange={onChange} />
  }),
  () => ({
    id: "channels",
    label: "Каналы",
    type: FilterType.MULTISELECT,
    render: ({ value, onChange }) => (
      <MultiselectFilter name="channels" label="Каналы" items={CHANNELS} values={value ?? []} onChange={onChange} />
    )
  }),
  () => ({
    id: "price",
    label: "Цена",
    type: FilterType.RANGE,
    render: ({ value, onChange }) => (
      <RangeFilter name="price" label="Цена" type={RangeFilterType.integer} value={value} onChange={onChange} />
    )
  }),
  () => ({
    id: "period",
    label: "Период",
    type: FilterType.DATE_RANGE,
    render: ({ value, onChange }) => <DateRangeFilter name="period" label="Период" value={value} onChange={onChange} />
  })
];

/** Подсказки, меню действий и строка фильтров списка. */
export function HintsSection() {
  const [isDropdownOpen, setDropdownOpen] = useState(false);
  const [lastAction, setLastAction] = useState<string | null>(null);
  const [areFiltersOpen, setFiltersOpen] = useState(false);
  const [filtersSummary, setFiltersSummary] = useState<string | null>(null);
  const [isTooltipVisible, setTooltipVisible] = useState(false);
  const dropdownTrigger = useRef<HTMLButtonElement>(null);

  function pick(action: string): void {
    setDropdownOpen(false);
    setLastAction(action);
  }

  return (
    <Section
      title="Подсказки и меню"
      description="Help — вопросик рядом с полем, Hint и Tooltip — подсказка при наведении, Dropdown — меню действий у кнопки. FiltersContainer — строка фильтров списка: input, select, multiselect, диапазон чисел и дат."
      file="HintsSection.tsx"
      snippet={SNIPPET}
    >
      <VStack size="s12">
        <HStack size="s12" style={{ alignItems: "center", flexWrap: "wrap" }}>
          <HStack size="s4" style={{ alignItems: "center" }}>
            <Text.Body>Help рядом с полем</Text.Body>
            <Help popup="Ключ API можно получить в личном кабинете сервиса, раздел «Интеграции»." />
          </HStack>
          <Hint overlay="Внимание: действие необратимо" variant={HintVariant.Alert} placement={Placement.TOP}>
            <HStack size="s8" style={{ alignItems: "center" }}>
              <AlertWarningTriangle20Icon />
              <Text.Body>Hint при наведении</Text.Body>
            </HStack>
          </Hint>
          {/* Тултип Hint standard тёмный, но цвет текста кит не задаёт — наследуется тёмный
              из body. Красим содержимое overlay токеном инверсного текста сами. */}
          <Hint
            overlay={<span style={{ color: "var(--invert-text)" }}>Подсказка без предупреждения</span>}
            variant={HintVariant.Standard}
            placement={Placement.TOP}
          >
            <Text.Body>Hint standard</Text.Body>
          </Hint>
          {/* Tooltip кита работает только в управляемом режиме: он всегда отдает visible
              в rc-tooltip, поэтому открытие держим в state, а trigger hover переключает его. */}
          <Tooltip
            overlay="Tooltip с произвольным содержимым"
            trigger={["hover"]}
            visible={isTooltipVisible}
            onVisibleChange={setTooltipVisible}
            placement={Placement.BOTTOM}
            offset={[0, 8]}
          >
            <Text.Body>Tooltip при наведении</Text.Body>
          </Tooltip>
        </HStack>
        <HStack size="s8" style={{ alignItems: "center", flexWrap: "wrap" }}>
          <Button ref={dropdownTrigger} variant={ButtonVariants.ADDITIONAL} onClick={() => setDropdownOpen((value) => !value)}>
            Действия
            <Down20Icon />
          </Button>
          <Button variant={ButtonVariants.ADDITIONAL} onClick={() => setFiltersOpen((value) => !value)}>
            {areFiltersOpen ? "Скрыть фильтры" : "Показать фильтры"}
          </Button>
          {lastAction && <Text.Caption>Выбрано: {lastAction}</Text.Caption>}
        </HStack>
        <FiltersContainer
          open={areFiltersOpen}
          filters={FILTERS}
          searchButtonText="Найти"
          clearButtonHintText="Очистить фильтры"
          onSearch={(values) => setFiltersSummary(`Заполнено фильтров: ${values?.size ?? 0}`)}
          onClear={() => setFiltersSummary(null)}
        />
        {filtersSummary && <Text.Caption>{filtersSummary}</Text.Caption>}
      </VStack>

      {/* Пункты меню по ДС — строки списка, а не кнопки. Готового Dropdown.Item в ките нет,
          поэтому стили пункта (как у Select.Option) лежат в theme.css под классом .menu-item. */}
      <Dropdown open={isDropdownOpen} onClose={() => setDropdownOpen(false)} triggerRef={dropdownTrigger}>
        <VStack size="s0" style={{ padding: 8 }}>
          {ACTIONS.map((action) => (
            <button key={action} className="menu-item" onClick={() => pick(action)}>
              {action}
            </button>
          ))}
        </VStack>
      </Dropdown>
    </Section>
  );
}
