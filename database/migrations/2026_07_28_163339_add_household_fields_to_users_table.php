<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('household_role', 32)->default('primary')->after('phone');
            $table->foreignId('family_member_id')
                ->nullable()
                ->unique()
                ->after('household_role')
                ->constrained('family_members')
                ->nullOnDelete();

            $table->index('household_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['family_member_id']);
            $table->dropIndex(['household_role']);
            $table->dropColumn(['household_role', 'family_member_id']);
        });
    }
};
