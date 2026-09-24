import { useState } from "react";
import { BentoBlock } from "@moysklad/uikit/components/BentoBlock";
import { Tabs, type TabSelectedValue } from "@moysklad/uikit/components/Tabs";
// [feature:loyalty] программа лояльности: вкладка живет в модуле frontend/src/loyalty.
import { LoyaltyTab } from "../loyalty/LoyaltyTab";
// [feature:uikit-examples] примеры UI Kit: вкладка живет в модуле frontend/src/uikit-examples.
import { ExamplesTab } from "../uikit-examples/ExamplesTab";
import type { AppStatusView, IframePageData } from "./page-data";
import { ResizeProbe } from "./ResizeProbe";
import { SettingsForm } from "./SettingsForm";
import { StatusCard } from "./StatusCard";
import { UserInfo } from "./UserInfo";

/**
 * Основной iframe решения. У решения одна такая страница, поэтому разделы —
 * это вкладки внутри нее; модули добавляют свои вкладки в помеченных местах.
 * Минимальный iframe — только ветка tab === "main": вкладки и импорты модулей,
 * помеченные [feature:…], можно удалить целиком.
 * Вкладки размонтируются при переключении, поэтому все, что меняется после загрузки страницы
 * (сохраненные настройки, состояние лояльности), хранится здесь, а не во вкладках.
 */
export function IframePage({ data }: { data: IframePageData }) {
  const [tab, setTab] = useState<TabSelectedValue>("main");
  const [status, setStatus] = useState(data.status);
  const [settings, setSettings] = useState({ infoMessage: data.infoMessage ?? "", store: data.store ?? "" });
  // [feature:loyalty] программа лояльности
  const [loyalty, setLoyalty] = useState(data.loyalty);

  function handleSettingsSaved(nextStatus: AppStatusView): void {
    setStatus(nextStatus);
    setSettings({ infoMessage: nextStatus.infoMessage ?? "", store: nextStatus.store ?? "" });
  }

  return (
    <>
      <Tabs className="page-tabs" value={tab} onChange={setTab} aria-label="Разделы решения">
        <Tabs.Item value="main">Основное</Tabs.Item>
        {/* [feature:loyalty] программа лояльности */}
        <Tabs.Item value="loyalty">Программа лояльности</Tabs.Item>
        {/* [feature:uikit-examples] примеры UI Kit */}
        <Tabs.Item value="uikit">Примеры UI Kit</Tabs.Item>
      </Tabs>

      {tab === "main" && (
        <main className="page">
          <BentoBlock as="section">
            <UserInfo data={data} />
            <StatusCard appVersion={data.appVersion} status={status} />
          </BentoBlock>
          <BentoBlock as="section">
            <SettingsForm data={data} saved={settings} onSaved={handleSettingsSaved} />
          </BentoBlock>
          <BentoBlock as="section" containerClassName="page__wide">
            <ResizeProbe />
          </BentoBlock>
        </main>
      )}

      {/* [feature:loyalty] программа лояльности */}
      {tab === "loyalty" && (
        <LoyaltyTab
          isAdmin={data.isAdmin}
          contextNonce={data.contextNonce}
          loyalty={loyalty}
          onLoyaltyChange={setLoyalty}
          defaultLoyaltyProviderUrl={data.defaultLoyaltyProviderUrl}
        />
      )}

      {/* [feature:uikit-examples] примеры UI Kit */}
      {tab === "uikit" && <ExamplesTab />}
    </>
  );
}
