import { Link } from "@moysklad/uikit/components/Link";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { Section } from "../Section";

const SNIPPET = `
import { Text } from "@moysklad/uikit/components/Text";
import { Link } from "@moysklad/uikit/components/Link";

<Text.H2>Заголовок раздела</Text.H2>
<Text.Body>Обычный текст</Text.Body>
<Text.Caption>Подпись</Text.Caption>
<Text variant="bodyStrong" colorToken="critical">Ошибка сохранения</Text>
<Link href="https://dev.moysklad.ru" target="_blank">Ссылка</Link>
`;

/** Типографика: все тексты — через Text, чтобы размеры и цвета совпадали с интерфейсом МоегоСклада. */
export function TypographySection() {
  return (
    <Section
      title="Типографика"
      description="Заголовки, основной текст и подписи. Цвет задается токеном colorToken, а не CSS."
      file="TypographySection.tsx"
      snippet={SNIPPET}
    >
      <VStack size="s8">
        <Text.H1>Заголовок H1</Text.H1>
        <Text.H2>Заголовок H2</Text.H2>
        <Text.H3>Заголовок H3</Text.H3>
        <Text.H4>Заголовок H4</Text.H4>
        <Text.BodyXL>Крупный текст (bodyXL)</Text.BodyXL>
        <Text.BodyL>Увеличенный текст (bodyL)</Text.BodyL>
        <Text.Body>Основной текст (body) — для описаний и полей.</Text.Body>
        <Text.BodyStrong>Акцентный текст (bodyStrong)</Text.BodyStrong>
        <Text.Caption>Подпись (caption) — для вторичной информации</Text.Caption>
        <Text.Body>
          Цвета: <Text colorToken="primary">primary</Text>, <Text colorToken="secondary">secondary</Text>,{" "}
          <Text colorToken="tertiary">tertiary</Text>, <Text colorToken="accent">accent</Text>,{" "}
          <Text colorToken="positive">positive</Text>, <Text colorToken="critical">critical</Text>,{" "}
          <Text colorToken="attention">attention</Text>
        </Text.Body>
        <Text.Body>
          Ссылка на документацию:{" "}
          <Link href="https://dev.moysklad.ru/doc/api/vendor/1.0/" target="_blank" rel="noreferrer">
            Vendor API 1.0
          </Link>
        </Text.Body>
      </VStack>
    </Section>
  );
}
