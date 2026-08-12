<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_id')->constrained('recurrings')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_on');
            $table->string('status', 20)->default('upcoming');
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('actual_amount', 12, 2)->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['recurring_id', 'period_start'], 'recurring_occurrences_recurring_period_unique');
            $table->index(['status', 'due_on']);
            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_occurrences');
    }
};
