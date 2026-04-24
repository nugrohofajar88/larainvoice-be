<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type')->nullable()->after('invoice_number');
            $table->string('source_type')->nullable()->after('invoice_type');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');

            $table->index(['source_type', 'source_id'], 'invoices_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_source_index');
            $table->dropColumn(['invoice_type', 'source_type', 'source_id']);
        });
    }
};
