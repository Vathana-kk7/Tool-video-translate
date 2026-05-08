<?php
require D:\laravel_php\tool_ai\bootstrap\autoload.php;
$app = require D:\laravel_php\tool_ai\bootstrap\app.php;

$key = config('services.tts.key');
$url = config('services.tts.url');

echo "Key: ".$key."\n";
echo "URL: ".$url."\n";

if (!$key || !$url) {
    echo "ERROR: Missing TTS config\n";
    exit(1);
} else {
    echo "SUCCESS: TTS config found\n";
}
?>