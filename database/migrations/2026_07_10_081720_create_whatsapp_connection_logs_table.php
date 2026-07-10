<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connection_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event');
            $table->string('status')->nullable();
            $table->string('connected_number')->nullable();
            $table->string('profile_name')->nullable();
            $table->string('instance_name');
            $table->string('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connection_logs');
    }
};
