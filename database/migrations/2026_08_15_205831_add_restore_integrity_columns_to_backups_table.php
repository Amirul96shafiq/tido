<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->char('content_sha256', 64)->nullable()->after('restore_token_hash');
            $table->char('manifest_hmac', 64)->nullable()->after('content_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn(['content_sha256', 'manifest_hmac']);
        });
    }
};
