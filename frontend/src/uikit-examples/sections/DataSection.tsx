import { useState } from "react";
import { Breadcrumbs } from "@moysklad/uikit/components/Breadcrumbs";
import { useFileUploader } from "@moysklad/uikit/components/FileUploader";
import { LabelValue } from "@moysklad/uikit/components/LabelValue";
import { LabelValueDate } from "@moysklad/uikit/components/LabelValueDate";
import { LabelValueFile } from "@moysklad/uikit/components/LabelValueFile";
import { LabelValueInput } from "@moysklad/uikit/components/LabelValueInput";
import { LabelValueLink } from "@moysklad/uikit/components/LabelValueLink";
import { LabelValueSelect } from "@moysklad/uikit/components/LabelValueSelect";
import { Link } from "@moysklad/uikit/components/Link";
import { Listing } from "@moysklad/uikit/components/Listing";
import { Panel } from "@moysklad/uikit/components/Panel";
import { type ISelectOption } from "@moysklad/uikit/components/Select";
import { StatusBadge, StatusColor, type StatusBadgeOption } from "@moysklad/uikit/components/StatusBadge";
import { Tabs, type TabSelectedValue } from "@moysklad/uikit/components/Tabs";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { Section } from "../Section";

const SNIPPET = `
import { LabelValue } from "@moysklad/uikit/components/LabelValue";
import { LabelValueSelect } from "@moysklad/uikit/components/LabelValueSelect";
import { LabelValueInput } from "@moysklad/uikit/components/LabelValueInput";
import { Panel } from "@moysklad/uikit/components/Panel";

<Panel columnsCount={2} items={[
  { id: "buyer", width: 1, element: <LabelValue label="Покупатель" value="ООО «Ромашка»" /> },
  { id: "store", width: 1, element: <LabelValueSelect label="Склад" options={STORES} value={store} onChange={setStore} /> },
  { id: "manager", width: 1, element: <LabelValueInput name="manager" label="Менеджер" value={manager} onChange={(e) => setManager(e.target.value)} /> }
]} />
`;

const ORDERS = ["№00121", "№00122", "№00123", "№00124"];

const STATUSES: StatusBadgeOption<string>[] = [
  { label: "Новый", value: "new", color: StatusColor.Blue },
  { label: "Отгружен", value: "shipped", color: StatusColor.Green },
  { label: "Отменен", value: "cancelled", color: StatusColor.Red }
];

const STORES: ISelectOption<string>[] = ["Основной склад", "Розница", "Возвраты"].map((name) => ({ label: name, value: name }));

/** Карточка сущности и навигация: пары «поле — значение» во всех вариантах, вкладки, хлебные крошки, листание. */
export function DataSection() {
  const [tab, setTab] = useState<TabSelectedValue>("orders");
  const [orderIndex, setOrderIndex] = useState(2);
  const [status, setStatus] = useState(STATUSES[1]);
  const [orderLink, setOrderLink] = useState("https://service.example/orders/00123");
  const [store, setStore] = useState<ISelectOption<string>>(STORES[0]);
  const [manager, setManager] = useState("Иванова А.");
  const [shipmentDate, setShipmentDate] = useState<Date | null>(new Date());
  const attachment = useFileUploader({});

  return (
    <Section
      title="Карточка и навигация"
      description="LabelValue и Panel — поля и сетка карточки сущности в стиле МоегоСклада: текст, выбор из списка, ввод, файл, дата и ссылка. StatusBadge — статус со сменой; Tabs — разделы внутри iframe; Breadcrumbs и Listing — навигация по спискам."
      file="DataSection.tsx"
      snippet={SNIPPET}
    >
      <VStack size="s16">
        {/* Наведение у крошки появляется только вместе с onClick — некликабельная крошка выглядит как текст. */}
        <Breadcrumbs>
          <Breadcrumbs.Item onClick={() => setOrderIndex(0)}>Интеграции</Breadcrumbs.Item>
          <Breadcrumbs.Item onClick={() => setOrderIndex(0)}>Заказы</Breadcrumbs.Item>
          <Breadcrumbs.Item>{ORDERS[orderIndex]}</Breadcrumbs.Item>
        </Breadcrumbs>
        <Tabs value={tab} onChange={setTab} aria-label="Разделы карточки">
          <Tabs.Item value="orders">Заказ</Tabs.Item>
          <Tabs.Item value="products">Товары</Tabs.Item>
          <Tabs.Item value="history">История</Tabs.Item>
        </Tabs>
        {tab === "orders" && (
          <VStack size="s12">
            {/* StatusBadge — статус со сменой из дропдауна, цвета из палитры статусов МоегоСклада. */}
            <div>
              <StatusBadge title={status.label} value={status} availableStatuses={STATUSES} onSelect={setStatus} />
            </div>
            {/* Panel — сетка полей шапки документа; width задается в колонках сетки.
                Поля — варианты LabelValue: текст, select (обычный и disabled), input, файл и дата. */}
            <Panel
              columnsCount={2}
              items={[
                { id: "number", width: 1, element: <LabelValue label="Номер в сервисе" value={ORDERS[orderIndex]} /> },
                { id: "buyer", width: 1, element: <LabelValue label="Покупатель" value="ООО «Ромашка»" /> },
                {
                  id: "status",
                  width: 1,
                  element: <LabelValue label="Статус в сервисе" value={status.label} helpPopupContent="Статус приходит из сервиса раз в час" />
                },
                { id: "comment", width: 1, element: <LabelValue label="Комментарий" value="" isEmpty /> },
                {
                  id: "store",
                  width: 1,
                  element: <LabelValueSelect label="Склад отгрузки" options={STORES} value={store} onChange={setStore} />
                },
                {
                  id: "channel",
                  width: 1,
                  element: <LabelValueSelect label="Канал продаж" options={[]} value={{ label: "Сайт", value: "site" }} disabled />
                },
                {
                  id: "manager",
                  width: 1,
                  element: <LabelValueInput name="manager" label="Менеджер" value={manager} onChange={(e) => setManager(e.target.value)} />
                },
                {
                  id: "shipment",
                  width: 1,
                  element: <LabelValueDate label="Дата отгрузки" value={shipmentDate} onChange={(date) => setShipmentDate(date)} />
                },
                { id: "attachment", width: 1, element: <LabelValueFile label="Накладная" fileUploader={attachment} emptyText="Добавить файл" /> }
              ]}
            />
            {/* LabelValueLink — поле-ссылка с инлайн-редактированием. */}
            <LabelValueLink name="orderLink" label="Заказ в сервисе" value={orderLink} onChange={(e) => setOrderLink(e.target.value)} />
            <Text.Body>
              Обычная ссылка в тексте — компонент Link:{" "}
              <Link href="https://dev.moysklad.ru" target="_blank" rel="noreferrer">
                документация для разработчиков
              </Link>
              .
            </Text.Body>
          </VStack>
        )}
        {tab === "products" && <Text.Body>Позиции заказа: 3 товара на 12 480 ₽.</Text.Body>}
        {tab === "history" && <Text.Body>27.08.2026 10:15 — заказ выгружен в сервис.</Text.Body>}
        <Listing
          current={orderIndex + 1}
          total={ORDERS.length}
          isPrevDisabled={orderIndex === 0}
          isNextDisabled={orderIndex === ORDERS.length - 1}
          onPrev={() => setOrderIndex((index) => Math.max(0, index - 1))}
          onNext={() => setOrderIndex((index) => Math.min(ORDERS.length - 1, index + 1))}
        />
      </VStack>
    </Section>
  );
}
