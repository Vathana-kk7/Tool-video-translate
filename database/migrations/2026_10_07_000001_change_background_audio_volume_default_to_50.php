<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->setDefault(50);
    }

    public function down(): void
    {
        $this->setDefault(40);
    }

    private function setDefault(int $volume): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE videos ALTER background_audio_volume SET DEFAULT {$volume}");
        }
    }
};
