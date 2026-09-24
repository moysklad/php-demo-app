import { useState } from "react";
import { Button, ButtonSize, ButtonVariants } from "@moysklad/uikit/components/Button";
import { HStack } from "@moysklad/uikit/components/HStack";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { Add20Icon, Delete20Icon, Edit20Icon } from "@moysklad/uikit/icon";
import { Section } from "../Section";

const SNIPPET = `
import { Button, ButtonSize, ButtonVariants } from "@moysklad/uikit/components/Button";
import { Add20Icon } from "@moysklad/uikit/icon";

<Button variant={ButtonVariants.PRIMARY} onClick={save}>Сохранить</Button>
<Button variant={ButtonVariants.ADDITIONAL} isLoading={isSaving}>Проверить</Button>
<Button variant={ButtonVariants.FRAMELESS}>Отмена</Button>
<Button variant={ButtonVariants.FRAMELESS} noPadding>Ссылка-действие без полей</Button>
<Button variant={ButtonVariants.ADDITIONAL} isIconButton aria-label="Добавить"><Add20Icon /></Button>
<Button variant={ButtonVariants.PRIMARY} size={ButtonSize.XL} stretch>На всю ширину</Button>
`;

/** Кнопки: один основной вариант на экран, остальные — второстепенные. */
export function ButtonsSection() {
  const [isLoading, setLoading] = useState(false);

  function simulate(): void {
    setLoading(true);
    window.setTimeout(() => setLoading(false), 1500);
  }

  return (
    <Section
      title="Кнопки"
      description="PRIMARY — главное действие формы, ADDITIONAL — второстепенные, SECONDARY — третьестепенные, FRAMELESS — отмена и ссылки-действия. Размер для веба — L, в мобильной версии — XL со stretch; в узкой колонке используйте stretch."
      file="ButtonsSection.tsx"
      snippet={SNIPPET}
    >
      <VStack size="s12">
        <HStack size="s8" style={{ flexWrap: "wrap", alignItems: "center" }}>
          <Button variant={ButtonVariants.PRIMARY}>Primary</Button>
          <Button variant={ButtonVariants.ADDITIONAL}>Additional</Button>
          <Button variant={ButtonVariants.SECONDARY}>Secondary</Button>
          <Button variant={ButtonVariants.FRAMELESS}>Frameless</Button>
          {/* Frameless-ссылка: noPadding убирает поля, высота равна строке текста — 20px. */}
          <Button variant={ButtonVariants.FRAMELESS} noPadding>
            Frameless-ссылка
          </Button>
        </HStack>
        <HStack size="s8" style={{ flexWrap: "wrap", alignItems: "center" }}>
          <Button variant={ButtonVariants.ADDITIONAL} size={ButtonSize.L}>
            Размер L
          </Button>
          <Button variant={ButtonVariants.ADDITIONAL} size={ButtonSize.XL}>
            Размер XL
          </Button>
        </HStack>
        <HStack size="s8" style={{ flexWrap: "wrap", alignItems: "center" }}>
          <Button variant={ButtonVariants.PRIMARY} isLoading={isLoading} onClick={simulate}>
            {isLoading ? "Сохраняем…" : "Показать загрузку"}
          </Button>
          <Button variant={ButtonVariants.PRIMARY} disabled>
            Недоступна
          </Button>
          <Button variant={ButtonVariants.ADDITIONAL} isIconButton aria-label="Добавить">
            <Add20Icon />
          </Button>
          {/* Кнопка-ссылка: as="a" + href — рендерится <a>, но выглядит как кнопка. */}
          <Button as="a" variant={ButtonVariants.FRAMELESS} href="https://dev.moysklad.ru" target="_blank" rel="noreferrer">
            Документация
          </Button>
          <Button variant={ButtonVariants.ADDITIONAL} isIconButton aria-label="Изменить">
            <Edit20Icon />
          </Button>
          <Button variant={ButtonVariants.ADDITIONAL} isIconButton aria-label="Удалить">
            <Delete20Icon />
          </Button>
        </HStack>
        <Text.Caption>Кнопка на всю ширину контейнера — типична для виджета:</Text.Caption>
        <Button variant={ButtonVariants.PRIMARY} stretch>
          Подключить интеграцию
        </Button>
      </VStack>
    </Section>
  );
}
