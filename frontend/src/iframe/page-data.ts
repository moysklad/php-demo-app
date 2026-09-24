// [feature:loyalty] программа лояльности: данные вкладки приходят из модуля src/php/loyalty.
import type { LoyaltyConnectionState } from "../loyalty/types";

/** Состояние решения для карточки статуса; в таком же виде его возвращает POST utils/update-settings.php. */
export type AppStatusView = {
  /** Цвет бейджа статуса (Badge из кита): зеленый — готово, оранжевый — нужны действия. */
  badge: "green" | "orange";
  title: string;
  showDetails: boolean;
  infoMessage?: string;
  store?: string;
};

/**
 * Данные страницы основного iframe. Сервер (src/php/entry/iframe.inc.php) сериализует их
 * в <script id="page-data">, клиент (client/main.tsx) читает при монтировании.
 * Файл содержит только типы: его импортируют и сервер, и браузерный код.
 */
export type IframePageData = {
  accountId: string;
  uid: string;
  fio: string;
  isAdmin: boolean;
  contextNonce: string;
  appVersion: string;
  infoMessage?: string;
  store?: string;
  storesValues: string[];
  status: AppStatusView;
  // [feature:loyalty] программа лояльности
  loyalty: LoyaltyConnectionState;
  defaultLoyaltyProviderUrl: string;
};
