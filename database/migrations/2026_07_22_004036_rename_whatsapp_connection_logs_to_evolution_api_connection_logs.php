<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('whatsapp_connection_logs')
            && ! Schema::hasTable('evolution_api_connection_logs')
        ) {
            Schema::rename('whatsapp_connection_logs', 'evolution_api_connection_logs');
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('evolution_api_connection_logs')
            && ! Schema::hasTable('whatsapp_connection_logs')
        ) {
            Schema::rename('evolution_api_connection_logs', 'whatsapp_connection_logs');
        }
    }
};
