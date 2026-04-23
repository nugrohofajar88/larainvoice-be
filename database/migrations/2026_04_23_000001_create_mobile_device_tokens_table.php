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
        Schema::create('mobile_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('provider')->default('fcm');
            $table->string('platform', 32)->nullable();
            $table->text('token');
            $table->string('token_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'token_hash'], 'mobile_device_tokens_user_token_hash_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_device_tokens');
    }
};
