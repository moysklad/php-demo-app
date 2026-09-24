import { useState } from "react";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { HStack } from "@moysklad/uikit/components/HStack";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";

/** Ручная проверка autoResizeIframe(): при изменении числа секций высота iframe должна меняться сразу. */
export function ResizeProbe() {
  const [count, setCount] = useState(1);

  return (
    <VStack size="s12">
      <Text.H3>Проверка autoResizeIframe</Text.H3>
      <Text.Body>
        Меняйте количество секций ниже. Если <code>autoResizeIframe()</code> работает корректно, высота iframe
        должна меняться без перезагрузки страницы.
      </Text.Body>
      <HStack size="s8">
        <Button variant={ButtonVariants.SECONDARY} onClick={() => setCount((value) => Math.max(1, value - 1))}>
          Уменьшить
        </Button>
        <Button variant={ButtonVariants.SECONDARY} onClick={() => setCount((value) => value + 1)}>
          Увеличить
        </Button>
      </HStack>
      <Text.Caption>Количество секций: {count}</Text.Caption>
      {Array.from({ length: count }, (_, index) => (
        <div className="frame" key={index}>
          <Text.BodyStrong>Секция {index + 1}</Text.BodyStrong>
          <Text.Body>
            Этот блок нужен для ручной проверки autoResizeIframe. При добавлении секций высота страницы должна
            меняться сразу.
          </Text.Body>
        </div>
      ))}
    </VStack>
  );
}
