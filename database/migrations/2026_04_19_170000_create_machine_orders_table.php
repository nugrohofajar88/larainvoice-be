<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('order_number')->unique();
            $table->date('order_date');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('machine_id')->constrained('machines');
            $table->unsignedInteger('qty')->default(1);
            $table->string('machine_name_snapshot');
            $table->decimal('base_price', 18, 2)->default(0);
            $table->enum('discount_type', ['percent', 'amount'])->nullable();
            $table->decimal('discount_value', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('additional_cost_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('paid_total', 18, 2)->default(0);
            $table->decimal('remaining_total', 18, 2)->default(0);
            $table->date('estimated_start_date')->nullable();
            $table->date('estimated_finish_date')->nullable();
            $table->date('actual_finish_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'order_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_orders');
    }
};
