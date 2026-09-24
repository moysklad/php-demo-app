import { useMemo, useState } from "react";
import { createColumnHelper, getCoreRowModel, useReactTable } from "@tanstack/react-table";
import { Pagination } from "@moysklad/uikit/components/Pagination";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { BooleanCell, type DefaultHeaderMeta, PlainTextHeader, Table, TextCell } from "@moysklad/uikit/data-grid";
import { Section } from "../Section";

const SNIPPET = `
import { createColumnHelper, getCoreRowModel, useReactTable } from "@tanstack/react-table";
import { PlainTextHeader, Table, TextCell, type DefaultHeaderMeta } from "@moysklad/uikit/data-grid";

const helper = createColumnHelper<Product>();
const columns = [
  helper.accessor("name", {
    header: PlainTextHeader,
    meta: { displayName: "Наименование" } as DefaultHeaderMeta,
    cell: ({ cell }) => <TextCell label={cell.getValue()} isArchived={false} isDisabled={false} />
  })
];
const table = useReactTable({ data: products, columns, getCoreRowModel: getCoreRowModel() });

// fullHeight={false} обязателен в iframe с autoResizeIframe: иначе таблица тянется до низа окна,
// а окно растет под контент — бесконечный рост высоты.
<Table table={table} isLoading={false} fullWidth fullHeight={false} />
`;

type Product = {
  id: string;
  name: string;
  code: string;
  price: string;
  stock: string;
  updatedAt: string;
  isSynced: boolean;
  /** Table требует у строки необязательный onRowClick — см. WithRowClickLink в @moysklad/uikit/data-grid. */
  onRowClick?: () => void;
};

const PRODUCTS: Product[] = [
  { id: "1", name: "Футболка базовая, белая", code: "TS-001", price: "1 290 ₽", stock: "120", updatedAt: "27.08.2026 10:15", isSynced: true },
  { id: "2", name: "Худи оверсайз, графит", code: "HD-014", price: "4 990 ₽", stock: "18", updatedAt: "27.08.2026 09:40", isSynced: true },
  { id: "3", name: "Кепка с логотипом", code: "CP-002", price: "990 ₽", stock: "0", updatedAt: "26.08.2026 18:02", isSynced: false },
  { id: "4", name: "Носки, набор 3 пары", code: "SK-100", price: "690 ₽", stock: "342", updatedAt: "26.08.2026 12:30", isSynced: true },
  { id: "5", name: "Рюкзак городской", code: "BG-007", price: "5 490 ₽", stock: "7", updatedAt: "25.08.2026 16:55", isSynced: false }
];

const helper = createColumnHelper<Product>();

function textColumn(key: keyof Product, displayName: string, size?: number) {
  return helper.accessor(key, {
    id: key,
    header: PlainTextHeader,
    meta: { displayName } as DefaultHeaderMeta,
    size,
    cell: ({ cell }) => <TextCell label={String(cell.getValue())} isArchived={false} isDisabled={false} />
  });
}

const COLUMNS = [
  textColumn("name", "Наименование", 260),
  textColumn("code", "Артикул", 110),
  textColumn("price", "Цена", 110),
  textColumn("stock", "Остаток", 100),
  textColumn("updatedAt", "Обновлено", 160),
  helper.accessor("isSynced", {
    id: "isSynced",
    header: PlainTextHeader,
    meta: { displayName: "Синхронизирован" } as DefaultHeaderMeta,
    size: 150,
    cell: ({ cell }) => <BooleanCell isActive={cell.getValue()} label={cell.getValue() ? "Да" : "Нет"} />
  })
];

/** Таблица данных: data-grid кита поверх @tanstack/react-table. */
export function TableSection() {
  const [selected, setSelected] = useState<string | null>(null);
  const [isLoading, setLoading] = useState(false);
  const data = useMemo(() => PRODUCTS, []);
  const table = useReactTable({ data, columns: COLUMNS, getCoreRowModel: getCoreRowModel() });

  function reload(): void {
    setLoading(true);
    window.setTimeout(() => setLoading(false), 1500);
  }

  return (
    <Section
      title="Таблица"
      description="Table из @moysklad/uikit/data-grid рисует данные, которые подготовил useReactTable из @tanstack/react-table (зависимость кита — добавьте ее в свой package.json). В узкой колонке таблице нужен горизонтальный скролл контейнера. В iframe с autoResizeIframe передавайте fullHeight={false}: по умолчанию таблица растягивается до низа окна, а окно растет под контент — высота уходит в бесконечность."
      file="TableSection.tsx"
      snippet={SNIPPET}
    >
      <VStack size="s8">
        <div style={{ overflowX: "auto" }}>
          <Table<Product> table={table} isLoading={isLoading} fullWidth fullHeight={false} onRowClick={(row) => setSelected(row.original.name)} />
        </div>
        {selected && <Text.Caption>Клик по строке: «{selected}»</Text.Caption>}
        <Pagination
          label={`1–${PRODUCTS.length} из ${PRODUCTS.length}`}
          isGoBackDisabled
          onFirst={reload}
          onPrev={reload}
          onNext={reload}
          onLast={reload}
        />
      </VStack>
    </Section>
  );
}
