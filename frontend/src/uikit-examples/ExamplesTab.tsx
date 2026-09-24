import { useState } from "react";
import { BentoBlock } from "@moysklad/uikit/components/BentoBlock";
import { Banner } from "@moysklad/uikit/components/Banner";
import { Link } from "@moysklad/uikit/components/Link";
import { SegmentButton } from "@moysklad/uikit/components/SegmentButton";
import { Tabs, type TabSelectedValue } from "@moysklad/uikit/components/Tabs";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { ButtonsSection } from "./sections/ButtonsSection";
import { DataSection } from "./sections/DataSection";
import { FeedbackSection } from "./sections/FeedbackSection";
import { FormSection } from "./sections/FormSection";
import { HintsSection } from "./sections/HintsSection";
import { IconsSection } from "./sections/IconsSection";
import { ImagesSection } from "./sections/ImagesSection";
import { PopupSection } from "./sections/PopupSection";
import { TableSection } from "./sections/TableSection";
import { TypographySection } from "./sections/TypographySection";

const WIDGET_WIDTH = 400;

/**
 * Вкладка «Примеры UI Kit»: живые примеры компонентов @moysklad/uikit, которые чаще всего
 * нужны в интерфейсе решения. Переключатель ширины показывает, как те же компоненты ведут
 * себя в двух контекстах платформы: в главном iframe (вся рабочая область, высота растягивается
 * под контент через autoResizeIframe) и в виджете (колонка 400px фиксированной высоты).
 *
 * Секции разложены по табам, чтобы iframe оставался коротким. Это не только навигация:
 * оверлеи (Carousel, Modal) позиционируются от всего iframe, а autoFocus в дропдауне
 * мультиселекта доскролливает страницу МоегоСклада — на короткой странице и то и другое
 * ведет себя как надо, на длинной уезжает за экран.
 */
export function ExamplesTab() {
  const [mode, setMode] = useState<string | number>("iframe");
  const [group, setGroup] = useState<TabSelectedValue>("basics");

  return (
    <main className="page">
      <BentoBlock as="section" containerClassName="page__wide">
        <VStack size="s12">
          <Text.H2>Примеры UI Kit</Text.H2>
          <Text.Body>
            UI Kit МоегоСклада — рекомендуемая основа интерфейса решения: пользователь получает привычный вид
            и поведение элементов. Пакет{" "}
            <Link href="https://www.npmjs.com/package/@moysklad/uikit" target="_blank" rel="noreferrer">
              @moysklad/uikit
            </Link>{" "}
            содержит React-компоненты; импортируйте их точечно:{" "}
            <code>import {"{ Button }"} from "@moysklad/uikit/components/Button"</code>.
          </Text.Body>
          <Text.Body>
            Каждая секция ниже — самостоятельный файл в <code>frontend/src/uikit-examples/sections/</code>: его можно
            скопировать в свое решение целиком.
          </Text.Body>
          <div>
            <SegmentButton.Group value={mode} onChange={setMode} aria-label="Ширина области">
              <SegmentButton value="iframe">Основной iframe</SegmentButton>
              <SegmentButton value="widget">Виджет, {WIDGET_WIDTH}px</SegmentButton>
            </SegmentButton.Group>
          </div>
          <Banner
            type="info"
            title={mode === "iframe" ? "Главный iframe" : "Виджет"}
            subtitle={
              mode === "iframe"
                ? "Занимает всю рабочую область раздела «Решения». Высота подстраивается под контент: <expand>true</expand> в дескрипторе и sdk.autoResizeIframe() на странице."
                : "Колонка шириной 400px и фиксированной высоты из дескриптора; вертикальный скролл внутри — на стороне решения. Таблицы и формы здесь тесны: для сложных сценариев открывайте попап через sdk.showPopup()."
            }
          />
        </VStack>
      </BentoBlock>

      {/* Табы групп — вне бенто (по дизайн-ревью): они переключают контент снаружи карточки. */}
      <Tabs className="group-tabs page__wide" value={group} onChange={setGroup} aria-label="Разделы примеров">
        <Tabs.Item value="basics">Основы</Tabs.Item>
        <Tabs.Item value="form">Форма</Tabs.Item>
        <Tabs.Item value="feedback">Обратная связь</Tabs.Item>
        <Tabs.Item value="hints">Подсказки и фильтры</Tabs.Item>
        <Tabs.Item value="popups">Попапы</Tabs.Item>
        <Tabs.Item value="table">Таблица</Tabs.Item>
        <Tabs.Item value="images">Изображения</Tabs.Item>
        <Tabs.Item value="card">Карточка</Tabs.Item>
      </Tabs>

      <div className="page__wide" style={{ maxWidth: mode === "widget" ? WIDGET_WIDTH : undefined }}>
        <VStack size="s16">
          {group === "basics" && (
            <>
              <TypographySection />
              <ButtonsSection />
              <IconsSection />
            </>
          )}
          {group === "form" && <FormSection />}
          {group === "feedback" && <FeedbackSection />}
          {group === "hints" && <HintsSection />}
          {group === "popups" && <PopupSection />}
          {group === "table" && <TableSection />}
          {group === "images" && <ImagesSection />}
          {group === "card" && <DataSection />}
        </VStack>
      </div>
    </main>
  );
}
