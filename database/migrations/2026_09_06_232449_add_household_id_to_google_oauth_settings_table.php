<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-household Google OAuth settings (retire singleton).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table): void {
            $table->foreignId('household_id')
                ->nullable()
                ->after('id')
                ->constrained('households')
                ->cascadeOnDelete();
        });

        if (DB::table('households')->where('id', 1)->exists()) {
            DB::table('google_oauth_settings')
                ->whereNull('household_id')
                ->update(['household_id' => 1]);
        }

        $existingHouseholdIds = DB::table('google_oauth_settings')
            ->whereNotNull('household_id')
            ->pluck('household_id')
            ->all();

        $householdIds = DB::table('households')->pluck('id');

        foreach ($householdIds as $householdId) {
            if (in_array($householdId, $existingHouseholdIds, true)) {
                continue;
            }

            DB::table('google_oauth_settings')->insert([
                'household_id' => $householdId,
                'client_id' => null,
                'client_secret' => null,
                'enabled' => false,
                'setup_completed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('google_oauth_settings', function (Blueprint $table): void {
            $table->unique('household_id');
        });

        Schema::table('google_oauth_login_logs', function (Blueprint $table): void {
            $table->foreignId('household_id')
                ->nullable()
                ->after('id')
                ->constrained('households')
                ->nullOnDelete();
            $table->index('household_id');
        });

        DB::table('google_oauth_login_logs')
            ->orderBy('id')
            ->get()
            ->each(function (object $log): void {
                $householdId = null;

                if ($log->user_id !== null) {
                    $householdId = DB::table('users')->where('id', $log->user_id)->value('household_id');
                }

                if ($householdId === null) {
                    $householdId = 1;
                }

                DB::table('google_oauth_login_logs')
                    ->where('id', $log->id)
                    ->update(['household_id' => $householdId]);
            });
    }

    public function down(): void
    {
        Schema::table('google_oauth_login_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('household_id');
        });

        Schema::table('google_oauth_settings', function (Blueprint $table): void {
            $table->dropUnique(['household_id']);
            $table->dropConstrainedForeignId('household_id');
        });
    }
};
