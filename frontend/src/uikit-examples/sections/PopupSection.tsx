import { useState } from "react";
import { Banner } from "@moysklad/uikit/components/Banner";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { HStack } from "@moysklad/uikit/components/HStack";
import { Modal } from "@moysklad/uikit/components/Modal";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { sdk } from "../../ui/sdk";
import { Section } from "../Section";

const SNIPPET = `
// Дескриптор решения (src/php/utils/descriptor.xml.php):
// <popups><popup><name>some-popup</name><sourceUrl>https://…/entry/popup.php</sourceUrl></popup></popups>
import { Modal } from "@moysklad/uikit/components/Modal";
import { sdk } from "../../ui/sdk";

// Полноценный диалог — попап МоегоСклада: поверх всего интерфейса, из любого контекста.
// Промис резолвится, когда попап вызовет sdk.closePopup(popupResponse).
const response = await sdk.showPopup("some-popup", { orderId: "00123" });

// Легкое подтверждение на короткой странице главного iframe — Modal кита:
<Modal isVisible={isVisible} onClose={close} maxWidth={480}>
  <Modal.Header>Подтверждение</Modal.Header>
  <Modal.Body>…</Modal.Body>
  <Modal.Footer>…</Modal.Footer>
</Modal>
`;

/** Диалоги: попап МоегоСклада для полноценных сценариев, Modal кита — для легких подтверждений. */
export function PopupSection() {
  const [result, setResult] = useState<string | null>(null);
  const [isOpening, setOpening] = useState(false);
  const [isModalVisible, setModalVisible] = useState(false);

  async function openPopup(): Promise<void> {
    setOpening(true);
    setResult(null);

    try {
      const response = await sdk.showPopup("some-popup", { source: "uikit-examples" });
      setResult(`Ответ попапа: ${JSON.stringify(response)}`);
    } catch (error) {
      setResult(`Ошибка: ${error instanceof Error ? error.message : String(error)}`);
    } finally {
      setOpening(false);
    }
  }

  return (
    <Section
      title="Диалоги: попап платформы и Modal кита"
      description="Основной способ — попап МоегоСклада: sdk.showPopup() открывает страницу решения поверх всего интерфейса, sdk.closePopup(popupResponse) возвращает результат. Modal кита годится для легких подтверждений на короткой странице главного iframe. Sidepage и Snackbar внутри iframe не рекомендуем."
      file="PopupSection.tsx"
      snippet={SNIPPET}
    >
      <VStack size="s12">
        <Banner
          type="warning"
          title="Когда какой диалог"
          subtitle="Оверлеи кита рисуются внутри iframe: затемнение и центрирование ограничены его рамкой, шапка МоегоСклада остается активной, а если страница длиннее экрана, position: fixed считается от всего iframe и окно уезжает за экран. Поэтому формы, выбор из списка и мастера — только через попап платформы: он открывается самим МоемСкладом поверх всей страницы и работает и из виджета. Modal кита — для подтверждений в пару кнопок на странице не выше экрана; в виджете шириной 400px ему места нет."
        />
        <Text.Body>
          Страница попапа — обычная страница решения (здесь: <code>src/php/entry/popup.php</code>); ее адрес объявлен в дескрипторе
          в секции <code>&lt;popups&gt;</code>. Попап получает событие OpenPopup с параметрами вызова и закрывает себя
          через <code>sdk.closePopup()</code>.
        </Text.Body>
        <HStack size="s8" style={{ alignItems: "center", flexWrap: "wrap" }}>
          <Button variant={ButtonVariants.PRIMARY} onClick={openPopup} isLoading={isOpening}>
            Открыть попап
          </Button>
          <Button variant={ButtonVariants.ADDITIONAL} onClick={() => setModalVisible(true)}>
            Открыть Modal кита
          </Button>
        </HStack>
        {/* Ответ — JSON без пробелов: без переноса строка вылезает за карточку в узкой колонке. */}
        {result && <Text.Caption style={{ overflowWrap: "anywhere" }}>{result}</Text.Caption>}
      </VStack>
      <Modal isVisible={isModalVisible} onClose={() => setModalVisible(false)} maxWidth={480}>
        <Modal.Header>Подтверждение</Modal.Header>
        <Modal.Body>
          <Text.Body>Легкое подтверждение внутри iframe: удалить связку заказа №00123 с сервисом?</Text.Body>
        </Modal.Body>
        <Modal.Footer>
          <HStack size="s16">
            <Button variant={ButtonVariants.PRIMARY} onClick={() => setModalVisible(false)}>
              Удалить
            </Button>
            <Button variant={ButtonVariants.FRAMELESS} onClick={() => setModalVisible(false)}>
              Отмена
            </Button>
          </HStack>
        </Modal.Footer>
      </Modal>
    </Section>
  );
}
