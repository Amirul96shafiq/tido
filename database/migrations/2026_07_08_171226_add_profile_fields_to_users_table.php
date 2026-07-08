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
            $table->string('phone', 20)->nullable()->after('avatar_url');
            $table->string('timezone', 64)->default('Asia/Kuala_Lumpur')->after('phone');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->string('date_format', 20)->default('d/m/Y')->after('locale');
            $table->boolean('notify_budget_alerts')->default(true)->after('date_format');
            $table->boolean('notify_profile_updates')->default(true)->after('notify_budget_alerts');
            $table->boolean('notify_email_digest')->default(false)->after('notify_profile_updates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'timezone',
                'locale',
                'date_format',
                'notify_budget_alerts',
                'notify_profile_updates',
                'notify_email_digest',
            ]);
        });
    }
};
