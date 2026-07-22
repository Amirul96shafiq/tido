<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'stylized_background_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table
                ->boolean('stylized_background_enabled')
                ->default(true)
                ->after('notify_evolution_api');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'stylized_background_enabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('stylized_background_enabled');
        });
    }
};
