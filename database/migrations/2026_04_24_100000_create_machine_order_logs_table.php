<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_order_id')->constrained('machine_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 50)->default('status_changed');
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['machine_order_id', 'created_at']);
            $table->index(['action_type', 'to_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_order_logs');
    }
};
