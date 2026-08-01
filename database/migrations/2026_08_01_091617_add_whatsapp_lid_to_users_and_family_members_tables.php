<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('whatsapp_lid')->nullable()->after('phone');
            $table->unique('whatsapp_lid');
        });

        Schema::table('family_members', function (Blueprint $table): void {
            $table->string('whatsapp_lid')->nullable()->after('phone');
            $table->unique('whatsapp_lid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['whatsapp_lid']);
            $table->dropColumn('whatsapp_lid');
        });

        Schema::table('family_members', function (Blueprint $table): void {
            $table->dropUnique(['whatsapp_lid']);
            $table->dropColumn('whatsapp_lid');
        });
    }
};
