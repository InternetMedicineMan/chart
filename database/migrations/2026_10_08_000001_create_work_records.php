<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug');
            $table->string('sphere')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('cadence_days')->nullable();
            $table->boolean('quiet_enabled')->default(true);
            $table->boolean('is_inbox')->default(false);
            $table->boolean('parked')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('last_touched_at')->nullable();
            $table->timestamp('last_shipped_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'slug']);
        });
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('relationship')->default('other');
            $table->string('company')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('type')->default('target_date');
            $table->date('target_date')->nullable();
            $table->unsignedSmallInteger('cadence_days')->nullable();
            $table->string('lifecycle')->default('active');
            $table->boolean('quiet_enabled')->default(true);
            $table->string('holder')->default('me');
            $table->foreignId('holder_person_id')->nullable()->constrained('people')->restrictOnDelete();
            $table->timestamp('holder_since')->nullable();
            $table->timestamp('last_touched_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'lifecycle']);
        });
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 255);
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('priority')->default(4);
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->string('source')->default('manual');
            $table->boolean('needs_review')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'completed_at', 'due_date']);
        });
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('kind')->default('thought');
            $table->boolean('needs_review')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'kind', 'created_at']);
        });
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value');
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['app_settings', 'notes', 'tasks', 'projects', 'people', 'domains'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
