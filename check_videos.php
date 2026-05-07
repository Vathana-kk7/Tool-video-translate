<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$videos = App\Models\Video::all(['id','status','khmer_audio','final_video']);
foreach ($videos as $v) {
    echo "ID: {$v->id} | Status: {$v->status} | khmer_audio: {$v->khmer_audio} | final_video: {$v->final_video}\n";
}
