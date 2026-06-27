<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); // null = overall budget
            $table->decimal('amount', 12, 2);
            $table->string('period', 20)->default('monthly'); // daily, weekly, monthly, quarterly, yearly
            $table->unsignedTinyInteger('quarter')->nullable(); // 1, 2, 3, 4 (only if period = quarterly)
            $table->unsignedInteger('year'); // the calendar year this budget belongs to
            $table->unsignedInteger('alert_threshold')->default(80); // alert percentage (e.g. 80%)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
