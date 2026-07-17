<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('title')->nullable()->after('id');
            $table->string('icon')->nullable()->after('title');
            $table->text('notes')->nullable()->after('is_active');
            $table->unsignedInteger('critical_threshold')->default(100)->after('alert_threshold');
            $table->boolean('notify_filament')->default(true)->after('critical_threshold');
            $table->boolean('notify_whatsapp')->default(true)->after('notify_filament');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'icon',
                'notes',
                'critical_threshold',
                'notify_filament',
                'notify_whatsapp',
            ]);
        });
    }
};
