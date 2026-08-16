<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurrings', function (Blueprint $table) {
            $table->decimal('prior_contributed_amount', 12, 2)
                ->nullable()
                ->after('goal_target_amount');
        });
    }

    public function down(): void
    {
        Schema::table('recurrings', function (Blueprint $table) {
            $table->dropColumn('prior_contributed_amount');
        });
    }
};
