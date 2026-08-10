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
        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('family_member_id')
                ->nullable()
                ->after('label_id')
                ->constrained('family_members')
                ->nullOnDelete();

            $table->boolean('is_shared')
                ->default(false)
                ->after('family_member_id')
                ->index();
        });

        DB::table('budgets')->update(['is_shared' => true]);
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['family_member_id']);
            $table->dropIndex(['is_shared']);
            $table->dropColumn(['family_member_id', 'is_shared']);
        });
    }
};
