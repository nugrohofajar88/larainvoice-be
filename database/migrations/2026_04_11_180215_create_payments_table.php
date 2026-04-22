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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('bank_account_id')->nullable()
                ->constrained('branch_bank_accounts');

            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->boolean('is_dp')->default(false);
            $table->date('payment_date');
            
            $table->string('proof_image')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
