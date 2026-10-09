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
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('plan_date');
            $table->json('top_task_ids');
            $table->string('tomorrow_focus', 280)->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'plan_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_plans');
    }
};
