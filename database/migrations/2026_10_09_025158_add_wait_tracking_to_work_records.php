<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('waiting_on_person_id')->nullable()->constrained('people')->restrictOnDelete();
            $table->timestamp('waiting_since')->nullable();
            $table->date('wait_expected_by')->nullable();
            $table->unsignedInteger('wait_revision')->default(0);
            $table->index(['user_id', 'waiting_on_person_id', 'wait_expected_by']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->date('wait_expected_by')->nullable();
            $table->unsignedInteger('wait_revision')->default(0);
            $table->index(['user_id', 'holder', 'wait_expected_by']);
        });
        $this->snapshots(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->snapshots(false);
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'waiting_on_person_id', 'wait_expected_by']);
            $table->dropConstrainedForeignId('waiting_on_person_id');
            $table->dropColumn(['waiting_since', 'wait_expected_by', 'wait_revision']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'holder', 'wait_expected_by']);
            $table->dropColumn(['wait_expected_by', 'wait_revision']);
        });
    }

    private function snapshots(bool $adding): void
    {
        $defaults = [
            'task' => ['waiting_on_person_id' => null, 'waiting_since' => null, 'wait_expected_by' => null, 'wait_revision' => 0],
            'project' => ['wait_expected_by' => null, 'wait_revision' => 0],
        ];
        foreach (['action_logs' => 'after_snapshot', 'captures' => 'fallback_snapshot'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column, $defaults, $adding) {
                foreach ($rows as $row) {
                    $fields = $defaults[$table === 'captures' ? 'task' : $row->target_type] ?? [];
                    $snapshot = json_decode($row->$column, true, flags: JSON_THROW_ON_ERROR);
                    if (! is_array($snapshot) || ! $fields) {
                        continue;
                    }
                    $snapshot = $adding ? $snapshot + $fields : array_diff_key($snapshot, $fields);
                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                }
            });
        }
    }
};
