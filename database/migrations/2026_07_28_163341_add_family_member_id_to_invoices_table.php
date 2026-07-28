<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('family_member_id')
                ->nullable()
                ->after('whatsapp_sender')
                ->constrained('family_members')
                ->nullOnDelete();

            $table->index('family_member_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['family_member_id']);
            $table->dropIndex(['family_member_id']);
            $table->dropColumn('family_member_id');
        });
    }
};
