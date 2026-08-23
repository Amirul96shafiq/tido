<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses') || Schema::hasColumn('expenses', 'document_classification')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('document_classification', 20)
                ->nullable()
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses') || ! Schema::hasColumn('expenses', 'document_classification')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['document_classification']);
            $table->dropColumn('document_classification');
        });
    }
};
