<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_order_id')->constrained('machine_orders')->cascadeOnDelete();
            $table->date('payment_date');
            $table->enum('payment_type', ['dp', 'pelunasan', 'cicilan', 'refund'])->default('dp');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['machine_order_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_order_payments');
    }
};
