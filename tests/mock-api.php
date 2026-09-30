<?php

$path = getenv('TEST_QUEUE');
$file = fopen($path, 'c+');
flock($file, LOCK_EX);
$state = json_decode(stream_get_contents($file), true);
$index = count($state['calls']);
$response = $state['responses'][min($index, count($state['responses']) - 1)];
$state['calls'][] = ['at' => microtime(true), 'method' => $_SERVER['REQUEST_METHOD'], 'url' => $_SERVER['REQUEST_URI']];
rewind($file);
ftruncate($file, 0);
fwrite($file, json_encode($state));
flock($file, LOCK_UN);
fclose($file);
http_response_code($response['status']);
header('Content-Type: application/json');
foreach ($response['headers'] ?? [] as $name => $value) {
    header($name . ': ' . $value);
}
echo $response['body'] ?? '{"rows":[]}';
