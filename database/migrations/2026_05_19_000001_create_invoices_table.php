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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('status')->default('draft');
            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->string('currency', 3)->default('usd');
            $table->integer('subtotal')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('total')->default(0);
            $table->text('notes')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_type')->nullable();
            $table->string('provider_id')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_type', 'provider_id']);
            $table->index(['status', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
