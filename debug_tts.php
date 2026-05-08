<?php
require D:\laravel_php\tool_ai\bootstrap\autoload.php;
$app = require D:\laravel_php\tool_ai\bootstrap\app.php;

$apiKey = config('services.tts.key');
$url = config('services.tts.url');

var_dump($apiKey);
var_dump($url);

echo "Key length: " . strlen($apiKey) . "\n";
echo "URL length: " . strlen($url) . "\n";

if (!$apiKey) {
    echo "API Key is empty\n";
}
if (!$url) {
    echo "URL is empty\n";
}
?>