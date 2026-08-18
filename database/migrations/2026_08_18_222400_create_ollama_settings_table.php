<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ollama_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('host')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('timeout')->nullable();
            $table->unsignedInteger('num_ctx')->nullable();
            $table->unsignedSmallInteger('max_image_dimension')->nullable();
            $table->string('pdfinfo_binary')->nullable();
            $table->string('pdftocairo_binary')->nullable();
            $table->string('pdftotext_binary')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ollama_settings');
    }
};
