<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recurring_outflows') && ! Schema::hasTable('recurrings')) {
            Schema::rename('recurring_outflows', 'recurrings');
        }

        if (Schema::hasTable('recurring_occurrences') && Schema::hasColumn('recurring_occurrences', 'recurring_outflow_id')) {
            Schema::table('recurring_occurrences', function (Blueprint $table): void {
                $table->dropForeign(['recurring_outflow_id']);
            });

            Schema::table('recurring_occurrences', function (Blueprint $table): void {
                $table->renameColumn('recurring_outflow_id', 'recurring_id');
            });

            Schema::table('recurring_occurrences', function (Blueprint $table): void {
                $table->foreign('recurring_id')
                    ->references('id')
                    ->on('recurrings')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recurring_occurrences') && Schema::hasColumn('recurring_occurrences', 'recurring_id')) {
            Schema::table('recurring_occurrences', function (Blueprint $table): void {
                $table->dropForeign(['recurring_id']);
            });

            Schema::table('recurring_occurrences', function (Blueprint $table): void {
                $table->renameColumn('recurring_id', 'recurring_outflow_id');
            });

            Schema::table('recurring_occurrences', function (Blueprint $table): void {
                $table->foreign('recurring_outflow_id')
                    ->references('id')
                    ->on('recurrings')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('recurrings') && ! Schema::hasTable('recurring_outflows')) {
            Schema::rename('recurrings', 'recurring_outflows');
        }

        if (Schema::hasTable('recurring_occurrences') && Schema::hasColumn('recurring_occurrences', 'recurring_outflow_id')) {
            Schema::table('recurring_occurrences', function (Blueprint $table): void {
                $table->dropForeign(['recurring_outflow_id']);
            });

            Schema::table('recurring_occurrences', function (Blueprint $table): void {
                $table->foreign('recurring_outflow_id')
                    ->references('id')
                    ->on('recurring_outflows')
                    ->cascadeOnDelete();
            });
        }
    }
};
