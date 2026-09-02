<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'mobile_nav_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table
                ->boolean('mobile_nav_enabled')
                ->default(false)
                ->after('reduce_motion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'mobile_nav_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('mobile_nav_enabled');
        });
    }
};
