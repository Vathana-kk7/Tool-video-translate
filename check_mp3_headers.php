<?php
$files = glob(__DIR__.'/storage/app/public/audio/*_khmer.mp3');
foreach ($files as $file) {
    $size = filesize($file);
    $handle = fopen($file, 'rb');
    $header = fread($handle, 10);
    fclose($handle);
    $hex = bin2hex($header);
    echo basename($file) . " | Size: $size | Header: $hex\n";
}
