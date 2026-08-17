<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_recurring_reminders')
                ->default(true)
                ->after('notify_evolution_api');
            $table->unsignedTinyInteger('recurring_reminder_lead_days')
                ->default(7)
                ->after('notify_recurring_reminders');
            $table->time('recurring_reminder_time')
                ->default('08:00:00')
                ->after('recurring_reminder_lead_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notify_recurring_reminders',
                'recurring_reminder_lead_days',
                'recurring_reminder_time',
            ]);
        });
    }
};
