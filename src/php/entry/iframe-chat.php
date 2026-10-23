<?php

require_once __DIR__ . '/../lib/lib.php';

// Окно чатов — отдельный React-бандл на том же протоколе user-context, что и основной iframe.
// Хост чатов высоту окна не подстраивает, скролл у страницы свой. Сервер отдает только оболочку
// без персональных данных: контекст и данные страницы приходят из entry/user-context.php.

$bundleVersion = static fn(string $file): string => (string)(@filemtime(__DIR__ . "/../assets/entry/$file") ?: appVersion());

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Demo App chat</title>
    <link rel="stylesheet" href="../assets/entry/chat.css?v=<?= escHtml($bundleVersion('chat.css')) ?>">
</head>
<body>
<div id="root"></div>
<script type="module" src="../assets/entry/chat.js?v=<?= escHtml($bundleVersion('chat.js')) ?>"></script>
</body>
</html>
