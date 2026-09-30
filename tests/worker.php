<?php

require __DIR__ . '/../src/php/lib/lib.php';

if ($argv[1] === 'reserve') {
    $gate = new LognexRateLimitGate('parallel-api', 'parallel-token');
    echo json_encode(['slot' => currentEpochMs() + $gate->reserveDelay()]);
} elseif ($argv[1] === 'session') {
    session_id($argv[2]);
    $startedAt = microtime(true);
    ensureSessionStarted();
    session_write_close();
    echo json_encode(['elapsed' => microtime(true) - $startedAt]);
} else {
    $curl = curl_init($argv[2]);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['contextNonce' => $argv[4]]),
        CURLOPT_COOKIE => 'PHPSESSID=' . $argv[3],
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($curl);
    echo json_encode(['status' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'body' => json_decode($body, true)]);
    curl_close($curl);
}
