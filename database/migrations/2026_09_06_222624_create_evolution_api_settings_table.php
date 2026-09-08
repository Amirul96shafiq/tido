<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MH-008: Per-household Evolution API credentials (seed household #1 from env).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evolution_api_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->string('api_url')->nullable();
            $table->text('api_key')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('instance_name')->nullable();
            $table->boolean('whatsapp_enabled')->default(true);
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();

            $table->unique('household_id');
        });

        if (DB::table('households')->where('id', 1)->exists()) {
            DB::table('evolution_api_settings')->insert([
                'household_id' => 1,
                'api_url' => config('services.evolution.api_url'),
                'api_key' => null,
                'webhook_secret' => null,
                'instance_name' => config('services.evolution.instance_name'),
                'whatsapp_enabled' => true,
                'setup_completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evolution_api_settings');
    }
};
