<?php
/** @var string $uid */
/** @var string $fio */
/** @var string $getObjectUrl */
/** @var string $contextNonce */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Demo App widget</title>
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

        .hint {
            cursor: default;
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

        .btn:disabled {
            cursor: not-allowed;
            opacity: 0.6;
            border-color: var(--border);
            color: var(--muted);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-xs);
        }

        .btn-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-xs);
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

        #log {
            flex: 0 0 auto;
            height: var(--log-height);
            min-height: 0;
            overflow: auto;
            font-size: var(--font-size-sm);
            background: var(--panel-bg);
            border-radius: var(--radius-md);
            padding: var(--space-sm);
            white-space: pre-wrap;
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

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .btn-row {
                grid-template-columns: 1fr;
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

            #log {
                font-size: 11px;
            }
        }
    </style>
    <script type="text/javascript"
            src="https://cdn.jsdelivr.net/npm/@moysklad-official/js-widget-sdk@1/dist/widget.min.js"></script>
</head>
<body data-context-nonce="<?= escHtml($contextNonce) ?>">
<main>
    <section class="panel settings">
        <h2 title="Информацию о текущем пользователе виджет может получить на своем бэкенде через Vendor API">
            Текущий пользователь <span class="hint">(?)</span>
        </h2>
        <div><?= escHtml($uid) ?> (<?= escHtml($fio) ?>)</div>
        <div class="panel-divider"></div>
        <h2 title="Используя objectId, переданный в сообщении Open, можем получить через JSON API открытую пользователем сущность/документ">
            Открытый объект <span class="hint">(?)</span>
        </h2>
        <div id="object">—</div>
        <div class="panel-divider"></div>
        <h2>good-folder-selector</h2>
        <div class="row">
            <button class="btn" id="btnSelectFolder">Выбрать</button>
        </div>
        <div class="panel-divider"></div>
        <h2>navigation-service</h2>
        <div class="row field-row">
            <label for="navigatePath">Путь</label>
            <input id="navigatePath" value="#customerorder?sort=o.moment%20d">
        </div>
        <div class="row">
            <button class="btn" id="btnNavigate">Перейти</button>
        </div>
        <div class="panel-divider"></div>
        <h2>standard-dialogs</h2>
        <div class="row field-row">
            <label for="dialogText">Текст диалога</label>
            <input id="dialogText" value="Hello from SDK">
        </div>
        <div class="row field-row">
            <label for="dialogButtons">Кнопки диалога (JSON)</label>
            <textarea
                id="dialogButtons">[{ "name": "Yes", "caption": "Да, удалить" },{ "name": "No", "caption": "Нет" }]</textarea>
        </div>
        <div class="row">
            <button class="btn" id="btnDialog">Открыть</button>
        </div>
        <div class="panel-divider"></div>
        <h2>dirty-state</h2>
        <div class="row grid-2">
            <button class="btn" id="btnSetDirty">Установить</button>
            <button class="btn" id="btnClearDirty">Очистить</button>
        </div>
        <div class="panel-divider"></div>
        <h2>validation-feedback</h2>
        <div class="row field-row">
            <label for="validationPayload">Параметры валидации (JSON или text)</label>
            <textarea id="validationPayload">{ "name": "ValidationFeedback", "correlationId": 1, "messageId": 1, "valid": false, "message": "Нужно больше печенья" }</textarea>
        </div>
        <div class="row">
            <button class="btn" id="btnValidation">Подтвердить</button>
        </div>
        <div class="panel-divider"></div>
        <h2>update-provider</h2>
        <div class="row field-row">
            <label for="updatePayload">Параметры обновления (JSON or text)</label>
            <textarea id="updatePayload">{ "name": "1" }</textarea>
        </div>
        <div class="row">
            <button class="btn" id="btnUpdate">Обновить</button>
        </div>
        <div class="panel-divider"></div>
        <h2>Popups</h2>
        <div class="row field-row">
            <label for="popupName">Название попапа</label>
            <input id="popupName" value="some-popup">
        </div>
        <div class="row field-row">
            <label for="popupParams">Параметры попапа (JSON)</label>
            <textarea id="popupParams">{ "foo": "bar" }</textarea>
        </div>
        <div class="row btn-row">
            <button class="btn" id="btnShowPopup">Открыть</button>
            <button class="btn" id="btnClosePopup">Закрыть</button>
        </div>
    </section>
    <section class="panel output">
        <h2>Логи</h2>
        <div id="log"></div>
    </section>
</main>
<script>
    const logEl = document.getElementById('log');

    // Пишем лог в UI, а если панели нет - в console.
    const widgetLog = (label, payload) => {
        const ts = new Date().toISOString().replace('T', ' ').replace('Z', '');
        const data = payload ? JSON.stringify(payload, null, 2) : '';
        const message = `[${ts}] ${label}\n${data}\n\n`;

        if (logEl) {
            logEl.textContent = message + logEl.textContent;
        } else {
            console.log(message);
        }
    };

    window.widgetLog = widgetLog;

    const getObjectUrl = <?= json_encode($getObjectUrl ?? '') ?>;
    const objectEl = document.getElementById('object');

    const AUTO_OPEN_FEEDBACK_DELAY_MS = 1000;

    const sdkNamespace = window.WidgetSDK;
    // Защита на случай, если SDK-скрипт не загрузился.
    const sdk = sdkNamespace ? sdkNamespace.create({debug: true}) : null;

    if (sdk) {
        // Экспортируем SDK в window для удобной отладки.
        window.widgetSdk = sdk;
    }

    // Пробуем разобрать JSON, иначе возвращаем trimmed-строку.
    const parseMaybeJson = (value) => {
        if (value === undefined || value === null) {
            return undefined;
        }

        const trimmed = String(value).trim();

        if (!trimmed) {
            return undefined;
        }

        try {
            return JSON.parse(trimmed);
        } catch (_) {
            return trimmed;
        }
    };

    const sdkControlIds = [
        'btnSelectFolder',
        'btnNavigate',
        'btnDialog',
        'btnSetDirty',
        'btnClearDirty',
        'btnValidation',
        'btnUpdate',
        'btnShowPopup',
        'btnClosePopup',
    ];

    const setSdkControlsEnabled = (enabled) => {
        sdkControlIds.forEach(id => {
            const el = document.getElementById(id);

            if (!el) {
                return;
            }

            el.disabled = !enabled;

            if (enabled) {
                el.removeAttribute('title');
                el.removeAttribute('aria-disabled');
            } else {
                el.setAttribute('title', 'SDK недоступен');
                el.setAttribute('aria-disabled', 'true');
            }
        });
    };

    let objectState = {};

    // Сравнение значений для diff; для объектов fallback на JSON-сравнение.
    const valuesEqual = (left, right) => {
        if (left === right) {
            return true;
        }

        if (left && right && typeof left === 'object' && typeof right === 'object') {
            try {
                return JSON.stringify(left) === JSON.stringify(right);
            } catch (_) {
                return false;
            }
        }

        return false;
    };

    // Вычисляем измененные ключи между двумя состояниями objectState.
    const diffs = (oldState, newState) => {
        const result = new Map();

        if (!newState || typeof newState !== 'object') {
            return result;
        }

        for (const key in newState) {
            if (Object.prototype.hasOwnProperty.call(newState, key)) {
                const oldValue = oldState && Object.prototype.hasOwnProperty.call(oldState, key) ? oldState[key] : undefined;

                if (!oldState || !Object.prototype.hasOwnProperty.call(oldState, key)
                    || !valuesEqual(newState[key], oldValue)) {
                    result.set(key, newState[key]);
                }
            }
        }

        if (oldState && typeof oldState === 'object') {
            for (const key in oldState) {
                if (Object.prototype.hasOwnProperty.call(oldState, key)
                    && (!newState || !Object.prototype.hasOwnProperty.call(newState, key))) {
                    result.set(key, '<deleted>');
                }
            }
        }

        return result;
    };

    // Форматируем diff-map для лога.
    const formatDiffs = (map) => {
        if (!map || map.size === 0) {
            return 'objectState: no changes';
        }

        const lines = [];

        map.forEach((value, key) => {
            if (value && typeof value === 'object') {
                lines.push(`${key} = {...}`);
            } else {
                lines.push(`${key} = ${value}`);
            }
        });

        return `objectState changes:\n${lines.join('\n')}`;
    };

    if (!sdk) {
        widgetLog('SDK init skipped', {reason: 'WidgetSDK is not available'});
        setSdkControlsEnabled(false);
    } else {
        widgetLog('SDK initialized', {debug: true});
        setSdkControlsEnabled(true);

        // Автоматически отправляем openFeedback после события Open.
        const maybeAutoOpenFeedback = (openMessage) => {
            const resolvedId = openMessage?.messageId;

            setTimeout(() => {
                const res = sdk.openFeedback(resolvedId);

                widgetLog('auto openFeedback sent', res);
            }, AUTO_OPEN_FEEDBACK_DELAY_MS);
        };

        sdk.onOpen(message => {
            widgetLog('Event: Open', message);
            maybeAutoOpenFeedback(message);

            if (objectEl && getObjectUrl && message && message.objectId) {
                // Передаем cookie той же origin, чтобы backend мог прочитать PHP-сессию.
                fetch(getObjectUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        contextNonce: document.body.dataset.contextNonce || '',
                        objectId: message.objectId
                    })
                })
                    .then(async response => {
                        const text = await response.text();

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${text}`);
                        }

                        return text;
                    })
                    .then(text => {
                        objectEl.textContent = text;
                    })
                    .catch(error => {
                        widgetLog('object fetch error', {message: error.message || String(error)});
                    });
            } else if (!message || !message.objectId) {
                widgetLog('object fetch skipped', {reason: 'missing objectId'});
            }
        });
        sdk.onOpenPopup(message => widgetLog('Event: OpenPopup', message));
        sdk.onChange(message => {
            widgetLog('Event: Change', message);

            if (!message || !message.objectState) {
                widgetLog('Change ignored', {reason: 'missing objectState'});
                return;
            }

            const diffMap = diffs(objectState, message.objectState);

            widgetLog('Event: Change (diff)', formatDiffs(diffMap));

            objectState = message.objectState;
        });
        sdk.onSave(message => widgetLog('Event: Save', message));

        document.getElementById('btnSelectFolder').addEventListener('click', async () => {
            try {
                const res = await sdk.selectGoodFolder();

                widgetLog('selectGoodFolder response', res);
            } catch (e) {
                widgetLog('selectGoodFolder error', {message: e.message, name: e.name});
            }
        });

        document.getElementById('btnNavigate').addEventListener('click', async () => {
            const path = document.getElementById('navigatePath').value.trim() || '/';

            try {
                const res = await sdk.navigateTo(path, 'blank');

                widgetLog('navigateTo response', res);
            } catch (e) {
                widgetLog('navigateTo error', {message: e.message, name: e.name});
            }
        });

        document.getElementById('btnDialog').addEventListener('click', async () => {
            const text = document.getElementById('dialogText').value.trim() || 'Dialog';
            const buttonsPayload = parseMaybeJson(document.getElementById('dialogButtons').value);

            try {
                const normalizedButtons = Array.isArray(buttonsPayload)
                    ? buttonsPayload
                    : (buttonsPayload && Array.isArray(buttonsPayload.buttons) ? buttonsPayload.buttons : undefined);
                const res = await sdk.showDialog(text, normalizedButtons);

                widgetLog('showDialog response', res);
            } catch (e) {
                widgetLog('showDialog error', {message: e.message, name: e.name});
            }
        });

        document.getElementById('btnSetDirty').addEventListener('click', () => {
            const res = sdk.setDirty();

            widgetLog('setDirty sent', res);
        });

        document.getElementById('btnClearDirty').addEventListener('click', () => {
            const res = sdk.clearDirty();

            widgetLog('clearDirty sent', res);
        });

        document.getElementById('btnValidation').addEventListener('click', () => {
            const payload = parseMaybeJson(document.getElementById('validationPayload').value);

            let valid = false;
            let message = undefined;
            let changeMessageId = undefined;

            if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
                if (payload.valid !== undefined) {
                    valid = payload.valid;
                }

                if (payload.message !== undefined) {
                    message = payload.message;
                }

                if (payload.changeMessageId !== undefined) {
                    changeMessageId = payload.changeMessageId;
                }

                if (payload.correlationId !== undefined) {
                    changeMessageId = payload.correlationId;
                }
            } else if (payload !== undefined) {
                message = String(payload);
            }

            const res = sdk.validationFeedback(valid, message, changeMessageId);

            widgetLog('validationFeedback sent', res);
        });

        document.getElementById('btnUpdate').addEventListener('click', async () => {
            const payload = parseMaybeJson(document.getElementById('updatePayload').value);

            try {
                const res = await sdk.update(payload);

                widgetLog('update response', res);
            } catch (e) {
                widgetLog('update error', {message: e.message, name: e.name});
            }
        });

        document.getElementById('btnShowPopup').addEventListener('click', async () => {
            const name = document.getElementById('popupName').value.trim() || 'popup';
            const params = parseMaybeJson(document.getElementById('popupParams').value);

            try {
                const res = await sdk.showPopup(name, params);

                widgetLog('showPopup response', res);
            } catch (e) {
                widgetLog('showPopup error', {message: e.message, name: e.name});
            }
        });

        document.getElementById('btnClosePopup').addEventListener('click', () => {
            const res = sdk.closePopup({ok: true});

            widgetLog('closePopup sent', res);
        });
    }
</script>
</body>
</html>
