import { HStack } from "@moysklad/uikit/components/HStack";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import {
  Add20Icon,
  AlertWarningTriangle20Icon,
  Check20Icon,
  Copy20Icon,
  Delete20Icon,
  Edit20Icon,
  Filter20Icon,
  InformationCircle20Icon,
  Print20Icon,
  Search20Icon,
  Settings20Icon
} from "@moysklad/uikit/icon";
import { Section } from "../Section";

const SNIPPET = `
import { Check20Icon, Search20Icon } from "@moysklad/uikit/icon";

<Search20Icon />
<Check20Icon stroke="var(--text-positive)" />
`;

const ICONS = [
  ["Add20Icon", <Add20Icon key="add" />],
  ["Edit20Icon", <Edit20Icon key="edit" />],
  ["Delete20Icon", <Delete20Icon key="delete" />],
  ["Copy20Icon", <Copy20Icon key="copy" />],
  ["Search20Icon", <Search20Icon key="search" />],
  ["Filter20Icon", <Filter20Icon key="filter" />],
  ["Settings20Icon", <Settings20Icon key="settings" />],
  ["Print20Icon", <Print20Icon key="print" />],
  ["Check20Icon", <Check20Icon key="check" stroke="var(--text-positive)" />],
  ["AlertWarningTriangle20Icon", <AlertWarningTriangle20Icon key="alert" stroke="var(--text-critical)" />],
  ["InformationCircle20Icon", <InformationCircle20Icon key="info" stroke="var(--text-accent)" />]
] as const;

/** Иконки: единый набор МоегоСклада в трех размерах (12, 16, 20). */
export function IconsSection() {
  return (
    <Section
      title="Иконки"
      description="Имя иконки — <Название><Размер>Icon, размеры 12/16/20. Цвет задается через stroke, лучше токеном кита."
      file="IconsSection.tsx"
      snippet={SNIPPET}
    >
      <HStack size="s16" style={{ flexWrap: "wrap" }}>
        {ICONS.map(([name, icon]) => (
          <VStack key={name} size="s4" style={{ alignItems: "center", width: 170, textAlign: "center", overflowWrap: "anywhere" }}>
            {icon}
            <Text.Caption>{name}</Text.Caption>
          </VStack>
        ))}
      </HStack>
    </Section>
  );
}
