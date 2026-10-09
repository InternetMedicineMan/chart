<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('domain_id')->constrained()->restrictOnDelete();
            $table->text('entry');
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->string('source', 20)->default('manual');
            $table->dateTime('occurred_at');
            $table->uuid('request_key');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'request_key']);
            $table->index(['user_id', 'subject_type', 'subject_id', 'occurred_at'], 'activity_subject_date');
            $table->index(['user_id', 'domain_id', 'occurred_at']);
        });
        Schema::create('work_touches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('origin_type', 20);
            $table->unsignedBigInteger('origin_id');
            $table->dateTime('occurred_at');
            $table->unique(['user_id', 'subject_type', 'subject_id', 'origin_type', 'origin_id'], 'touch_origin');
            $table->index(['user_id', 'subject_type', 'subject_id', 'occurred_at'], 'touch_subject_date');
        });
        foreach (['domain' => 'domains', 'project' => 'projects'] as $type => $table) {
            DB::table($table)->whereNotNull('last_touched_at')->orderBy('id')->chunkById(200, function ($rows) use ($type) {
                DB::table('work_touches')->insert($rows->map(fn ($row) => [
                    'user_id' => $row->user_id, 'subject_type' => $type, 'subject_id' => $row->id,
                    'origin_type' => 'baseline', 'origin_id' => $row->id, 'occurred_at' => $row->last_touched_at,
                ])->all());
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_touches');
        Schema::dropIfExists('activity_logs');
    }
};
