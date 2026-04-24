<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('item_type')->nullable()->after('product_type');
            $table->string('source_type')->nullable()->after('item_type');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('description')->nullable()->after('source_id');
            $table->string('unit')->nullable()->after('description');

            $table->index(['source_type', 'source_id'], 'invoice_items_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex('invoice_items_source_index');
            $table->dropColumn(['item_type', 'source_type', 'source_id', 'description', 'unit']);
        });
    }
};
