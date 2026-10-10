<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->json('reminder_offsets')->nullable());
        Schema::table('connected_calendars', fn (Blueprint $table) => $table->unsignedInteger('reminder_minutes')->nullable());
        Schema::table('notifications_feed', fn (Blueprint $table) => $table->string('target_url')->nullable());
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint_hash', 64)->unique();
            $table->text('subscription');
            $table->string('label', 100);
            $table->timestamps();
        });
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dedup_key', 64)->unique();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('scheduled_at')->index();
            $table->string('status', 20)->default('pending');
            $table->foreignId('notification_id')->nullable()->constrained('notifications_feed')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_id')->constrained('notifications_feed')->cascadeOnDelete();
            $table->foreignId('push_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamps();
            $table->unique(['notification_id', 'push_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('push_subscriptions');
        Schema::table('notifications_feed', fn (Blueprint $table) => $table->dropColumn('target_url'));
        Schema::table('connected_calendars', fn (Blueprint $table) => $table->dropColumn('reminder_minutes'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('reminder_offsets'));
    }
};
