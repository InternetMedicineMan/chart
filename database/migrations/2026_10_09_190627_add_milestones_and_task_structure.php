<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite table rebuilds must toggle foreign keys outside a transaction. MySQL DDL is non-transactional too.
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->unsignedInteger('weight')->default(1);
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('milestone_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->restrictOnDelete();
            $table->string('touch_target_type', 20)->nullable();
            $table->unsignedBigInteger('touch_target_id')->nullable();
        });
        Schema::table('task_completions', fn (Blueprint $table) => $table->json('successor_children')->nullable());
        $this->snapshots(true);
    }

    public function down(): void
    {
        $this->snapshots(false);
        Schema::table('task_completions', fn (Blueprint $table) => $table->dropColumn('successor_children'));
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['milestone_id']);
            $table->dropForeign(['parent_task_id']);
            $table->dropColumn(['milestone_id', 'parent_task_id', 'touch_target_type', 'touch_target_id']);
        });
        Schema::dropIfExists('milestones');
    }

    private function snapshots(bool $adding): void
    {
        $fields = ['milestone_id' => null, 'parent_task_id' => null, 'touch_target_type' => null, 'touch_target_id' => null];
        foreach (['action_logs' => ['before_snapshot', 'after_snapshot'], 'captures' => ['fallback_snapshot'], 'task_completions' => ['before_snapshot', 'after_snapshot', 'successor_snapshot']] as $table => $columns) {
            $key = $table === 'task_completions' ? 'task_id' : 'id';
            $query = DB::table($table);
            if ($table === 'action_logs') {
                $query->where('target_type', 'task');
            }
            $query->orderBy($key)->chunkById(200, function ($rows) use ($table, $columns, $fields, $adding, $key) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        if ($row->$column !== null) {
                            $snapshot = json_decode($row->$column, true, flags: JSON_THROW_ON_ERROR);
                            $updates[$column] = json_encode($adding ? $snapshot + $fields : array_diff_key($snapshot, $fields), JSON_THROW_ON_ERROR);
                        }
                    }
                    if ($updates) {
                        DB::table($table)->where($key, $row->$key)->update($updates);
                    }
                }
            }, $key);
        }
    }
};
