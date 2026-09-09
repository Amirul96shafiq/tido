<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'profile_banner')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('profile_banner')
                    ->nullable()
                    ->after('avatar_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'profile_banner')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('profile_banner');
            });
        }
    }
};
