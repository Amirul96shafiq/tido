<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->string('relationship')->nullable()->after('email');
            $table->string('relationship_other')->nullable()->after('relationship');
            $table->date('date_of_birth')->nullable()->after('relationship_other');

            $table->index('relationship');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            $table->dropIndex(['relationship']);
            $table->dropIndex(['email']);
            $table->dropColumn([
                'display_name',
                'email',
                'relationship',
                'relationship_other',
                'date_of_birth',
            ]);
        });
    }
};
