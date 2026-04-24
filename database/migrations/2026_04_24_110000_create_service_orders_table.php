<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('order_number')->unique();
            $table->enum('order_type', ['service', 'training']);
            $table->date('order_date');
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('status')->default('draft');

            $table->string('title');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_finish_date')->nullable();
            $table->text('completion_notes')->nullable();

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'order_date']);
            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'order_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
