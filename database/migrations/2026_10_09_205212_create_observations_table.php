<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 50);
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->string('title');
            $table->text('body');
            $table->string('suggested_action')->nullable();
            $table->json('data');
            $table->unsignedInteger('score');
            $table->string('urgency', 10);
            $table->string('dedup_key', 191);
            $table->unsignedInteger('roll_up_count')->nullable();
            $table->date('observed_on');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('acted_on_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['user_id', 'dedup_key']);
            $table->index(['user_id', 'resolved_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
