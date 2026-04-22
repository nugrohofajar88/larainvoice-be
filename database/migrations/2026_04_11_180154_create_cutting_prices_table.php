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
        Schema::create('cutting_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_type_id')->constrained('machine_types');
            $table->foreignId('plate_type_id')->constrained('plate_types');
            $table->foreignId('size_id')->constrained('sizes');

            $table->decimal('price_easy', 12, 2)->default(0);
            $table->decimal('price_medium', 12, 2)->default(0);
            $table->decimal('price_difficult', 12, 2)->default(0);
            $table->decimal('price_per_minute', 12, 2)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cutting_prices');
    }
};
