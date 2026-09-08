<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MH-004: households table + household_id on domain tables + backfill to household #1.
     */
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $primaryName = DB::table('users')
            ->where('id', 1)
            ->value('display_name')
            ?? DB::table('users')->where('id', 1)->value('name');

        $householdName = is_string($primaryName) && trim($primaryName) !== ''
            ? trim($primaryName).' Household'
            : 'Primary Household';

        DB::table('households')->insert([
            'id' => 1,
            'name' => $householdName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            'users',
            'family_members',
            'expenses',
            'labels',
            'payment_methods',
            'budgets',
            'recurrings',
            'backups',
            'ollama_settings',
            'evolution_api_connection_logs',
        ] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('household_id')
                    ->default(1)
                    ->after('id')
                    ->constrained('households')
                    ->cascadeOnDelete();
                $blueprint->index('household_id');
            });
        }

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropUnique('invoices_receipt_hash_unique');
            $table->dropUnique('invoices_whatsapp_message_id_unique');
            $table->unique(['household_id', 'receipt_hash'], 'expenses_household_receipt_hash_unique');
            $table->unique(['household_id', 'whatsapp_message_id'], 'expenses_household_whatsapp_message_id_unique');
        });

        Schema::table('family_members', function (Blueprint $table): void {
            $table->dropUnique('family_members_phone_unique');
            $table->dropUnique('family_members_whatsapp_lid_unique');
            $table->unique(['household_id', 'phone'], 'family_members_household_phone_unique');
            $table->unique(['household_id', 'whatsapp_lid'], 'family_members_household_whatsapp_lid_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_whatsapp_lid_unique');
            $table->unique(['household_id', 'whatsapp_lid'], 'users_household_whatsapp_lid_unique');
        });

        Schema::table('labels', function (Blueprint $table): void {
            $table->dropUnique('labelings_type_slug_unique');
            $table->unique(['household_id', 'type', 'slug'], 'labels_household_type_slug_unique');
        });

        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->dropUnique('payment_methods_slug_unique');
            $table->unique(['household_id', 'slug'], 'payment_methods_household_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->dropUnique('payment_methods_household_slug_unique');
            $table->unique('slug', 'payment_methods_slug_unique');
        });

        Schema::table('labels', function (Blueprint $table): void {
            $table->dropUnique('labels_household_type_slug_unique');
            $table->unique(['type', 'slug'], 'labelings_type_slug_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_household_whatsapp_lid_unique');
            $table->unique('whatsapp_lid', 'users_whatsapp_lid_unique');
        });

        Schema::table('family_members', function (Blueprint $table): void {
            $table->dropUnique('family_members_household_phone_unique');
            $table->dropUnique('family_members_household_whatsapp_lid_unique');
            $table->unique('phone', 'family_members_phone_unique');
            $table->unique('whatsapp_lid', 'family_members_whatsapp_lid_unique');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropUnique('expenses_household_receipt_hash_unique');
            $table->dropUnique('expenses_household_whatsapp_message_id_unique');
            $table->unique('receipt_hash', 'invoices_receipt_hash_unique');
            $table->unique('whatsapp_message_id', 'invoices_whatsapp_message_id_unique');
        });

        foreach ([
            'evolution_api_connection_logs',
            'ollama_settings',
            'backups',
            'recurrings',
            'budgets',
            'payment_methods',
            'labels',
            'expenses',
            'family_members',
            'users',
        ] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('household_id');
            });
        }

        Schema::dropIfExists('households');
    }
};
