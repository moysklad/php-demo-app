export type LoyaltyConnectionState = {
  state: "not-connected" | "connected" | "reconnect-required";
  /** Цвет бейджа статуса (Badge из кита): зеленый — подключено, оранжевый — нужны действия. */
  badge: "green" | "orange";
  title: string;
  details: string;
  externalSearch: boolean;
};
