<?php
if (!defined('WIDGET_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!isset($entity) || !is_string($entity) || $entity === '') {
    throw new LogicException('widget.inc.php requires a non-empty entity name');
}

// contextNonce передается в теле POST-запроса, а не в URL.
$getObjectUrl = '/utils/get-object.php?' . http_build_query([
        'entity' => $entity,
    ]);

require __DIR__ . '/widget.html.php';
