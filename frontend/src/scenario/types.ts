/** Действие из блока <scenario> дескриптора: name уходит в actionName запроса, title видит администратор. */
export type ScenarioAction = {
  name: string;
  title: string;
};

/** Срабатывание действия, записанное обработчиком POST .../apps/{appId}/{accountId}/scenario. */
export type ScenarioEvent = {
  id: number | null;
  createdAt: string;
  actionName: string;
  actionTitle: string;
  /** ADD, MODIFY, CHANGE_STATUS, OVERDUE, CHANGE_PRODUCTION_STATE — событие сценария в МоемСкладе. */
  actionType: string;
  objectType: string;
  objectId: string;
  objectName: string;
  /** ok — ответили 200, rejected — 400 с сообщением пользователю, failed — 5xx, МойСклад повторит. */
  outcome: "ok" | "rejected" | "failed";
  message: string;
};

/** Журнал в pageData окна чатов и в ответе POST scenario/events.php. */
export type ScenarioJournalData = {
  scenarioActions: ScenarioAction[];
  scenarioEvents: ScenarioEvent[];
};
