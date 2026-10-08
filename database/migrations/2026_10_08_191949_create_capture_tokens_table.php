<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('device_name', 100)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->json('scopes');
            $table->unsignedInteger('rate_limit_per_hour')->default(120);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::table('captures', function (Blueprint $table) {
            $table->foreignId('capture_token_id')->nullable()->constrained('capture_tokens')->nullOnDelete();
            $table->string('device_label', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('capture_token_id');
            $table->dropColumn('device_label');
        });
        Schema::dropIfExists('capture_tokens');
    }
};
