<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || Schema::hasTable('expenses')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
        });

        Schema::rename('invoices', 'expenses');
        Schema::rename('invoice_items', 'expense_items');

        Schema::table('expense_items', function (Blueprint $table): void {
            $table->renameColumn('invoice_id', 'expense_id');
        });

        Schema::table('expense_items', function (Blueprint $table): void {
            $table->foreign('expense_id')->references('id')->on('expenses')->cascadeOnDelete();
        });

        $this->renameExpenseIndexes();
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses') || Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('expense_items', function (Blueprint $table): void {
            $table->dropForeign(['expense_id']);
        });

        $this->renameExpenseIndexes(reverse: true);

        Schema::table('expense_items', function (Blueprint $table): void {
            $table->renameColumn('expense_id', 'invoice_id');
        });

        Schema::rename('expense_items', 'invoice_items');
        Schema::rename('expenses', 'invoices');

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    private function renameExpenseIndexes(bool $reverse = false): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $map = [
            'invoices_receipt_hash_unique' => 'expenses_receipt_hash_unique',
            'invoices_whatsapp_message_id_unique' => 'expenses_whatsapp_message_id_unique',
            'invoices_family_member_id_index' => 'expenses_family_member_id_index',
            'invoices_date_time_status_index' => 'expenses_date_time_status_index',
            'invoices_currency_conversion_status_index' => 'expenses_currency_conversion_status_index',
            'invoices_currency_currency_conversion_status_index' => 'expenses_currency_currency_conversion_status_index',
        ];

        Schema::table('expenses', function (Blueprint $table) use ($map, $reverse): void {
            foreach ($map as $from => $to) {
                if ($reverse) {
                    $table->renameIndex($to, $from);
                } else {
                    $table->renameIndex($from, $to);
                }
            }
        });
    }
};
