<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_order_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_order_id')->constrained('machine_orders')->cascadeOnDelete();
            $table->foreignId('cost_type_id')->nullable()->constrained('cost_types')->nullOnDelete();
            $table->string('cost_name_snapshot');
            $table->text('description')->nullable();
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_order_costs');
    }
};
