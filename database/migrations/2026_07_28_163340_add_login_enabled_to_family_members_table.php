<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            $table->boolean('login_enabled')->default(false)->after('allowlist_enabled');

            $table->index('login_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            $table->dropIndex(['login_enabled']);
            $table->dropColumn('login_enabled');
        });
    }
};
