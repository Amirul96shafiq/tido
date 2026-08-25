<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'reduce_motion')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table
                ->boolean('reduce_motion')
                ->default(false)
                ->after('stylized_background_enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'reduce_motion')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('reduce_motion');
        });
    }
};
