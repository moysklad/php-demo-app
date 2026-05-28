<?php
if (!defined('WIDGET_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!isset($context) || !is_array($context)) {
    throw new LogicException('widget.inc.php requires a user context array');
}

if (!isset($entity) || !is_string($entity) || $entity === '') {
    throw new LogicException('widget.inc.php requires a non-empty entity name');
}

/** @var array{uid: string, fio: string, contextNonce: string} $context */
$uid = (string)$context['uid'];
$fio = (string)$context['fio'];
$contextNonce = (string)$context['contextNonce'];

// contextNonce передается в теле POST-запроса, а не в URL.
$getObjectUrl = '/utils/get-object.php?' . http_build_query([
        'entity' => $entity,
    ]);

require __DIR__ . '/widget.html.php';
