<?php

require_once __DIR__ . '/../lib/lib.php';

// Мобильное окно (<iframe type="mobile">) — страница ручных проверок WebView мобильного приложения МоегоСклада.
// Мобильный хост не поддерживает протокол user-context, поэтому сессия поднимается по contextKey из URL.
// React и UI Kit здесь не нужны: HTML отдает сервер, логика и стили — assets/mobile/iframe-mobile.* без сборки.

$contextKey = trim((string)($_GET['contextKey'] ?? ''));
$employee = $contextKey === '' ? null : vendorApi()->context($contextKey);
$uid = trim((string)($employee->uid ?? ''));
$accountId = trim((string)($employee->accountId ?? ''));
$context = null;

if ($uid !== '' && $accountId !== '') {
    $context = saveActiveUserContextToSession([
        'uid' => $uid,
        'fio' => (string)($employee->shortFio ?? ''),
        'accountId' => $accountId,
        'isAdmin' => $employee->permissions->admin->view ?? false,
    ]);
} else {
    http_response_code(401);
}

$assetVersion = static fn(string $file): string => (string)(@filemtime(__DIR__ . "/../assets/mobile/$file") ?: appVersion());

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>PHP Demo App mobile WebView</title>
    <link rel="stylesheet" href="../assets/mobile/iframe-mobile.css?v=<?= escHtml($assetVersion('iframe-mobile.css')) ?>">
<?php if ($context !== null): ?>
    <script defer src="../assets/mobile/iframe-mobile.js?v=<?= escHtml($assetVersion('iframe-mobile.js')) ?>"></script>
<?php endif; ?>
</head>
<body>
<?php if ($context === null): ?>
<main class="mobile-page">
    <section class="hero-card">
        <p class="eyebrow">Mobile iframe</p>
        <h1>Не удалось открыть решение</h1>
        <p class="muted">
<?php if ($contextKey !== ''): ?>
            МойСклад не вернул пользователя по contextKey из адреса страницы: ссылка устарела.
            Закройте решение и откройте его заново из мобильного приложения — оно запросит новую ссылку.
<?php else: ?>
            В адресе страницы нет параметра contextKey. Страницу открывает мобильное приложение МоегоСклада
            по ссылке из Mobile iframe API, без contextKey пользователь неизвестен.
<?php endif; ?>
        </p>
    </section>
</main>
<?php else: ?>
<main class="mobile-page" data-context-nonce="<?= escHtml($context['contextNonce']) ?>">
    <section class="hero-card">
        <p class="eyebrow">Mobile iframe</p>
        <h1>Проверка WebView</h1>
        <p class="muted">
            Страница открыта с серверным контекстом пользователя и набором проверок возможностей мобильного браузера.
            Метка в углу карточки показывает итог последней проверки, внизу страницы — отчёт для пересылки.
        </p>
        <dl class="context-grid" aria-label="Контекст пользователя">
            <div>
                <dt>Пользователь</dt>
                <dd data-report="Пользователь"><?= escHtml($context['fio'] === '' ? $context['uid'] : "{$context['uid']} ({$context['fio']})") ?></dd>
            </div>
            <div>
                <dt>Аккаунт</dt>
                <dd data-report="Аккаунт"><?= escHtml($context['accountId']) ?></dd>
            </div>
            <div>
                <dt>Доступ</dt>
                <dd data-report="Доступ"><?= $context['isAdmin'] ? 'администратор аккаунта' : 'простой пользователь' ?></dd>
            </div>
        </dl>
        <input type="hidden" name="contextNonce" value="<?= escHtml($context['contextNonce']) ?>">
    </section>

    <section class="capability-card" data-check="text" data-title="Ввод текста и буфер обмена">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Ввод текста</p>
                <h2>Клавиатура и буфер обмена</h2>
            </div>
            <span class="badge">Не проверено</span>
        </div>
        <label for="textInput">Однострочное поле</label>
        <input id="textInput" type="text" autocomplete="off" inputmode="text" placeholder="Текст с клавиатуры">
        <label for="textArea">Многострочное поле / вставка</label>
        <textarea id="textArea" placeholder="Вставьте текст вручную или через кнопку чтения буфера"></textarea>
        <div class="actions">
            <button class="btn" type="button" data-action="copy-text">Скопировать текст</button>
            <button class="btn secondary" type="button" data-action="read-clipboard">Прочитать буфер</button>
        </div>
        <p class="status" role="status" aria-live="polite"></p>
    </section>

    <section class="capability-card" data-check="media" data-title="Изображения, видео и файлы">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Медиа</p>
                <h2>Изображения, видео и файлы</h2>
            </div>
            <span class="badge">Не проверено</span>
        </div>
        <label for="imagePicker">Выберите изображение</label>
        <input id="imagePicker" type="file" accept="image/*">
        <img id="imagePreview" class="preview-image" alt="Предпросмотр выбранного изображения" hidden>

        <label for="videoPicker">Выберите видео</label>
        <input id="videoPicker" type="file" accept="video/*">
        <video id="videoPreview" class="preview-video" controls playsinline hidden></video>

        <label for="filePicker">Выберите файлы для проверки загрузки из WebView</label>
        <input id="filePicker" type="file" multiple>
        <ul id="fileList" class="file-list" aria-live="polite"></ul>

        <button class="btn" type="button" data-action="download-file">Скачать отчёт файлом</button>
        <p class="status" role="status" aria-live="polite"></p>
    </section>

    <section class="capability-card" data-check="camera" data-title="Камера">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Камера</p>
                <h2>Видео с камеры</h2>
            </div>
            <span class="badge">Не проверено</span>
        </div>
        <video id="cameraPreview" class="preview-video camera-preview" autoplay playsinline muted hidden></video>
        <div class="actions">
            <button class="btn" type="button" data-action="start-camera">Включить камеру</button>
            <button class="btn secondary" type="button" data-action="stop-camera">Остановить</button>
        </div>
        <p class="status" role="status" aria-live="polite"></p>
    </section>

    <section class="capability-card" data-check="microphone" data-title="Микрофон">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Микрофон</p>
                <h2>Разрешение и уровень сигнала</h2>
            </div>
            <span class="badge">Не проверено</span>
        </div>
        <div class="meter" aria-label="Уровень микрофона">
            <div id="microphoneLevel"></div>
        </div>
        <div class="actions">
            <button class="btn" type="button" data-action="start-microphone">Включить микрофон</button>
            <button class="btn secondary" type="button" data-action="stop-microphone">Остановить</button>
        </div>
        <p class="status" role="status" aria-live="polite"></p>
    </section>

    <section class="capability-card" data-check="geolocation" data-title="Геолокация">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Геолокация</p>
                <h2>Координаты устройства</h2>
            </div>
            <span class="badge">Не проверено</span>
        </div>
        <button class="btn" type="button" data-action="get-location">Получить координаты</button>
        <dl id="locationOutput" class="env-grid" aria-live="polite" hidden></dl>
        <p class="status" role="status" aria-live="polite"></p>
    </section>

    <section class="capability-card" id="report">
        <div class="card-heading">
            <div>
                <p class="eyebrow">Окружение</p>
                <h2>WebView, экран и отчёт</h2>
            </div>
        </div>
        <p class="muted">
            Видимая область уменьшается при открытой клавиатуре: сравните значение до и после фокуса в текстовом поле.
        </p>
        <dl id="environment" class="env-grid"></dl>
        <button class="btn" type="button" data-action="copy-report">Скопировать отчёт</button>
        <textarea id="reportOutput" class="report-output" readonly hidden aria-label="Текст отчёта"></textarea>
        <p class="status" role="status" aria-live="polite"></p>
    </section>
    <div class="safe-area-probe" aria-hidden="true"></div>
</main>
<?php endif; ?>
</body>
</html>
