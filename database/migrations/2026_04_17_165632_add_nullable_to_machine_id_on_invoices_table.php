<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);

            $table->unsignedBigInteger('machine_id')
                ->nullable()
                ->change();

            $table->foreign('machine_id')
                ->references('id')
                ->on('machines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);

            $table->unsignedBigInteger('machine_id')
                ->nullable(false)
                ->change();

            $table->foreign('machine_id')
                ->references('id')
                ->on('machines')
                ->nullOnDelete();
        });
    }
};
