<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence_rule', 500)->nullable();
            $table->date('recurrence_anchor')->nullable();
            $table->string('recurrence_timezone', 64)->nullable();
            $table->unsignedInteger('recurrence_index')->default(0);
            $table->foreignId('recurrence_parent_id')->nullable()->unique()->constrained('tasks')->restrictOnDelete();
            $table->unsignedInteger('revision')->default(0);
        });
        Schema::create('task_completions', function (Blueprint $table) {
            $table->foreignId('task_id')->primary()->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->json('previous_touches');
            $table->unsignedBigInteger('successor_id')->nullable();
            $table->json('successor_snapshot')->nullable();
        });
        $this->snapshots(true);
    }

    public function down(): void
    {
        $this->snapshots(false);
        Schema::dropIfExists('task_completions');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropUnique(['recurrence_parent_id']);
            $table->dropColumn('recurrence_parent_id');
            $table->dropColumn(['recurrence_rule', 'recurrence_anchor', 'recurrence_timezone', 'recurrence_index', 'revision']);
        });
    }

    private function snapshots(bool $adding): void
    {
        $fields = ['recurrence_rule' => null, 'recurrence_anchor' => null, 'recurrence_timezone' => null, 'recurrence_index' => 0, 'recurrence_parent_id' => null, 'revision' => 0];
        foreach (['action_logs' => ['before_snapshot', 'after_snapshot'], 'captures' => ['fallback_snapshot']] as $table => $columns) {
            $query = DB::table($table);
            if ($table === 'action_logs') {
                $query->where('target_type', 'task');
            }
            $query->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns, $fields, $adding) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        if ($row->$column === null) {
                            continue;
                        }
                        $snapshot = json_decode($row->$column, true, flags: JSON_THROW_ON_ERROR);
                        $updates[$column] = json_encode($adding ? $snapshot + $fields : array_diff_key($snapshot, $fields), JSON_THROW_ON_ERROR);
                    }
                    if ($updates) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }
};
