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
            src="https://apps-api.moysklad.ru/js/ns/appstore/app/v1/moysklad-iframe-expand-3.js"></script>
</head>
<body>
<main class="iframe-layout">
    <section class="panel">
        <h2>Информация о пользователе</h2>
        <ul class="info-list">
            <li>Текущий пользователь: <?= escHtml($uid) ?> (<?= escHtml($fio) ?>)</li>
            <li>Идентификатор аккаунта: <?= escHtml($accountId) ?></li>
            <li>Уровень доступа: <b><?= $isAdmin ? 'администратор аккаунта' : 'простой пользователь' ?></b>
            </li>
        </ul>
        <div class="panel-divider"></div>
        <h2>Состояние решения</h2>
        <div class="status-box <?= $isSettingsRequired ? 'status-required' : 'status-ready' ?>">
            <div class="status-title">
                <?= $isSettingsRequired ? 'ТРЕБУЕТСЯ НАСТРОЙКА' : 'РЕШЕНИЕ ГОТОВО К РАБОТЕ' ?>
            </div>
            <?php if (empty($app->accessToken)) { ?>
                <p>
                    В локальном хранилище нет `access_token` для этого приложения.
                    После пересборки контейнера переустановите приложение, чтобы заново получить install callback.
                </p>
            <?php } ?>
            <?php if (!$isSettingsRequired) { ?>
                <p>
                    Сообщение: <?= escHtml($infoMessage) ?><br>
                    Выбран склад: <?= escHtml($store) ?>
                </p>
            <?php } ?>
        </div>
    </section>
    <section class="panel">
        <h2>Форма настроек</h2>
        <?php if ($isAdmin && !empty($app->accessToken)) { ?>
            <form method="post" action="../utils/update-settings.php">
                <div class="row field-row">
                    <label for="infoMessage">Укажите сообщение</label>
                    <input id="infoMessage" type="text" name="infoMessage" value="<?= escHtml($infoMessage ?? '') ?>">
                </div>
                <div class="row field-row">
                    <label for="store">Выберите склад</label>
                    <select id="store" name="store">
                        <?php if (!empty($store) && !in_array($store, $storesValues, true)) { ?>
                            <option value="<?= escHtml($store) ?>" selected><?= escHtml($store) ?></option>
                        <?php } ?>
                        <?php foreach ($storesValues as $v) { ?>
                            <option value="<?= escHtml($v) ?>" <?= $v === $store ? 'selected' : '' ?>><?= escHtml($v) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <input type="hidden" name="contextNonce" value="<?= escHtml($contextNonce) ?>"/>
                <button class="btn" type="submit">Сохранить</button>
            </form>
        <?php } elseif (!$isAdmin) { ?>
            <p class="muted">Настройки доступны только администратору аккаунта</p>
        <?php } ?>
    </section>
</main>
</body>
</html>
