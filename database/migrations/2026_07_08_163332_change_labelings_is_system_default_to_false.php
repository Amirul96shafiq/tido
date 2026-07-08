<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SYSTEM_SLUGS = [
        'food-dining',
        'transportation-fuel',
        'groceries-household',
        'electronics-gadgets',
        'utilities-bills',
        'healthcare-medical',
        'entertainment-leisure',
        'office-supplies',
        'subscriptions-memberships',
    ];

    public function up(): void
    {
        DB::table('labelings')
            ->whereNotIn('slug', self::SYSTEM_SLUGS)
            ->update(['is_system' => false]);

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE labelings ALTER COLUMN is_system SET DEFAULT false');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE labelings ALTER COLUMN is_system SET DEFAULT true');
        }
    }
};
