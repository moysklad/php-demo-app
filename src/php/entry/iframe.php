<?php

require_once __DIR__ . '/../lib/lib.php';

// Основной iframe — React-приложение на @moysklad/uikit (исходники в frontend/, сборка в assets/entry).
// Сервер отдает только оболочку без персональных данных: контекст пользователя браузер получает
// через requestUserContextToken() и entry/user-context.php, данные страницы приходят в том же ответе.

$bundleVersion = static fn(string $file): string => (string)(@filemtime(__DIR__ . "/../assets/entry/$file") ?: appVersion());

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Demo App iframe</title>
    <link rel="stylesheet" href="../assets/entry/iframe.css?v=<?= escHtml($bundleVersion('iframe.css')) ?>">
</head>
<body>
<div id="root"></div>
<script type="module" src="../assets/entry/iframe.js?v=<?= escHtml($bundleVersion('iframe.js')) ?>"></script>
</body>
</html>
