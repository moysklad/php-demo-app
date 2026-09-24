# Модуль «Примеры UI Kit»

Вкладка основного iframe с живыми примерами компонентов `@moysklad/uikit` — тех, что чаще всего
нужны в интерфейсе решения. Показывает, что UI Kit — рекомендуемая основа интерфейса: пользователь
получает привычный вид и поведение элементов МоегоСклада.

## Что демонстрирует

- Переключатель ширины «Основной iframe / Виджет, 400px»: одни и те же компоненты в двух контекстах
  платформы. Главный iframe занимает всю рабочую область и растягивается по высоте
  (`<expand>true</expand>` в дескрипторе + `sdk.autoResizeIframe()`); виджет — колонка 400px
  фиксированной высоты из дескриптора, скролл внутри — на стороне решения.
- Секции с демо и фрагментом кода под копирование (кнопка «Показать код»), разложенные по табам:
  Основы (типографика, кнопки, иконки), Форма, Обратная связь, Подсказки и фильтры, Попапы,
  Таблица, Изображения, Карточка. Табы держат iframe коротким — это не только навигация:
  оверлеи (`Carousel`, `Modal`) позиционируются от всего iframe, а `autoFocus` в дропдауне
  мультиселекта доскролливает страницу МоегоСклада, поэтому на короткой странице они ведут
  себя корректно, а на длинной уезжают за экран.

## Карта файлов

| Файл                              | Что внутри                                                                              |
|-----------------------------------|-----------------------------------------------------------------------------------------|
| `ExamplesTab.tsx`          | Шапка вкладки, переключатель ширины, табы с группами секций                             |
| `Section.tsx`              | Карточка секции: заголовок, описание, демо, фрагмент кода                               |
| `sections/TypographySection.tsx` | `Text` (варианты и цветовые токены), `Link`                                       |
| `sections/ButtonsSection.tsx`    | `Button`: варианты, размеры, загрузка, иконка, `stretch`, ссылка через `as="a"` + `href`                          |
| `sections/FormSection.tsx`       | Форма настроек: `Input`, `Select`, `Multiselect`, `Quantity`, `Datepicker`, `SegmentButton`, `Radiobutton`, `Checkbox`, `Toggle`, `Textfield`, `SearchInput`, валидация, результат — `Banner` |
| `sections/FeedbackSection.tsx`   | `Banner`, `Informer`, `Badge`, `Counter`, `Chip`, `Spinner`, `Skeleton`, `EmptyState`         |
| `sections/HintsSection.tsx`      | `Help`, `Hint`, `Tooltip`, `Dropdown`, фильтры `FiltersContainer` из `data-grid`   |
| `sections/PopupSection.tsx`      | Диалоги: попап МоегоСклада (`sdk.showPopup()` / `sdk.closePopup()`) и `Modal` кита для легких подтверждений |
| `sections/TableSection.tsx`      | `data-grid` `Table` на `@tanstack/react-table`, `Pagination`                      |
| `sections/ImagesSection.tsx`     | `FileUploader` (загрузка с превью), `Carousel` (галерея)                           |
| `sections/IconsSection.tsx`      | Иконки `@moysklad/uikit/icon`                                                      |
| `sections/DataSection.tsx`       | `Panel` и варианты `LabelValue` (текст, select, input, файл, дата, ссылка), `StatusBadge`, `Link`, `Tabs`, `Breadcrumbs`, `Listing` |

## Как взять секцию к себе

1. Скопируйте файл секции из `sections/` — каждая самодостаточна: свои данные, свой state,
   импорты только из React и кита. Обертку `Section` замените своей разметкой или скопируйте тоже.
2. Импорты оставьте точечными: `@moysklad/uikit/components/<X>`, `@moysklad/uikit/icon`,
   `@moysklad/uikit/data-grid`. Импорт из корня пакета тянет всю библиотеку.
3. Для `TableSection` добавьте в `frontend/package.json` `@tanstack/react-table` той же версии, что у кита
   (в этом решении — `8.21.3`): `Table` принимает таблицу, подготовленную `useReactTable`.

## Платформенные особенности

- Оверлеи кита рисуются внутри iframe: `position: fixed` считается от всего iframe, а не от экрана,
  затемнение и центрирование ограничены его рамкой, шапка МоегоСклада остается активной. Поэтому
  полноценные диалоги (формы, выбор, мастера) — через протокол попапов: `sdk.showPopup(name, params)`
  открывает страницу решения поверх всего интерфейса МоегоСклада и работает и из виджета,
  `sdk.closePopup(response)` возвращает результат (`PopupSection`, страница попапа —
  `src/php/entry/popup.php`). `Modal` кита допустим для легких подтверждений на странице не выше
  экрана; `Sidepage` и `Snackbar` внутри iframe не используйте, в виджете 400px не используйте и `Modal`.
  `Dropdown`, `Datepicker`, `Tooltip`, `Help`, `Hint` привязаны к триггеру и работают без оговорок.
- В главном iframe высота подстраивается под контент, поэтому раскрытый код или длинная таблица
  просто удлиняют страницу; в виджете высота фиксирована — контент скроллится внутри.
- Таблица в узкой колонке требует горизонтального скролла контейнера (`overflow-x: auto`).
- `Table` из `data-grid` по умолчанию `fullHeight` — растягивается до низа окна (`100vh − отступ`). В главном
  iframe окно само растет под контент (`autoResizeIframe`), и высота уходит в бесконечность. Передавайте
  `fullHeight={false}`; то же касается любых компонентов с `100vh`.

## Где модуль подключается к общему коду

Все такие места помечены: `grep -rn "feature:uikit-examples" frontend/src/`.

- `frontend/src/iframe/IframePage.tsx` — `Tabs.Item` и рендер `ExamplesTab`.

## Как убрать модуль

Удалите каталог `frontend/src/uikit-examples/`, две помеченные строки в `IframePage.tsx` и зависимость
`@tanstack/react-table` из `frontend/package.json` (она нужна только `TableSection`).
Сам UI Kit при этом остается: на нем построен основной iframe решения, см. раздел «UI Kit» в корневом `README.md`.
