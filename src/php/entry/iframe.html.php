<?php
// Оболочка основного iframe: персональные данные и форма настроек появляются после
// обмена одноразового токена в entry/user-context.php.
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Demo App iframe</title>
    <style>
        :root {
            --page-bg: #f7f7f7;
            --panel-bg: #ffffff;
            --muted: #5f6d79;
            --text: #091739;
            --border: #bfbfbf;
            --surface: #ffffff;
            --accent: #036ce5;
            --accent-hover: #0b7cff;
            --accent-active: #2f8fff;
            --radius-lg: 10px;
            --radius-md: 8px;
            --radius-sm: 7px;
            --space-xxs: 6px;
            --space-xs: 8px;
            --space-sm: 10px;
            --space-md: 12px;
            --space-lg: 14px;
            --space-xl: 16px;
            --space-xxl: 24px;
            --font-family: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            --font-size: 14px;
            --line-height: 1.45;
            --font-size-sm: 12px;
            --font-size-lg: 13px;
            --letter-spacing-wide: 0.08em;
            --letter-spacing-tight: 0.02em;
            --log-height: 250px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--page-bg);
            color: var(--text);
            font: var(--font-size)/var(--line-height) var(--font-family);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            display: flex;
            flex-direction: column;
            gap: var(--space-xxl);
            padding: var(--space-md) 20px 20px;
            flex: 1;
            min-height: 0;
            box-sizing: border-box;
        }

        .panel {
            background: var(--panel-bg);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
        }

        .panel.settings {
            flex: 0 0 300px;
            overflow: auto;
        }

        .panel.output {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            border: none;
            background: transparent;
            padding: 0;
        }

        .panel h2 {
            margin: 0 0 10px;
            font-size: var(--font-size-lg);
            text-transform: uppercase;
            letter-spacing: var(--letter-spacing-wide);
            color: var(--text);
        }

        .panel.output h2 {
            margin: 0;
        }

        .row {
            display: grid;
            gap: var(--space-xs);
            margin-bottom: var(--space-sm);
        }

        label {
            font-size: var(--font-size-sm);
            color: var(--muted);
            line-height: 1.3;
        }

        input, textarea, select {
            width: 100%;
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
        }

        input:hover,
        textarea:hover,
        select:hover {
            border-color: #000;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
        }

        input:focus-visible,
        textarea:focus-visible,
        select:focus-visible {
            border-color: #000;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-md);
            border: 1px solid var(--accent);
            background: var(--panel-bg);
            color: var(--accent);
            cursor: pointer;
            transition: transform 0.05s ease, border-color 0.2s ease;
        }

        .btn:hover {
            border-color: var(--accent-hover);
            color: var(--accent-hover);
        }

        .btn:active {
            transform: translateY(1px);
            border-color: var(--accent-active);
            color: var(--accent-active);
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-xxs);
        }

        .panel-divider {
            height: 1px;
            background: #d1d6df;
            margin: var(--space-md) 0;
        }

        .iframe-layout {
            display: grid;
            grid-template-columns: minmax(320px, 1fr) minmax(320px, 1fr);
            gap: var(--space-xl);
            padding: var(--space-md) 20px 20px;
        }

        .app-version {
            margin: 0 0 10px;
            font-size: var(--font-size-sm);
            color: var(--muted);
        }

        .info-list {
            margin: 0;
            padding-left: 18px;
        }

        .status-box {
            border-radius: var(--radius-md);
            padding: var(--space-xl) var(--space-md) var(--space-md);
            border: 1px dashed var(--border);
            background: #f6f7fb;
        }

        .status-title {
            font-size: var(--font-size-sm);
            letter-spacing: var(--letter-spacing-wide);
            text-transform: uppercase;
        }

        .status-box p {
            margin: var(--space-xs) 0 0;
        }

        .status-required {
            border-color: #d66;
            background: #ffe8e8;
        }

        .status-ready {
            border-color: #4cae74;
            background: #e7f6ee;
        }

        .form-result {
            min-height: 20px;
            margin-top: var(--space-sm);
            font-size: var(--font-size-sm);
            color: var(--muted);
        }

        .form-result.is-success {
            color: #246b44;
        }

        .form-result.is-error,
        #bootstrapStatus.is-error {
            color: #9a2f2f;
        }

        .muted {
            color: var(--muted);
        }

        @media (max-width: 980px) {
            .iframe-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 420px) {
            .panel.settings {
                flex-basis: 260px;
            }

            :root {
                --log-height: 200px;
            }

            main {
                padding: 10px 12px 16px;
                gap: var(--space-xxl);
            }

            .panel {
                padding: var(--space-md);
                border-radius: var(--radius-md);
            }

            .btn {
                width: 100%;
            }

            input, textarea, select {
                padding: 7px 9px;
                border-radius: var(--radius-sm);
            }

            textarea {
                min-height: 76px;
            }
        }
    </style>
    <script type="text/javascript"
            src="https://cdn.jsdelivr.net/npm/@moysklad/js-widget-sdk@1.3.0/dist/widget.min.js"></script>
</head>
<body>
<main class="iframe-layout">
    <section id="bootstrapPanel" class="panel">
        <h2>Контекст пользователя</h2>
        <p id="bootstrapStatus" class="muted" role="status" aria-live="polite">Получаем контекст пользователя…</p>
    </section>
    <section id="userPanel" class="panel" hidden>
        <h2>Информация о пользователе</h2>
        <ul class="info-list">
            <li>Текущий пользователь: <span id="userUid"></span></li>
            <li>Идентификатор аккаунта: <span id="userAccountId"></span></li>
            <li>Уровень доступа: <b id="userAccessLevel"></b></li>
        </ul>
        <div class="panel-divider"></div>
        <h2>Состояние решения</h2>
        <p class="app-version">Версия <span id="appVersion"></span></p>
        <div id="appStatus" class="status-box">
            <div id="appStatusTitle" class="status-title"></div>
            <p id="noAccessToken" hidden>
                В локальном хранилище нет `access_token` для этого приложения.
                После пересборки контейнера переустановите приложение, чтобы заново получить install callback.
            </p>
            <p id="appStatusDetails" hidden></p>
        </div>
    </section>
    <section id="settingsPanel" class="panel" hidden>
        <h2>Форма настроек</h2>
        <form id="settingsForm" method="post" action="../utils/update-settings.php" data-update-url="../utils/update-settings.php" hidden>
            <div class="row field-row">
                <label for="infoMessage">Укажите сообщение</label>
                <input id="infoMessage" type="text" name="infoMessage" value="">
            </div>
            <div class="row field-row">
                <label for="store">Выберите склад</label>
                <select id="store" name="store"></select>
            </div>
            <input type="hidden" name="contextNonce" value=""/>
            <button class="btn" type="submit">Сохранить</button>
            <div id="settingsResult" class="form-result" role="status" aria-live="polite"></div>
        </form>
        <p id="settingsRestricted" class="muted" hidden>Настройки доступны только администратору аккаунта</p>
    </section>
</main>
<script>
    (function () {
        const sdkNamespace = window.WidgetSDK;
        const sdk = sdkNamespace ? sdkNamespace.create({debug: true}) : null;

        if (sdk) {
            window.widgetSdk = sdk;
            sdk.autoResizeIframe();
        }

        const bootstrapPanel = document.getElementById('bootstrapPanel');
        const bootstrapStatus = document.getElementById('bootstrapStatus');
        const userPanel = document.getElementById('userPanel');
        const settingsPanel = document.getElementById('settingsPanel');
        const settingsRestricted = document.getElementById('settingsRestricted');
        const form = document.getElementById('settingsForm');
        const result = document.getElementById('settingsResult');
        const statusBox = document.getElementById('appStatus');
        const statusTitle = document.getElementById('appStatusTitle');
        const statusDetails = document.getElementById('appStatusDetails');

        if (!form || !result) {
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        const defaultButtonText = submitButton ? submitButton.textContent : '';

        const showBootstrapError = (message) => {
            bootstrapStatus.textContent = message;
            bootstrapStatus.classList.remove('muted');
            bootstrapStatus.classList.add('is-error');
        };

        const initializeUserContext = async () => {
            if (!sdk || typeof sdk.requestUserContextToken !== 'function') {
                showBootstrapError('JS Widget SDK не загружен, контекст пользователя недоступен.');
                return;
            }

            let token = null;

            try {
                token = await sdk.requestUserContextToken();
            } catch (error) {
                const details = error && error.message ? error.message : String(error);
                showBootstrapError(`Не удалось запросить контекст пользователя у хоста: ${details}`);
                return;
            }

            const request = new Request('user-context.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({token, page: 'iframe'}),
                credentials: 'same-origin',
            });
            token = null;

            let response;
            let payload = null;

            try {
                response = await fetch(request);
                payload = await response.json().catch(() => null);
            } catch (_error) {
                showBootstrapError('Не удалось отправить контекст на сервер приложения.');
                return;
            }

            if (!response.ok || !payload || !payload.pageData) {
                const code = payload && payload.code ? ` (код ${payload.code})` : '';
                showBootstrapError(`Не удалось получить контекст пользователя: HTTP ${response.status}${code}.`);
                return;
            }

            renderPage(payload.pageData);
        };

        const renderPage = (pageData) => {
            document.getElementById('userUid').textContent = pageData.fio ? `${pageData.uid} (${pageData.fio})` : pageData.uid;
            document.getElementById('userAccountId').textContent = pageData.accountId;
            document.getElementById('userAccessLevel').textContent = pageData.accessLevel;
            document.getElementById('appVersion').textContent = pageData.appVersion || '';
            document.getElementById('noAccessToken').hidden = pageData.hasAccessToken;
            updateStatus(pageData.status);

            if (pageData.isAdmin && pageData.hasAccessToken) {
                const storeSelect = document.getElementById('store');
                const currentStore = pageData.status ? pageData.status.store : '';
                const stores = Array.isArray(pageData.storesValues) ? pageData.storesValues.slice() : [];

                if (currentStore && !stores.includes(currentStore)) {
                    stores.unshift(currentStore);
                }

                storeSelect.innerHTML = '';
                stores.forEach((value) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    option.selected = value === currentStore;
                    storeSelect.append(option);
                });
                document.getElementById('infoMessage').value = pageData.status ? pageData.status.infoMessage : '';
                form.elements.namedItem('contextNonce').value = pageData.contextNonce;
                form.hidden = false;
            } else if (!pageData.isAdmin) {
                settingsRestricted.hidden = false;
            }

            bootstrapPanel.hidden = true;
            userPanel.hidden = false;
            settingsPanel.hidden = false;
        };

        const setResult = (message, kind) => {
            result.textContent = message;
            result.classList.remove('is-success', 'is-error');

            if (kind) {
                result.classList.add(kind);
            }
        };

        const updateStatus = (status) => {
            if (!status || !statusBox || !statusTitle || !statusDetails) {
                return;
            }

            statusBox.classList.remove('status-required', 'status-ready');

            if (status.className) {
                statusBox.classList.add(status.className);
            }

            statusTitle.textContent = status.title || '';

            if (status.showDetails) {
                statusDetails.hidden = false;
                statusDetails.innerHTML = '';
                statusDetails.append(
                    'Сообщение: ',
                    status.infoMessage || '',
                    document.createElement('br'),
                    'Выбран склад: ',
                    status.store || ''
                );
            } else {
                statusDetails.hidden = true;
                statusDetails.textContent = '';
            }
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            setResult('', '');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Сохранение...';
            }

            try {
                const response = await fetch(form.dataset.updateUrl || form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                const message = payload.message;

                if (response.ok) {
                    setResult(message || 'Настройки обновлены', 'is-success');
                    updateStatus(payload.status);
                } else {
                    setResult(message || 'Не удалось сохранить настройки', 'is-error');
                }
            } catch (_error) {
                setResult('Не удалось сохранить настройки', 'is-error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = defaultButtonText;
                }
            }
        });

        initializeUserContext();
    })();
</script>
</body>
</html>
