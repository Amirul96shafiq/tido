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
            $table->boolean('notify_receipt_review')
                ->default(true)
                ->after('notify_recurring_reminders');
            $table->boolean('notify_service_status')
                ->default(true)
                ->after('notify_receipt_review');
            $table->boolean('notify_backups')
                ->default(true)
                ->after('notify_service_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notify_receipt_review',
                'notify_service_status',
                'notify_backups',
            ]);
        });
    }
};
