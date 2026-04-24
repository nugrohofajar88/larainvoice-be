<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_order_id')->constrained('machine_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['machine_order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_order_assignments');
    }
};
