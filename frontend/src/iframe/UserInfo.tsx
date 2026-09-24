import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import type { IframePageData } from "./page-data";

export function UserInfo({ data }: { data: Pick<IframePageData, "uid" | "fio" | "accountId" | "isAdmin"> }) {
  return (
    <VStack size="s8">
      <Text.H3>Информация о пользователе</Text.H3>
      <Text.Body>
        Текущий пользователь: {data.uid} ({data.fio})
      </Text.Body>
      <Text.Body>Идентификатор аккаунта: {data.accountId}</Text.Body>
      <Text.Body>
        Уровень доступа:{" "}
        <Text.BodyStrong as="span">{data.isAdmin ? "администратор аккаунта" : "простой пользователь"}</Text.BodyStrong>
      </Text.Body>
    </VStack>
  );
}
