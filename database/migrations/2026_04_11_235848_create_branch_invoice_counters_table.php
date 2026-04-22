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
        Schema::create('branch_invoice_counters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained('branches');

            $table->string('prefix')->nullable(); // contoh: INV-MLG-

            $table->integer('month');
            $table->integer('year');

            $table->integer('last_number')->default(0);

            $table->timestamps();

            $table->unique(['branch_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_invoice_counters');
    }
};
