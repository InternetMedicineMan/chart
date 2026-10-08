<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key');
            $table->text('raw_text');
            $table->string('source')->default('in_app');
            $table->string('mode')->default('single');
            $table->timestamp('client_captured_at');
            $table->string('timezone');
            $table->string('status')->default('received');
            $table->json('parsed')->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->uuid('lease')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->foreignId('fallback_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->json('fallback_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
            $table->index(['status', 'available_at']);
        });
        Schema::create('capture_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->text('excerpt');
            $table->string('action_type');
            $table->string('status')->default('pending');
            $table->json('payload');
            $table->json('candidates')->nullable();
            $table->string('error')->nullable();
            $table->decimal('confidence', 4, 3)->default(0);
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
            $table->unique(['capture_id', 'sequence']);
        });
        Schema::create('capture_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->string('status');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();
        });
        Schema::create('action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->json('payload');
            $table->json('after_snapshot');
            $table->string('status')->default('ok');
            $table->timestamp('executed_at');
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });
        Schema::table('projects', fn (Blueprint $table) => $table->boolean('needs_review')->default(false));
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('needs_review'));
        foreach (['action_logs', 'capture_attempts', 'capture_items', 'captures'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
