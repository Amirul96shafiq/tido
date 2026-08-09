<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        DB::table('activity_log')
            ->where('subject_type', 'App\Models\Invoice')
            ->update(['subject_type' => 'App\Models\Expense']);

        DB::table('activity_log')
            ->where('subject_type', 'App\Models\InvoiceItem')
            ->update(['subject_type' => 'App\Models\ExpenseItem']);

        if (Schema::hasColumn('activity_log', 'causer_type')) {
            // no-op: causer stays User
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        DB::table('activity_log')
            ->where('subject_type', 'App\Models\Expense')
            ->update(['subject_type' => 'App\Models\Invoice']);

        DB::table('activity_log')
            ->where('subject_type', 'App\Models\ExpenseItem')
            ->update(['subject_type' => 'App\Models\InvoiceItem']);
    }
};
