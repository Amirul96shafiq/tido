<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_items', function (Blueprint $table): void {
            if (! Schema::hasIndex('expense_items', 'expense_items_expense_id_index')) {
                $table->index('expense_id');
            }

            if (! Schema::hasIndex('expense_items', 'expense_items_label_id_index')) {
                $table->index('label_id');
            }
        });

        Schema::table('budgets', function (Blueprint $table): void {
            if (! Schema::hasIndex('budgets', 'budgets_is_active_index')) {
                $table->index('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_items', function (Blueprint $table): void {
            if (Schema::hasIndex('expense_items', 'expense_items_expense_id_index')) {
                $table->dropIndex('expense_items_expense_id_index');
            }

            if (Schema::hasIndex('expense_items', 'expense_items_label_id_index')) {
                $table->dropIndex('expense_items_label_id_index');
            }
        });

        Schema::table('budgets', function (Blueprint $table): void {
            if (Schema::hasIndex('budgets', 'budgets_is_active_index')) {
                $table->dropIndex('budgets_is_active_index');
            }
        });
    }
};
