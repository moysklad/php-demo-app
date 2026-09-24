import WidgetSDK, { type WidgetSDKInstance } from "@moysklad/js-widget-sdk";

declare global {
  interface Window {
    widgetSdk?: WidgetSDKInstance;
    widgetLog?: (label: string, payload?: unknown) => void;
  }
}

/**
 * Один экземпляр JS Widget SDK на страницу. Создается при загрузке бандла, до монтирования React:
 * хост присылает Open сразу после загрузки iframe. Подписываться можно позже (например, из useEffect) —
 * js-widget-sdk ≥ 1.3 доигрывает последнее Open позднему подписчику, поэтому обработчик Open должен быть
 * идемпотентным. Доступен как window.widgetSdk — удобно дергать методы из консоли браузера.
 */
export const sdk = WidgetSDK.create({ debug: true }) as WidgetSDKInstance & Record<string, any>;

window.widgetSdk = sdk;
