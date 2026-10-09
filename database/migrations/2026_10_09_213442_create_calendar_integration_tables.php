<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_subject');
            $table->string('email');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes');
            $table->string('status')->default('connected');
            $table->string('error')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
        Schema::create('connected_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_connection_id')->constrained()->cascadeOnDelete();
            $table->text('google_id');
            $table->char('google_key', 64);
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->string('access_role');
            $table->string('mode')->default('off');
            $table->boolean('is_primary')->default(false);
            $table->text('sync_token')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['calendar_connection_id', 'google_key']);
        });
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connected_calendar_id')->constrained()->cascadeOnDelete();
            $table->text('google_id');
            $table->char('google_key', 64);
            $table->string('etag')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('location')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('all_day')->default(false);
            $table->boolean('is_series')->default(false);
            $table->text('recurring_event_id')->nullable();
            $table->string('status')->default('confirmed');
            $table->string('origin')->default('google');
            $table->json('remote_payload');
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['connected_calendar_id', 'google_key']);
            $table->index(['user_id', 'starts_at']);
            $table->index(['user_id', 'start_date']);
        });
        Schema::create('calendar_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connected_calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('operation');
            $table->json('payload');
            $table->json('before_payload')->nullable();
            $table->string('expected_etag')->nullable();
            $table->string('applied_etag')->nullable();
            $table->string('status')->default('pending');
            $table->string('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('undo_of_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_mutations');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('connected_calendars');
        Schema::dropIfExists('calendar_connections');
    }
};
