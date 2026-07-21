<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasColumn('users', 'notify_whatsapp_connection')
            && ! Schema::hasColumn('users', 'notify_evolution_api')
        ) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('notify_whatsapp_connection', 'notify_evolution_api');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('users', 'notify_evolution_api')
            && ! Schema::hasColumn('users', 'notify_whatsapp_connection')
        ) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('notify_evolution_api', 'notify_whatsapp_connection');
            });
        }
    }
};
