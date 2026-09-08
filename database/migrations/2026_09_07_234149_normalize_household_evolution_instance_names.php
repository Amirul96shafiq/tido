<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('evolution_api_settings')
            ->where('household_id', 1)
            ->first();

        if ($setting === null || ! in_array($setting->instance_name, [
            null,
            '',
            'tido',
            config('services.evolution.instance_name', 'tido'),
        ], true)) {
            return;
        }

        $targetName = 'tido-hh-1';

        if (DB::table('evolution_api_settings')
            ->where('instance_name', $targetName)
            ->where('id', '!=', $setting->id)
            ->exists()) {
            throw new RuntimeException('Cannot assign tido-hh-1 because the Evolution instance name is already in use.');
        }

        DB::table('evolution_api_settings')
            ->where('id', $setting->id)
            ->update([
                'instance_name' => $targetName,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('evolution_api_settings')
            ->where('household_id', 1)
            ->where('instance_name', 'tido-hh-1')
            ->update([
                'instance_name' => 'tido',
                'updated_at' => now(),
            ]);
    }
};
