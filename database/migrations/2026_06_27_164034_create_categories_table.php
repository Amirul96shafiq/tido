<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labelings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('finance');
            $table->string('name');
            $table->string('slug');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labelings');
    }
};
