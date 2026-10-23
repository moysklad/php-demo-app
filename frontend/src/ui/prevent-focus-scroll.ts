/**
 * Фокус без прокрутки. Дропдауны кита (Multiselect, MultiselectFilter) ставят autoFocus на поле поиска,
 * а позиционируются уже после монтирования: браузер успевает проскроллить к полю, и в iframe с
 * autoResizeIframe уезжает страница МоегоСклада. У iframe решения своего скролла нет, так что
 * прокрутка по фокусу внутри него и не нужна — отключаем ее для всех элементов страницы.
 * Подключать до монтирования React.
 */
const nativeFocus = HTMLElement.prototype.focus;

HTMLElement.prototype.focus = function focusWithoutScroll(options?: FocusOptions) {
  nativeFocus.call(this, { ...options, preventScroll: true });
};
