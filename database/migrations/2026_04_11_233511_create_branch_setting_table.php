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
        Schema::create('branch_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained('branches')->unique();

            // STOCK
            $table->integer('minimum_stock')->default(0);

            // SALES
            $table->decimal('sales_commission_percentage', 5, 2)->default(0);

            // INVOICE IDENTITY
            $table->string('invoice_header_name')->nullable();
            $table->string('invoice_header_position')->nullable(); // contoh: "Manager / Admin"

            $table->string('invoice_footer_note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_setting');
    }
};
