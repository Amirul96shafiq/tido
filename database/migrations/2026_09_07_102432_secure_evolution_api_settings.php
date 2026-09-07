<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evolution_api_settings', function (Blueprint $table): void {
            $table->string('webhook_secret_hash', 64)
                ->nullable()
                ->unique('evolution_api_settings_webhook_secret_hash_unique')
                ->after('webhook_secret');
            $table->unique('instance_name', 'evolution_api_settings_instance_name_unique');
            $table->boolean('whatsapp_enabled')
                ->default(false)
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('evolution_api_settings', function (Blueprint $table): void {
            $table->dropUnique('evolution_api_settings_webhook_secret_hash_unique');
            $table->dropUnique('evolution_api_settings_instance_name_unique');
            $table->boolean('whatsapp_enabled')
                ->default(true)
                ->nullable(false)
                ->change();
            $table->dropColumn('webhook_secret_hash');
        });
    }
};
