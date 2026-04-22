<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE components MODIFY type_size VARCHAR(255) NULL');
        DB::statement('ALTER TABLE components MODIFY weight DECIMAL(12,2) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE components SET type_size = '' WHERE type_size IS NULL");
        DB::statement('UPDATE components SET weight = 0 WHERE weight IS NULL');

        DB::statement('ALTER TABLE components MODIFY type_size VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE components MODIFY weight DECIMAL(12,2) NOT NULL');
    }
};
