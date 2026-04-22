<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_order_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_order_id')->constrained('machine_orders')->cascadeOnDelete();
            $table->foreignId('component_id')->nullable()->constrained('components')->nullOnDelete();
            $table->string('component_name_snapshot');
            $table->unsignedInteger('qty')->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->unsignedInteger('stock_deducted_qty')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_order_components');
    }
};
