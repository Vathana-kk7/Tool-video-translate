<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->longText('transcribed_text')->nullable()->change();
            $table->longText('translated_text')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->text('transcribed_text')->nullable()->change();
            $table->text('translated_text')->nullable()->change();
        });
    }
};
