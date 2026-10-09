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
        Schema::create('notifications_feed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('action_log_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('dedup_key');
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('unread');
            $table->json('undo_payload')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dedup_key']);
            $table->index(['user_id', 'status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_feed');
    }
};
