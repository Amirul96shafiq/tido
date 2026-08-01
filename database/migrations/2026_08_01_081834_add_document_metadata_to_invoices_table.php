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
            $table->string('whatsapp_message_id')
                ->nullable()
                ->unique()
                ->after('whatsapp_sender');
            $table->string('file_mime_type', 100)
                ->nullable()
                ->after('image_path');
            $table->unsignedSmallInteger('file_page_count')
                ->nullable()
                ->after('file_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['whatsapp_message_id']);
            $table->dropColumn([
                'whatsapp_message_id',
                'file_mime_type',
                'file_page_count',
            ]);
        });
    }
};
