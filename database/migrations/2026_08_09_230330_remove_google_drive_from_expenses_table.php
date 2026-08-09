<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            DB::table('expenses')
                ->where('source', 'google_drive')
                ->update(['source' => 'manual']);

            if (Schema::hasColumn('expenses', 'google_drive_file_id')) {
                Schema::table('expenses', function (Blueprint $table): void {
                    $table->dropColumn('google_drive_file_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'google_drive_file_id')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->string('google_drive_file_id')->nullable()->after('status');
            });
        }
    }
};
