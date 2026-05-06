<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('original_video');
            $table->string('extracted_audio')->nullable();
            $table->text('transcribed_text')->nullable();
            $table->text('translated_text')->nullable();
            $table->string('khmer_audio')->nullable();
            $table->string('final_video')->nullable();
            $table->enum('status', ['pending', 'processing', 'extracting_audio', 'transcribing', 'translating', 'generating_tts', 'merging', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('progress')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};