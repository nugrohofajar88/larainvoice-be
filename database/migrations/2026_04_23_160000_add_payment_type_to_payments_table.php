<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type')->nullable()->after('payment_method');
            $table->index('payment_type');
        });

        DB::table('payments')
            ->whereNull('payment_type')
            ->update([
                'payment_type' => DB::raw("CASE WHEN is_dp = 1 THEN 'dp' ELSE 'pelunasan' END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_type']);
            $table->dropColumn('payment_type');
        });
    }
};
