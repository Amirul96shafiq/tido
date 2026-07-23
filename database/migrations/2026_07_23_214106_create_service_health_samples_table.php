<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_health_samples', function (Blueprint $table): void {
            $table->id();
            $table->string('service')->index();
            $table->string('status');
            $table->timestamp('checked_at')->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['service', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_health_samples');
    }
};
