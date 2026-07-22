<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->boolean('allowlist_enabled')->default(true);
            $table->timestamps();

            $table->index('allowlist_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
