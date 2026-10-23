import type { AppStatusView } from "../iframe/page-data";
// [feature:scenario] действия в сценариях: журнал приходит из модуля src/php/scenario.
import type { ScenarioJournalData } from "../scenario/types";

/**
 * Данные окна чатов: поле pageData в ответе POST entry/user-context.php с page: "chat",
 * формирует их buildChatPageData() в src/php/entry/iframe.inc.php.
 */
export type ChatPageData = ScenarioJournalData & {
  accountId: string;
  uid: string;
  fio: string;
  isAdmin: boolean;
  contextNonce: string;
  appVersion: string;
  status: AppStatusView;
};
