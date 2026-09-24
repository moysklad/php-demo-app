import { type FormEvent, useState } from "react";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { HStack } from "@moysklad/uikit/components/HStack";
import { Input } from "@moysklad/uikit/components/Input";
import { Modal } from "@moysklad/uikit/components/Modal";
import { Tabs, type TabSelectedValue } from "@moysklad/uikit/components/Tabs";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";

const RESULT_REQUEST = `PUT https://apps-api.moysklad.ru/api/vendor/1.0/apps/{appId}/{accountId}/loyalty

{
  "url": "https://loyalty.example.com/api",
  "token": "generated-provider-token",
  "externalSearch": true
}`;

/**
 * Демонстрация рекомендованного сценария: пользователь входит в программу лояльности,
 * а провайдер сам передает настройки в МойСклад. Во внешнюю систему диалог не обращается —
 * он показывает, какие данные и каким запросом попадут в МойСклад.
 */
export function AuthModal({ isVisible, onClose }: { isVisible: boolean; onClose: () => void }) {
  const [mode, setMode] = useState<TabSelectedValue>("login");
  const [login, setLogin] = useState("");
  const [password, setPassword] = useState("");
  const [isDone, setDone] = useState(false);

  function reset(): void {
    setMode("login");
    setLogin("");
    setPassword("");
    setDone(false);
  }

  function close(): void {
    reset();
    onClose();
  }

  function submit(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    setDone(true);
  }

  const subtitle = isDone
    ? mode === "login"
      ? "Пользователь вошёл в программу лояльности, после чего провайдер получает нужные настройки."
      : "Пользователь зарегистрирован, после чего провайдер получает нужные настройки."
    : mode === "login"
      ? "Войдите или зарегистрируйтесь, чтобы подключить аккаунт к МоемуСкладу."
      : "Зарегистрируйтесь, если аккаунта ещё нет, затем провайдер передаст настройки в МойСклад.";

  // Modal.Provider дает модалке контекст (Escape) и уносит в портал все, что в него обернуто, — только сам Modal.
  return (
    <Modal.Provider>
      <Modal isVisible={isVisible} onClose={close} maxWidth={560}>
        <Modal.Header>
          <VStack size="s4">
            <Text.H2>Программа лояльности</Text.H2>
            <Text.Body>{subtitle}</Text.Body>
          </VStack>
        </Modal.Header>
        <Modal.Body>
          {!isDone ? (
            <form id="authForm" onSubmit={submit} autoComplete="off">
              <VStack size="s12">
                <Tabs value={mode} onChange={setMode} aria-label="Способ авторизации">
                  <Tabs.Item value="login">Войти</Tabs.Item>
                  <Tabs.Item value="register">Зарегистрироваться</Tabs.Item>
                </Tabs>
                <Input name="login" label="Логин" placeholder="login" value={login} onChange={(e) => setLogin(e.target.value)} required />
                <Input
                  name="password"
                  label="Пароль"
                  type="password"
                  placeholder="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                />
              </VStack>
            </form>
          ) : (
            <VStack size="s16">
              <VStack size="s12">
                <Step number={1} title="Провайдер авторизует пользователя">
                  Программа лояльности получает login «{login.trim() || "login"}» и password на своей стороне.
                </Step>
                <Step number={2} title="Провайдер находит настройки аккаунта">
                  По учетной записи определяются baseURL, токен и режим внешнего поиска покупателей.
                </Step>
                <Step number={3} title="Провайдер передает настройки в МойСклад">
                  Для передачи используется PUT-запрос.
                </Step>
              </VStack>
              <pre className="log">{RESULT_REQUEST}</pre>
            </VStack>
          )}
        </Modal.Body>
        {/* Футер есть в обоих состояниях: без него у Modal.Body нет нижнего отступа. */}
        <Modal.Footer>
          {!isDone ? (
            <HStack size="s16">
              <Button type="submit" form="authForm" variant={ButtonVariants.PRIMARY}>
                {mode === "login" ? "Войти и продолжить" : "Зарегистрироваться и продолжить"}
              </Button>
              <Button variant={ButtonVariants.FRAMELESS} onClick={close}>
                Не сейчас
              </Button>
            </HStack>
          ) : (
            <HStack size="s16">
              <Button variant={ButtonVariants.PRIMARY} onClick={close}>
                Завершить настройку
              </Button>
              <Button variant={ButtonVariants.FRAMELESS} onClick={() => setDone(false)}>
                Назад
              </Button>
            </HStack>
          )}
        </Modal.Footer>
      </Modal>
    </Modal.Provider>
  );
}

function Step({ number, title, children }: { number: number; title: string; children: string | (string | number)[] }) {
  return (
    <HStack size="s12">
      <Text.BodyStrong>{number}.</Text.BodyStrong>
      <VStack size="s2">
        <Text.BodyStrong>{title}</Text.BodyStrong>
        <Text.Body>{children}</Text.Body>
      </VStack>
    </HStack>
  );
}
