<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('family_members', 'profile_banner')) {
            Schema::table('family_members', function (Blueprint $table): void {
                $table->string('profile_banner')
                    ->nullable()
                    ->after('avatar_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('family_members', 'profile_banner')) {
            Schema::table('family_members', function (Blueprint $table): void {
                $table->dropColumn('profile_banner');
            });
        }
    }
};
